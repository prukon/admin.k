<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Role;
use App\Models\ScheduleUser;
use App\Models\Status;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Тестовые записи журнала /schedule (schedule_users) для dev-стенда.
 *
 * Последние 6 месяцев, 3 занятия в неделю на ученика, случайные статусы.
 * При «Посетил» — свой / чужой / без тренера. Существующие ячейки не перезаписываются.
 */
class DevScheduleJournalSeeder extends Seeder
{
    use GuardsDevSeedData;

    private const STUDENT_SAMPLE_PERCENT = 80;

    private const LESSONS_PER_WEEK = 3;

    private const COMMENT_PROBABILITY_PERCENT = 25;

    private const CLEAR_TEAM_PROBABILITY_PERCENT = 25;

    private const ASSIGN_TEAM_PROBABILITY_PERCENT = 35;

    private const INSERT_CHUNK_SIZE = 500;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        DevScheduleStatusesSeeder::ensureGlobalSystemStatuses();

        $visitedStatusId = Status::globalVisitedId();
        if ($visitedStatusId === null) {
            $this->command?->warn('DevScheduleJournalSeeder: системный статус «Посетил» не найден, пропуск.');

            return;
        }

        $userRoleId = Role::query()->where('name', 'user')->value('id');
        if (! $userRoleId) {
            return;
        }

        $periodEnd = Carbon::today();
        $periodStart = $periodEnd->copy()->subMonths(6)->startOfDay();

        Partner::query()
            ->orderBy('id')
            ->each(function (Partner $partner) use (
                $userRoleId,
                $visitedStatusId,
                $periodStart,
                $periodEnd,
            ): void {
                $this->seedPartner(
                    (int) $partner->id,
                    (int) $userRoleId,
                    (int) $visitedStatusId,
                    $periodStart,
                    $periodEnd,
                );
            });
    }

    private function seedPartner(
        int $partnerId,
        int $userRoleId,
        int $visitedStatusId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): void {
        $statusIds = Status::query()
            ->forSchedulePartner($partnerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($statusIds === []) {
            return;
        }

        $partnerTrainerIds = TrainerProfile::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        /** @var Collection<int, Collection<int, int>> $teamTrainerIds */
        $teamTrainerIds = DB::table('team_trainer')
            ->where('partner_id', $partnerId)
            ->orderBy('id')
            ->get(['team_id', 'trainer_profile_id'])
            ->groupBy('team_id')
            ->map(fn (Collection $rows) => $rows->pluck('trainer_profile_id')->map(fn ($id) => (int) $id)->values());

        $partnerTeamIds = Team::query()
            ->where('partner_id', $partnerId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $students = User::query()
            ->where('partner_id', $partnerId)
            ->where('role_id', $userRoleId)
            ->where('is_enabled', 1)
            ->orderBy('id')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $selectedStudents = $students->filter(
            fn (User $user) => $this->isSelectedForJournal((int) $user->id, $partnerId),
        );

        if ($selectedStudents->isEmpty()) {
            return;
        }

        $selectedIds = $selectedStudents->pluck('id')->map(fn ($id) => (int) $id)->all();

        /** @var Collection<int, Collection<int, string>> $existingDatesByUser */
        $existingDatesByUser = ScheduleUser::query()
            ->whereIn('user_id', $selectedIds)
            ->whereBetween('date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->get(['user_id', 'date'])
            ->groupBy('user_id')
            ->map(function (Collection $rows) {
                return $rows->map(function ($row) {
                    $date = $row->date;

                    return $date instanceof Carbon
                        ? $date->toDateString()
                        : Carbon::parse($date)->toDateString();
                });
            });

        $weekStarts = $this->weekStartsInPeriod($periodStart, $periodEnd);
        $rowsToInsert = [];
        $now = now();

        foreach ($selectedStudents as $student) {
            $this->maybeAdjustTeam($student, $partnerTeamIds);

            $userId = (int) $student->id;
            $existingDates = $existingDatesByUser->get($userId, collect())->flip();

            foreach ($weekStarts as $weekStart) {
                $weekDays = $this->daysOfWeekInPeriod($weekStart, $periodStart, $periodEnd);
                if ($weekDays === []) {
                    continue;
                }

                $filledInWeek = 0;
                $openDays = [];

                foreach ($weekDays as $day) {
                    $dateKey = $day->toDateString();
                    if ($existingDates->has($dateKey)) {
                        $filledInWeek++;

                        continue;
                    }

                    $openDays[] = $day;
                }

                if ($filledInWeek >= self::LESSONS_PER_WEEK) {
                    continue;
                }

                $needed = self::LESSONS_PER_WEEK - $filledInWeek;
                if ($openDays === []) {
                    continue;
                }

                shuffle($openDays);
                $picked = array_slice($openDays, 0, min($needed, count($openDays)));

                foreach ($picked as $day) {
                    $dateKey = $day->toDateString();
                    $statusId = $statusIds[array_rand($statusIds)];
                    $trainerProfileId = null;

                    if ($statusId === $visitedStatusId) {
                        $trainerProfileId = $this->resolveVisitedTrainerProfileId(
                            $student,
                            $partnerTrainerIds,
                            $teamTrainerIds,
                        );
                    }

                    $rowsToInsert[] = [
                        'user_id' => $userId,
                        'date' => $dateKey,
                        'status_id' => $statusId,
                        'description' => $this->randomDescription(),
                        'trainer_profile_id' => $trainerProfileId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $existingDates->put($dateKey, true);
                }
            }
        }

        foreach (array_chunk($rowsToInsert, self::INSERT_CHUNK_SIZE) as $chunk) {
            ScheduleUser::query()->insert($chunk);
        }
    }

    private function isSelectedForJournal(int $userId, int $partnerId): bool
    {
        return (($userId * 31 + $partnerId) % 100) < self::STUDENT_SAMPLE_PERCENT;
    }

    /**
     * @param  list<int>  $partnerTeamIds
     */
    private function maybeAdjustTeam(User $user, array $partnerTeamIds): void
    {
        $sync = app(TeamUserSyncService::class);
        $teamIds = $sync->teamIdsForStudent($user);

        if ($teamIds !== []) {
            if (random_int(1, 100) <= self::CLEAR_TEAM_PROBABILITY_PERCENT) {
                $sync->detachAllTeamsForStudent($user);
            }

            return;
        }

        if ($partnerTeamIds === []) {
            return;
        }

        if (random_int(1, 100) <= self::ASSIGN_TEAM_PROBABILITY_PERCENT) {
            $sync->syncTeamsForStudent($user, [$partnerTeamIds[array_rand($partnerTeamIds)]]);
        }
    }

    /**
     * @return list<Carbon>
     */
    private function weekStartsInPeriod(Carbon $periodStart, Carbon $periodEnd): array
    {
        $cursor = $periodStart->copy()->startOfWeek(Carbon::MONDAY);
        $lastWeekStart = $periodEnd->copy()->startOfWeek(Carbon::MONDAY);
        $weeks = [];

        while ($cursor->lte($lastWeekStart)) {
            $weeks[] = $cursor->copy();
            $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * @return list<Carbon>
     */
    private function daysOfWeekInPeriod(Carbon $weekStart, Carbon $periodStart, Carbon $periodEnd): array
    {
        $days = [];
        $day = $weekStart->copy();

        for ($i = 0; $i < 7; $i++) {
            if ($day->betweenIncluded($periodStart, $periodEnd)) {
                $days[] = $day->copy();
            }
            $day->addDay();
        }

        return $days;
    }

    /**
     * @param  list<int>  $partnerTrainerIds
     * @param  Collection<int, Collection<int, int>>  $teamTrainerIds
     */
    private function resolveVisitedTrainerProfileId(
        User $student,
        array $partnerTrainerIds,
        Collection $teamTrainerIds,
    ): ?int {
        $choice = random_int(0, 2);

        $teamIds = app(TeamUserSyncService::class)->teamIdsForStudent($student);
        $teamId = $teamIds[0] ?? null;
        $ownIds = $teamId !== null
            ? ($teamTrainerIds->get($teamId)?->all() ?? [])
            : [];

        $foreignIds = $partnerTrainerIds;
        if ($ownIds !== []) {
            $ownFlip = array_flip($ownIds);
            $foreignIds = array_values(array_filter(
                $partnerTrainerIds,
                fn (int $id) => ! isset($ownFlip[$id]),
            ));
        }

        if ($choice === 0) {
            if ($ownIds !== []) {
                return $ownIds[array_rand($ownIds)];
            }

            if ($foreignIds !== []) {
                return $foreignIds[array_rand($foreignIds)];
            }

            return null;
        }

        if ($choice === 1) {
            if ($foreignIds !== []) {
                return $foreignIds[array_rand($foreignIds)];
            }

            if ($ownIds !== []) {
                return $ownIds[array_rand($ownIds)];
            }

            return null;
        }

        return null;
    }

    private function randomDescription(): ?string
    {
        if (random_int(1, 100) > self::COMMENT_PROBABILITY_PERCENT) {
            return null;
        }

        return fake()->sentence(random_int(3, 8));
    }
}
