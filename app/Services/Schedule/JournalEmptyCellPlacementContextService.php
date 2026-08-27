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
use App\Services\Pricing\UserPercentDiscount;
use Illuminate\Support\Facades\DB;

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
     *         fee_amount_label: string,
     *         discount_percent: int|null,
     *         discount_comment: string|null
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
     *     team_default_trainer_profile_ids: list<int>,
     *     team_default_trainer_profile_id: int|null,
     *     team_default_trainer_profile_ids_by_team: array<string, list<int>>,
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
        $teamIds = array_map(static fn (array $team): int => (int) $team['id'], $teamsPayload);
        $defaultsByTeam = $this->teamDefaultTrainerProfileIdsByTeam($partnerId, $teamIds);
        $teamDefaultIds = $resolvedTeamId !== null
            ? ($defaultsByTeam[(string) $resolvedTeamId] ?? [])
            : [];
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
            'team_default_trainer_profile_ids' => $teamDefaultIds,
            'team_default_trainer_profile_id' => $teamDefaultIds[0] ?? null,
            'team_default_trainer_profile_ids_by_team' => $defaultsByTeam,
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
     *     fee_amount_label: string,
     *     discount_percent: int|null,
     *     discount_comment: string|null
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
                'discount_percent' => $ulp->discount_percent !== null ? (int) $ulp->discount_percent : null,
                'discount_comment' => $ulp->discount_comment ? (string) $ulp->discount_comment : null,
            ];
        }

        $templateRows = $this->assignmentEligibility
            ->singleLessonTemplatesQuery($partnerId)
            ->get(['id', 'name', 'price_cents']);

        $user = User::query()->find($userId);

        foreach ($templateRows as $pkg) {
            $catalogCents = (int) $pkg->price_cents;
            $cents = UserPercentDiscount::payableCentsForUser($catalogCents, $user);
            $feeLabel = Money::formatRub($cents, ' руб');
            $name = (string) ($pkg->name !== '' ? $pkg->name : 'Разовое занятие');
            $snap = UserPercentDiscount::snapshotFromUser($user);
            $options[] = [
                'key' => 'create:'.$pkg->id,
                'mode' => 'create_new',
                'label' => 'Разовое: «'.$name.'» — '.$feeLabel,
                'user_lesson_package_id' => null,
                'lesson_package_id' => (int) $pkg->id,
                'fee_amount' => (float) Money::fromCents($cents),
                'fee_amount_label' => $feeLabel,
                'discount_percent' => $snap['discount_percent'],
                'discount_comment' => $snap['discount_comment'],
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

    /**
     * @param  list<int>  $teamIds
     * @return array<string, list<int>>
     */
    private function teamDefaultTrainerProfileIdsByTeam(int $partnerId, array $teamIds): array
    {
        $map = [];
        foreach ($teamIds as $teamId) {
            if ($teamId > 0) {
                $map[(string) $teamId] = [];
            }
        }

        if ($map === []) {
            return [];
        }

        $rows = DB::table('team_trainer')
            ->where('partner_id', $partnerId)
            ->whereIn('team_id', array_map('intval', array_keys($map)))
            ->orderBy('id')
            ->get(['team_id', 'trainer_profile_id']);

        foreach ($rows as $row) {
            $key = (string) ((int) $row->team_id);
            if (! array_key_exists($key, $map)) {
                continue;
            }
            $map[$key][] = (int) $row->trainer_profile_id;
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    private function teamDefaultTrainerProfileIds(int $partnerId, ?int $teamId): array
    {
        if (! $teamId) {
            return [];
        }

        return $this->teamDefaultTrainerProfileIdsByTeam($partnerId, [$teamId])[(string) $teamId] ?? [];
    }

    private function teamDefaultTrainerProfile(int $partnerId, ?int $teamId): ?TrainerProfile
    {
        $ids = $this->teamDefaultTrainerProfileIds($partnerId, $teamId);
        if ($ids === []) {
            return null;
        }

        return TrainerProfile::query()
            ->with('user')
            ->whereKey($ids[0])
            ->first();
    }
}
