<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\LessonPackages\SchoolCalendarAssignmentEligibilityService;
use App\Services\LessonPackages\SchoolCalendarTrialEligibilityService;
use App\Services\TeamUserSyncService;
use App\Support\Money;

/**
 * Контекст модалки «пробное / разовое» на пустой ячейке журнала /schedule.
 */
final class JournalEmptyCellPlacementContextService
{
    public function __construct(
        private readonly SchoolCalendarTrialEligibilityService $trialEligibility,
        private readonly SchoolCalendarAssignmentEligibilityService $assignmentEligibility,
        private readonly TeamUserSyncService $teamUserSync,
        private readonly ScheduleJournalMonthService $journalMonthService,
    ) {
    }

    /**
     * @return array{
     *     success: true,
     *     user: array{id: int, name: string},
     *     occurrence_date: string,
     *     teams: list<array{id: int, title: string}>,
     *     team_id: int|null,
     *     team_locked: bool,
     *     trial: array{allowed: bool, reason: string|null, label: string},
     *     single_options: list<array{
     *         key: string,
     *         mode: 'bind_existing'|'create_new',
     *         label: string,
     *         user_lesson_package_id: int|null,
     *         lesson_package_id: int|null,
     *         fee_amount: float,
     *         fee_amount_label: string
     *     }>,
     *     single_blocked_reason: string|null,
     *     flexible_options: list<array{
     *         key: string,
     *         mode: 'flexible',
     *         label: string,
     *         user_lesson_package_id: int,
     *         team_id: int,
     *         slots_remaining: int,
     *         lessons_total: int,
     *         allowed: bool,
     *         reason: string|null
     *     }>,
     *     visited_status_id: int|null,
     *     scheduled_status_id: int|null,
     *     team_default_trainer_profile_id: int|null,
     *     trainers: list<array{id: int, name: string}>
     * }
     */
    public function build(
        int $partnerId,
        User $user,
        string $occurrenceDateYmd,
        ?int $filterTeamId,
    ): array {
        $teamsPayload = $this->teamsForStudent($partnerId, $user);
        $teamLocked = $filterTeamId !== null && $filterTeamId > 0;
        $resolvedTeamId = null;

        if ($teamLocked) {
            foreach ($teamsPayload as $team) {
                if ((int) $team['id'] === $filterTeamId) {
                    $resolvedTeamId = $filterTeamId;
                    break;
                }
            }
        } elseif (count($teamsPayload) === 1) {
            $resolvedTeamId = (int) $teamsPayload[0]['id'];
        }

        $trial = $this->trialEligibility->evaluateUserLevel($partnerId, (int) $user->id);

        $singleOptions = $this->buildSingleOptions($partnerId, (int) $user->id);
        $singleBlockedReason = null;
        if ($singleOptions === []) {
            $hasTemplates = $this->assignmentEligibility->hasAnySingleLessonTemplate($partnerId);
            $singleBlockedReason = $hasTemplates
                ? 'Нет доступных вариантов разового занятия.'
                : 'Нет шаблонов разового занятия. Создайте абонемент с типом «Разовое занятие».';
        }

        $billingMonth = \Carbon\Carbon::parse($occurrenceDateYmd)->startOfMonth()->format('Y-m-d');
        $teamFilter = $filterTeamId !== null && $filterTeamId > 0 ? $filterTeamId : 'all';
        $flexibleByUser = $this->journalMonthService->flexibleAssignableByUserForBillingMonth(
            $partnerId,
            [(int) $user->id],
            $billingMonth,
            $teamFilter,
        );
        $flexibleRows = $flexibleByUser[(int) $user->id] ?? [];
        $flexibleOptions = $this->buildFlexibleOptions($flexibleRows, $occurrenceDateYmd);

        $visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner($partnerId);
        $scheduledStatusId = LessonOccurrenceStatus::scheduledIdForPartner($partnerId);
        $teamDefault = $this->teamDefaultTrainerProfile($partnerId, $resolvedTeamId);
        $trainers = $this->trainerOptionsForPartner($partnerId);

        return [
            'success' => true,
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) ($user->full_name ?: $user->name),
            ],
            'occurrence_date' => $occurrenceDateYmd,
            'teams' => $teamsPayload,
            'team_id' => $resolvedTeamId,
            'team_locked' => $teamLocked && $resolvedTeamId !== null,
            'trial' => [
                'allowed' => (bool) $trial['allowed'],
                'reason' => $trial['reason'],
                'label' => 'Пробное (бесплатное)',
            ],
            'single_options' => $singleOptions,
            'single_blocked_reason' => $singleBlockedReason,
            'flexible_options' => $flexibleOptions,
            'visited_status_id' => $visitedStatusId,
            'scheduled_status_id' => $scheduledStatusId,
            'team_default_trainer_profile_id' => $teamDefault?->id,
            'trainers' => $trainers,
        ];
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function teamsForStudent(int $partnerId, User $user): array
    {
        $teamIds = $this->teamUserSync->teamIdsForStudent($user);
        if ($teamIds === []) {
            return [];
        }

        return Team::query()
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->whereIn('id', $teamIds)
            ->orderBy('order_by')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(static fn (Team $team): array => [
                'id' => (int) $team->id,
                'title' => (string) ($team->title !== '' ? $team->title : 'Группа #'.$team->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $flexibleRows
     * @return list<array{
     *     key: string,
     *     mode: 'flexible',
     *     label: string,
     *     user_lesson_package_id: int,
     *     team_id: int,
     *     slots_remaining: int,
     *     lessons_total: int,
     *     allowed: bool,
     *     reason: string|null
     * }>
     */
    private function buildFlexibleOptions(array $flexibleRows, string $occurrenceDateYmd): array
    {
        $options = [];
        foreach ($flexibleRows as $row) {
            $start = (string) ($row['starts_at'] ?? '');
            $end = (string) ($row['ends_at'] ?? '');
            if ($start === '' || $end === '') {
                continue;
            }
            if ($occurrenceDateYmd < $start || $occurrenceDateYmd > $end) {
                continue;
            }

            $remaining = max(0, (int) ($row['slots_remaining'] ?? 0));
            $total = max(0, (int) ($row['lessons_total'] ?? 0));
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Гибкий абонемент';
            }
            $ulpId = (int) ($row['id'] ?? 0);
            $teamId = (int) ($row['team_id'] ?? 0);
            if ($ulpId < 1 || $teamId < 1) {
                continue;
            }

            $allowed = $remaining > 0;
            $options[] = [
                'key' => 'flexible:'.$ulpId,
                'mode' => 'flexible',
                'label' => 'Гибкий: «'.$name.'» — '.$remaining.'/'.$total,
                'user_lesson_package_id' => $ulpId,
                'team_id' => $teamId,
                'slots_remaining' => $remaining,
                'lessons_total' => $total,
                'allowed' => $allowed,
                'reason' => $allowed
                    ? null
                    : 'Достигнут лимит занятий по гибкому абонементу.',
            ];
        }

        return $options;
    }

    /**
     * @return list<array{
     *     key: string,
     *     mode: 'bind_existing'|'create_new',
     *     label: string,
     *     user_lesson_package_id: int|null,
     *     lesson_package_id: int|null,
     *     fee_amount: float,
     *     fee_amount_label: string
     * }>
     */
    private function buildSingleOptions(int $partnerId, int $userId): array
    {
        $options = [];

        /** @var \Illuminate\Support\Collection<int, UserLessonPackage> $existingRows */
        $existingRows = $this->assignmentEligibility
            ->singleLessonAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->get();

        foreach ($existingRows as $ulp) {
            $cents = (int) ($ulp->fee_amount_cents ?? 0);
            $feeLabel = Money::formatRub($cents, ' руб');
            $name = (string) ($ulp->lessonPackage?->name ?? 'Разовое занятие');
            $options[] = [
                'key' => 'bind:'.$ulp->id,
                'mode' => 'bind_existing',
                'label' => 'Разовое: «'.$name.'» — '.$feeLabel,
                'user_lesson_package_id' => (int) $ulp->id,
                'lesson_package_id' => null,
                'fee_amount' => (float) Money::fromCents($cents),
                'fee_amount_label' => $feeLabel,
            ];
        }

        $templateRows = $this->assignmentEligibility
            ->singleLessonTemplatesQuery($partnerId)
            ->get(['id', 'name', 'price_cents']);

        foreach ($templateRows as $pkg) {
            $cents = (int) $pkg->price_cents;
            $feeLabel = Money::formatRub($cents, ' руб');
            $name = (string) ($pkg->name !== '' ? $pkg->name : 'Разовое занятие');
            $options[] = [
                'key' => 'create:'.$pkg->id,
                'mode' => 'create_new',
                'label' => 'Разовое: «'.$name.'» — '.$feeLabel,
                'user_lesson_package_id' => null,
                'lesson_package_id' => (int) $pkg->id,
                'fee_amount' => (float) Money::fromCents($cents),
                'fee_amount_label' => $feeLabel,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function trainerOptionsForPartner(int $partnerId): array
    {
        return TrainerProfile::query()
            ->with('user')
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (TrainerProfile $profile): array {
                $name = trim((string) ($profile->user?->full_name ?? ''));

                return [
                    'id' => (int) $profile->id,
                    'name' => $name !== '' ? $name : 'Без тренера',
                ];
            })
            ->values()
            ->all();
    }

    private function teamDefaultTrainerProfile(int $partnerId, ?int $teamId): ?TrainerProfile
    {
        if (! $teamId) {
            return null;
        }

        return TrainerProfile::query()
            ->with('user')
            ->select('trainer_profiles.*')
            ->join('team_trainer', 'team_trainer.trainer_profile_id', '=', 'trainer_profiles.id')
            ->where('team_trainer.partner_id', $partnerId)
            ->where('team_trainer.team_id', $teamId)
            ->orderBy('team_trainer.id')
            ->first();
    }
}
