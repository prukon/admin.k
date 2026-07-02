<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonOccurrenceStatus;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserTeamScheduleSlot;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\TeamScheduleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final class AutoMarkYesterdayLessonOccurrencesService
{
    /** @var list<string> */
    private const ELIGIBLE_SCHEDULE_TYPES = ['fixed', 'flexible'];

    public function __construct(
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{processed: int, skipped: int, failed: int}
     */
    public function processForDate(CarbonImmutable $occurrenceDate): array
    {
        $dateYmd = $occurrenceDate->toDateString();
        $stats = ['processed' => 0, 'skipped' => 0, 'failed' => 0];

        UserTeamScheduleSlot::query()
            ->with([
                'user:id,name,lastname',
                'slot',
                'userLessonPackage.lessonPackage:id,auto_attendance_enabled,schedule_type',
            ])
            ->whereDate('starts_at', $dateYmd)
            ->where('is_trial_lesson', false)
            ->whereNotNull('user_lesson_package_id')
            ->whereHas('userLessonPackage.lessonPackage', function ($q): void {
                $q->where('auto_attendance_enabled', true)
                    ->whereIn('schedule_type', self::ELIGIBLE_SCHEDULE_TYPES);
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dateYmd, &$stats): void {
                /** @var UserTeamScheduleSlot $utss */
                foreach ($rows as $utss) {
                    $this->processRow($utss, $dateYmd, $stats);
                }
            });

        Log::channel('queue')->info('AutoMarkYesterdayLessonOccurrencesService: finished', [
            'occurrence_date' => $dateYmd,
            'processed' => $stats['processed'],
            'skipped' => $stats['skipped'],
            'failed' => $stats['failed'],
        ]);

        return $stats;
    }

    /**
     * @param array{processed: int, skipped: int, failed: int} $stats
     */
    private function processRow(UserTeamScheduleSlot $utss, string $dateYmd, array &$stats): void
    {
        if (! $this->isEligible($utss, $dateYmd)) {
            $stats['skipped']++;

            return;
        }

        $partnerId = (int) $utss->partner_id;
        $ulpId = (int) $utss->user_lesson_package_id;

        $status = LessonOccurrenceStatus::query()
            ->where('partner_id', $partnerId)
            ->where('code', 'attended')
            ->where('is_active', true)
            ->first();

        if ($status === null) {
            Log::channel('queue')->warning('AutoMarkYesterdayLessonOccurrencesService: attended status missing', [
                'partner_id' => $partnerId,
                'user_team_schedule_slot_id' => (int) $utss->id,
            ]);
            $stats['failed']++;

            return;
        }

        try {
            $event = $this->occurrenceStatusService->apply(
                $partnerId,
                (int) $utss->user_id,
                (int) $utss->team_schedule_slot_id,
                $dateYmd,
                $ulpId,
                $status,
                null,
            );
        } catch (\DomainException $e) {
            Log::channel('queue')->warning('AutoMarkYesterdayLessonOccurrencesService: row skipped', [
                'user_team_schedule_slot_id' => (int) $utss->id,
                'occurrence_date' => $dateYmd,
                'reason' => $e->getMessage(),
            ]);
            $stats['failed']++;

            return;
        } catch (\Throwable $e) {
            Log::channel('queue')->error('AutoMarkYesterdayLessonOccurrencesService: row failed', [
                'user_team_schedule_slot_id' => (int) $utss->id,
                'occurrence_date' => $dateYmd,
                'error' => $e->getMessage(),
            ]);
            $stats['failed']++;

            return;
        }

        $user = $utss->user;
        $userLabel = $user instanceof User
            ? trim((string) ($user->lastname ?? '').' '.(string) ($user->name ?? ''))
            : '';
        if ($userLabel === '') {
            $userLabel = 'Ученик #'.(int) $utss->user_id;
        }

        $this->auditLogger->record(
            AuditEvent::ScheduleOccurrenceStatusAutoSet,
            AuditContext::make(
                'Автосписание: '.$status->title
                    .'; ученик: '.$userLabel
                    .'; дата: '.$dateYmd
                    .'; назначение #'.$ulpId
            )
                ->withPartnerId($partnerId)
                ->withUserId((int) $utss->user_id)
        );

        $stats['processed']++;

        unset($event);
    }

    public function isEligible(UserTeamScheduleSlot $utss, string $occurrenceDateYmd): bool
    {
        if ($utss->is_trial_lesson || $utss->user_lesson_package_id === null) {
            return false;
        }

        $package = $utss->userLessonPackage?->lessonPackage;
        if ($package === null
            || ! $package->auto_attendance_enabled
            || ! in_array((string) $package->schedule_type, self::ELIGIBLE_SCHEDULE_TYPES, true)) {
            return false;
        }

        if (! $this->datesMatch($utss, $occurrenceDateYmd)) {
            return false;
        }

        $slot = $utss->slot;
        if ($slot === null) {
            return false;
        }

        $day = CarbonImmutable::parse($occurrenceDateYmd, 'Europe/Moscow')->startOfDay();

        if (! $this->calendarService->slotActiveOnDate($slot, $day)) {
            return false;
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $day)) {
            return false;
        }

        return ! UserLessonOccurrenceStatusEvent::query()
            ->where('partner_id', (int) $utss->partner_id)
            ->where('user_id', (int) $utss->user_id)
            ->where('team_schedule_slot_id', (int) $utss->team_schedule_slot_id)
            ->whereDate('occurrence_date', $occurrenceDateYmd)
            ->where('user_lesson_package_id', (int) $utss->user_lesson_package_id)
            ->exists();
    }

    private function datesMatch(UserTeamScheduleSlot $utss, string $occurrenceDateYmd): bool
    {
        $startsAt = $utss->starts_at;

        if ($startsAt instanceof \Carbon\CarbonInterface) {
            return $startsAt->toDateString() === $occurrenceDateYmd;
        }

        return (string) $startsAt === $occurrenceDateYmd;
    }
}
