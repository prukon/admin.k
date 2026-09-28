<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\SchoolCalendarTrialEligibilityService;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use App\Support\UserPriceTeamMembership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Пробное занятие из пустой ячейки журнала /schedule (со статусом и тренером).
 */
final class JournalTrialLessonPlacementService
{
    public function __construct(
        private readonly JournalTeamScheduleSlotEnsureService $slotEnsure,
        private readonly SchoolCalendarTrialEligibilityService $trialEligibility,
        private readonly UserLessonOccurrenceStatusService $occurrenceStatusService,
    ) {
    }

    /**
     * @return array{
     *     utss_id: int,
     *     user_lesson_package_id: null,
     *     is_trial_lesson: true,
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
        ?int $createdByUserId,
        ?int $trainerProfileId = null,
        ?string $comment = null,
        ?array $trainerProfileIds = null,
    ): array {
        $this->assertUserAndTeam($partnerId, $user, $team);

        if ((int) $status->partner_id !== $partnerId || ! $status->is_active) {
            throw new InvalidArgumentException('Выбранный статус не найден или неактивен.');
        }

        $userLevel = $this->trialEligibility->evaluateUserLevel($partnerId, (int) $user->id);
        if (! $userLevel['allowed']) {
            throw new InvalidArgumentException((string) ($userLevel['reason'] ?? 'Нельзя добавить пробное занятие.'));
        }

        $occurrenceYmd = $occurrenceDate->toDateString();
        $commentValue = $comment !== null && trim($comment) !== '' ? trim($comment) : null;
        $utssId = 0;
        $resolvedTrainerIds = $trainerProfileIds ?? (
            $trainerProfileId !== null && $trainerProfileId > 0 ? [$trainerProfileId] : []
        );

        DB::transaction(function () use (
            $partnerId,
            $user,
            $team,
            $occurrenceDate,
            $occurrenceYmd,
            $createdByUserId,
            $status,
            $resolvedTrainerIds,
            $commentValue,
            &$utssId,
        ): void {
            $weekday = (int) $occurrenceDate->format('N');
            $slot = $this->slotEnsure->resolveOrCreateNextFreeForUserDate(
                $partnerId,
                (int) $team->id,
                $weekday,
                (int) $user->id,
                $occurrenceYmd,
            );
            $slotId = (int) $slot->id;

            $utss = UserTeamScheduleSlot::query()->create([
                'partner_id' => $partnerId,
                'user_id' => (int) $user->id,
                'user_lesson_package_id' => null,
                'is_trial_lesson' => true,
                'trial_lessons_remaining' => 1,
                'trial_lessons_total' => 1,
                'team_schedule_slot_id' => $slotId,
                'starts_at' => $occurrenceYmd,
                'ends_at' => $occurrenceYmd,
                'created_by' => $createdByUserId,
            ]);

            $this->occurrenceStatusService->apply(
                $partnerId,
                (int) $user->id,
                $slotId,
                $occurrenceYmd,
                null,
                $status,
                $createdByUserId,
                $resolvedTrainerIds[0] ?? null,
                $commentValue,
                $resolvedTrainerIds,
            );

            $utssId = (int) $utss->id;
        });

        return [
            'utss_id' => $utssId,
            'user_lesson_package_id' => null,
            'is_trial_lesson' => true,
            'occurrence_date' => $occurrenceYmd,
            'comment' => $commentValue,
            'package_hover' => 'Пробное',
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
}
