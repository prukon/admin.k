<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\Postpay\PostpayJournalService;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Services\UserLessonPackageConsumptionAdjuster;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Аннуляция занятия в журнале /schedule: откат consumes_lesson, удаление events + UTSS.
 */
final class JournalOccurrenceAnnulmentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PostpayJournalService $postpayJournal,
        private readonly PostpayUsersPriceSync $postpaySync,
        private readonly ScheduleJournalMonthService $journalMonthService,
    ) {
    }

    /**
     * @return array{
     *     utss_id: int,
     *     user_id: int,
     *     occurrence_date: string,
     *     deleted: true,
     *     occurrence_count: int,
     *     is_postpay: bool,
     *     is_flexible: bool,
     *     remaining: null|array{
     *         utss_id: int,
     *         comment: string|null,
     *         status: array{id: int|null, title: string|null, icon: string|null, color: string|null}
     *     },
     *     user_lesson_package_id: int|null,
     *     slots_remaining: int|null,
     *     lessons_total: int|null,
     *     package_name: string|null
     * }
     */
    public function annul(int $partnerId, UserTeamScheduleSlot $utss, ?int $authorId): array
    {
        if ((int) $utss->partner_id !== $partnerId) {
            throw new InvalidArgumentException('Запись занятия не найдена.');
        }

        $utss->loadMissing([
            'user:id,name,lastname,partner_id,is_enabled',
            'slot.team:id,title',
            'userLessonPackage.lessonPackage:id,name,schedule_type',
        ]);

        $user = $utss->user;
        if (! $user || (int) $user->partner_id !== $partnerId || ! (bool) $user->is_enabled) {
            throw new InvalidArgumentException('Запись занятия не найдена.');
        }

        $occurrenceDate = Carbon::parse($utss->starts_at)->format('Y-m-d');
        $teamId = (int) ($utss->slot?->team_id ?? 0);
        $isPostpay = $utss->user_lesson_package_id === null && ! (bool) $utss->is_trial_lesson;
        $scheduleType = (string) ($utss->userLessonPackage?->lessonPackage?->schedule_type ?? '');
        $isFlexible = $scheduleType === 'flexible';

        if ($teamId > 0) {
            try {
                $this->postpayJournal->assertOccurrenceEditable((int) $user->id, $teamId, $occurrenceDate);
            } catch (DomainException $e) {
                throw $e;
            }
        }

        $bindId = (int) $utss->id;
        $userId = (int) $user->id;
        $slotId = (int) $utss->team_schedule_slot_id;
        $ulpId = $utss->user_lesson_package_id !== null ? (int) $utss->user_lesson_package_id : null;
        $packageName = null;
        if ($utss->is_trial_lesson) {
            $packageName = 'Пробное';
        } elseif ($isPostpay) {
            $packageName = 'Постоплата';
        } else {
            $packageName = trim((string) ($utss->userLessonPackage?->lessonPackage?->name ?? 'Абонемент'));
        }

        $userLabel = trim((string) ($user->full_name ?? ''));
        if ($userLabel === '') {
            $userLabel = 'Ученик #'.$userId;
        }

        $teamTitle = (string) ($utss->slot?->team?->title ?? '');
        $statusTitleBefore = null;

        DB::transaction(function () use (
            $partnerId,
            $userId,
            $slotId,
            $ulpId,
            $occurrenceDate,
            $bindId,
            $userLabel,
            $teamTitle,
            $packageName,
            $utss,
            $authorId,
            $teamId,
            &$statusTitleBefore,
        ): void {
            /** @var UserLessonOccurrenceStatusEvent|null $latestEvent */
            $latestEvent = UserLessonOccurrenceStatusEvent::query()
                ->with(['lessonOccurrenceStatus:id,title,consumes_lesson'])
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('team_schedule_slot_id', $slotId)
                ->whereDate('occurrence_date', $occurrenceDate)
                ->when(
                    $ulpId !== null,
                    fn ($q) => $q->where('user_lesson_package_id', $ulpId),
                    fn ($q) => $q->whereNull('user_lesson_package_id')
                )
                ->orderByDesc('id')
                ->first();

            $statusTitleBefore = $latestEvent?->lessonOccurrenceStatus?->title;

            if ($latestEvent !== null && $ulpId !== null) {
                $consumed = (bool) ($latestEvent->lessonOccurrenceStatus?->consumes_lesson ?? false);
                if ($consumed) {
                    /** @var UserLessonPackage|null $ulp */
                    $ulp = UserLessonPackage::query()->whereKey($ulpId)->first();
                    if ($ulp) {
                        UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, 1);
                    }
                }
            }

            UserLessonOccurrenceStatusEvent::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('team_schedule_slot_id', $slotId)
                ->whereDate('occurrence_date', $occurrenceDate)
                ->when(
                    $ulpId !== null,
                    fn ($q) => $q->where('user_lesson_package_id', $ulpId),
                    fn ($q) => $q->whereNull('user_lesson_package_id')
                )
                ->delete();

            $utss->delete();

            if ($teamId > 0) {
                $this->postpaySync->syncAfterOccurrenceChange(
                    $partnerId,
                    $userId,
                    $occurrenceDate,
                    $teamId
                );
            }

            $statusPart = $statusTitleBefore ? (', статус: '.$statusTitleBefore) : '';
            $teamPart = $teamTitle !== '' ? (', группа: '.$teamTitle) : '';

            $this->auditLogger->record(
                AuditEvent::ScheduleJournalOccurrenceDeleted,
                AuditContext::make(sprintf(
                    'Удалено занятие в журнале; ученик: %s, дата: %s%s%s, тип: %s',
                    $userLabel,
                    Carbon::parse($occurrenceDate)->format('d.m.Y'),
                    $teamPart,
                    $statusPart,
                    $packageName
                ))
                    ->withAuthorId($authorId)
                    ->withPartnerId($partnerId)
                    ->withUserId($userId)
                    ->withTargetReference(
                        UserTeamScheduleSlot::class,
                        $bindId,
                        $userLabel.', '.$packageName.', '.$occurrenceDate
                    )
            );
        });

        $dayOccurrences = $this->remainingDayOccurrences($partnerId, $userId, $occurrenceDate);
        $occurrenceCount = count($dayOccurrences);
        $remaining = null;
        if ($occurrenceCount === 1) {
            $item = $dayOccurrences[0];
            $remaining = [
                'utss_id' => (int) $item['utss_id'],
                'comment' => $item['comment'] ?? null,
                'package_hover' => $item['package_hover'] ?? null,
                'status' => [
                    'id' => isset($item['lesson_occurrence_status_id'])
                        ? (int) $item['lesson_occurrence_status_id']
                        : null,
                    'title' => $item['status_title'] ?? null,
                    'icon' => $item['status_icon'] ?? null,
                    'color' => $item['status_color'] ?? null,
                ],
            ];
        }

        $slotsRemaining = null;
        $lessonsTotal = null;
        $ulpPackageName = null;
        $feeAmountCents = null;
        if ($ulpId !== null) {
            /** @var UserLessonPackage|null $ulpAfter */
            $ulpAfter = UserLessonPackage::query()
                ->with('lessonPackage:id,name')
                ->whereKey($ulpId)
                ->first();
            if ($ulpAfter) {
                // Для гибкого в журнале — остаток по consumes_lesson, не COUNT(utss).
                $slotsRemaining = max(0, (int) $ulpAfter->lessons_remaining);
                $lessonsTotal = (int) $ulpAfter->lessons_total;
                $ulpPackageName = trim((string) ($ulpAfter->lessonPackage?->name ?? ''));
                $feeAmountCents = (int) ($ulpAfter->fee_amount_cents ?? 0);
            }
        }

        $packageHoverAfter = null;
        if ($occurrenceCount > 1) {
            $hoverLines = [];
            foreach ($dayOccurrences as $dayItem) {
                $line = trim((string) ($dayItem['package_hover'] ?? $dayItem['package_name'] ?? ''));
                if ($line !== '') {
                    $hoverLines[] = $line;
                }
            }
            $packageHoverAfter = $hoverLines !== [] ? implode("\n", $hoverLines) : null;
        } elseif ($occurrenceCount === 1 && is_array($remaining)) {
            $packageHoverAfter = $remaining['package_hover'] ?? null;
        }

        return [
            'utss_id' => $bindId,
            'user_id' => $userId,
            'occurrence_date' => $occurrenceDate,
            'deleted' => true,
            'occurrence_count' => $occurrenceCount,
            'is_postpay' => $isPostpay,
            'is_flexible' => $isFlexible,
            'remaining' => $remaining,
            'user_lesson_package_id' => $ulpId,
            'slots_remaining' => $slotsRemaining,
            'lessons_total' => $lessonsTotal,
            'fee_amount_cents' => $feeAmountCents,
            'package_name' => $ulpPackageName !== '' ? $ulpPackageName : $packageName,
            'package_hover' => $packageHoverAfter,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function remainingDayOccurrences(int $partnerId, int $userId, string $occurrenceDate): array
    {
        $occurrences = $this->journalMonthService->occurrencesByUserDate(
            $partnerId,
            [$userId],
            Carbon::parse($occurrenceDate)->startOfDay(),
            Carbon::parse($occurrenceDate)->endOfDay(),
            'all',
        );

        $key = $userId.'_'.$occurrenceDate;

        return $occurrences[$key] ?? [];
    }
}
