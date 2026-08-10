<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\SchoolCalendarAssignmentEligibilityService;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use App\Services\LessonPackages\UserLessonPackageAutoProlongGuard;
use App\Services\UserLessonPackageCalendarPeriodService;
use App\Support\Money;
use App\Support\UserPriceTeamMembership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Разовое занятие (no_schedule) из пустой ячейки журнала /schedule:
 * создание нового назначения или привязка свободного + статус.
 */
final class JournalSingleLessonPlacementService
{
    public function __construct(
        private readonly JournalTeamScheduleSlotEnsureService $slotEnsure,
        private readonly SchoolCalendarAssignmentEligibilityService $assignmentEligibility,
        private readonly UserLessonPackageCalendarPeriodService $calendarPeriodService,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
        private readonly UserLessonPackageAutoProlongGuard $autoProlongGuard,
    ) {
    }

    /**
     * @param  array{
     *     user_lesson_package_id?: int|null,
     *     lesson_package_id?: int|null,
     *     fee_amount?: float|string|null,
     * }  $payload
     * @return array{
     *     utss_id: int,
     *     user_lesson_package_id: int,
     *     is_trial_lesson: false,
     *     occurrence_date: string,
     *     comment: string|null,
     *     status: array{id: int, title: string, icon: string|null, color: string|null}
     * }
     */
    public function place(
        int $partnerId,
        User $user,
        Team $team,
        CarbonImmutable $occurrenceDate,
        LessonOccurrenceStatus $status,
        array $payload,
        ?int $createdByUserId,
        ?int $trainerProfileId = null,
        ?string $comment = null,
        ?array $trainerProfileIds = null,
    ): array {
        $this->assertUserAndTeam($partnerId, $user, $team);
        try {
            $this->autoProlongGuard->assertCanRegisterTrialOrCalendarEntryForUser((int) $user->id);
        } catch (ValidationException) {
            throw new InvalidArgumentException(UserLessonPackageAutoProlongGuard::BLOCK_REASON);
        }

        if ((int) $status->partner_id !== $partnerId || ! $status->is_active) {
            throw new InvalidArgumentException('Выбранный статус не найден или неактивен.');
        }

        $ulpId = isset($payload['user_lesson_package_id']) ? (int) $payload['user_lesson_package_id'] : 0;
        $templateId = isset($payload['lesson_package_id']) ? (int) $payload['lesson_package_id'] : 0;

        if ($ulpId > 0 && $templateId > 0) {
            throw new InvalidArgumentException('Укажите либо свободное назначение, либо шаблон разового занятия.');
        }
        if ($ulpId < 1 && $templateId < 1) {
            throw new InvalidArgumentException('Укажите назначение или шаблон разового занятия.');
        }

        $occurrenceYmd = $occurrenceDate->toDateString();
        $commentValue = $comment !== null && trim($comment) !== '' ? trim($comment) : null;
        $utssId = 0;
        $createdOrBoundUlpId = 0;
        $resolvedTrainerIds = $trainerProfileIds ?? (
            $trainerProfileId !== null && $trainerProfileId > 0 ? [$trainerProfileId] : []
        );

        DB::transaction(function () use (
            $partnerId,
            $user,
            $team,
            $occurrenceDate,
            $occurrenceYmd,
            $ulpId,
            $templateId,
            $payload,
            $createdByUserId,
            $status,
            $resolvedTrainerIds,
            $commentValue,
            &$utssId,
            &$createdOrBoundUlpId,
        ): void {
            if ($ulpId > 0) {
                $ulp = $this->resolveBindableUlp($partnerId, (int) $user->id, $ulpId);
            } else {
                $template = $this->resolveSingleLessonTemplate($partnerId, $templateId);
                $feeAmountCents = Money::toCentsOrFail($payload['fee_amount'] ?? 0);

                $ulp = UserLessonPackage::query()->create([
                    'user_id' => (int) $user->id,
                    'lesson_package_id' => (int) $template->id,
                    'starts_at' => null,
                    'ends_at' => null,
                    'lessons_total' => (int) $template->lessons_count,
                    'lessons_remaining' => (int) $template->lessons_count,
                    'fee_amount_cents' => $feeAmountCents,
                    'is_paid' => false,
                    'created_by' => $createdByUserId,
                ]);
            }

            // Eligibility-query тянет lessonPackage без duration_days — сбрасываем, иначе applyFirstCalendarAnchor
            // видит duration_days = null на уже загруженной связи.
            if ($ulp->relationLoaded('lessonPackage')) {
                $ulp->unsetRelation('lessonPackage');
            }
            $ulp->load('lessonPackage');

            $createdOrBoundUlpId = (int) $ulp->id;

            $this->assertUlpStillBindable($ulp);

            $this->calendarPeriodService->applyFirstCalendarAnchor($ulp, $occurrenceDate);
            $ulp->refresh();

            if ($ulp->starts_at === null || $ulp->ends_at === null) {
                throw new InvalidArgumentException('У назначения не задан период действия.');
            }

            $ulpStart = CarbonImmutable::parse($ulp->starts_at->format('Y-m-d'))->startOfDay();
            $ulpEnd = CarbonImmutable::parse($ulp->ends_at->format('Y-m-d'))->startOfDay();
            if ($occurrenceDate->lt($ulpStart) || $occurrenceDate->gt($ulpEnd)) {
                throw new InvalidArgumentException(sprintf(
                    'Дата выходит за пределы периода абонемента (%s–%s).',
                    $ulpStart->format('d.m.Y'),
                    $ulpEnd->format('d.m.Y')
                ));
            }

            $weekday = (int) $occurrenceDate->format('N');
            $slot = $this->slotEnsure->resolveOrCreateNextFreeForUserDate(
                $partnerId,
                (int) $team->id,
                $weekday,
                (int) $user->id,
                $occurrenceYmd,
            );

            $periodEndYmd = $ulpEnd->toDateString();

            $utss = UserTeamScheduleSlot::query()->create([
                'partner_id' => $partnerId,
                'user_id' => (int) $user->id,
                'user_lesson_package_id' => (int) $ulp->id,
                'team_schedule_slot_id' => (int) $slot->id,
                'starts_at' => $occurrenceYmd,
                'ends_at' => $periodEndYmd,
                'created_by' => $createdByUserId,
                'is_trial_lesson' => false,
            ]);

            $this->occurrenceStatusService->apply(
                $partnerId,
                (int) $user->id,
                (int) $slot->id,
                $occurrenceYmd,
                (int) $ulp->id,
                $status,
                $createdByUserId,
                $resolvedTrainerIds[0] ?? null,
                $commentValue,
                $resolvedTrainerIds,
            );

            $utssId = (int) $utss->id;
        });

        /** @var UserLessonPackage $ulpForHover */
        $ulpForHover = UserLessonPackage::query()
            ->with('lessonPackage:id,name')
            ->whereKey($createdOrBoundUlpId)
            ->firstOrFail();
        $packageName = trim((string) ($ulpForHover->lessonPackage?->name ?? ''));

        return [
            'utss_id' => $utssId,
            'user_lesson_package_id' => $createdOrBoundUlpId,
            'is_trial_lesson' => false,
            'occurrence_date' => $occurrenceYmd,
            'comment' => $commentValue,
            'package_hover' => ScheduleJournalMonthService::packageHoverLabel(
                $packageName !== '' ? $packageName : 'Абонемент',
                (int) ($ulpForHover->fee_amount_cents ?? 0),
            ),
            'status' => [
                'id' => (int) $status->id,
                'title' => (string) $status->title,
                'icon' => $status->icon !== null && $status->icon !== '' ? (string) $status->icon : null,
                'color' => $status->color !== null && $status->color !== '' ? (string) $status->color : null,
            ],
        ];
    }

    private function assertUserAndTeam(int $partnerId, User $user, Team $team): void
    {
        if ((int) $user->partner_id !== $partnerId || ! (bool) $user->is_enabled) {
            throw new InvalidArgumentException('Ученик не найден.');
        }
        if ((int) $team->partner_id !== $partnerId || $team->deleted_at !== null) {
            throw new InvalidArgumentException('Группа не найдена.');
        }

        if (! UserPriceTeamMembership::studentBelongsToTeam($user, (int) $team->id, $partnerId)) {
            throw new InvalidArgumentException('Ученик не состоит в выбранной группе.');
        }
    }

    private function resolveBindableUlp(int $partnerId, int $userId, int $ulpId): UserLessonPackage
    {
        /** @var UserLessonPackage|null $ulp */
        $ulp = $this->assignmentEligibility
            ->singleLessonAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->whereKey($ulpId)
            ->first();

        if (! $ulp) {
            throw new InvalidArgumentException('Выберите свободное назначение разового занятия.');
        }

        return $ulp;
    }

    private function resolveSingleLessonTemplate(int $partnerId, int $lessonPackageId): LessonPackage
    {
        /** @var LessonPackage|null $template */
        $template = $this->assignmentEligibility
            ->singleLessonTemplatesQuery($partnerId)
            ->whereKey($lessonPackageId)
            ->first();

        if (! $template) {
            throw new InvalidArgumentException('Шаблон разового занятия не найден или недоступен.');
        }

        return $template;
    }

    private function assertUlpStillBindable(UserLessonPackage $ulp): void
    {
        $ulp->loadMissing('lessonPackage');
        $package = $ulp->lessonPackage;
        if (! $package || (string) $package->schedule_type !== LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE) {
            throw new InvalidArgumentException('Выберите назначение с типом «разовое занятие».');
        }

        $scheduledForUlp = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', (int) $ulp->id)
            ->count();
        if ($scheduledForUlp >= (int) $ulp->lessons_total) {
            $msg = (int) $ulp->lessons_total === 1
                ? 'Для этого назначения слот в календаре уже выбран. Оформите новое разовое занятие отдельным абонементом.'
                : 'Достигнут лимит занятий в календаре для этого абонемента.';

            throw new InvalidArgumentException($msg);
        }
    }
}
