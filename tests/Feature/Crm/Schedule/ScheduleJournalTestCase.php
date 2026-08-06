<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

abstract class ScheduleJournalTestCase extends CrmTestCase
{
    protected ?int $visitedStatusId = null;

    protected ?int $trainerRoleId = null;

    protected function setUpScheduleJournal(): void
    {
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $this->visitedStatusId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
    }

    protected function grantScheduleView(?User $actor = null): void
    {
        $actor ??= $this->user;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function grantLessonPackagesView(?User $actor = null): void
    {
        $actor ??= $this->user;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createCustomOccurrenceStatus(string $title = 'Тестовый статус'): LessonOccurrenceStatus
    {
        return LessonOccurrenceStatus::query()->create([
            'partner_id' => $this->partner->id,
            'code' => 'custom_'.bin2hex(random_bytes(4)),
            'title' => $title,
            'icon' => 'fa-solid fa-star',
            'color' => '#eeeeee',
            'is_system' => false,
            'is_active' => true,
            'consumes_lesson' => false,
            'sort_order' => 50,
        ]);
    }

    /**
     * @return array{0: User, 1: Team, 2: TrainerProfile}
     */
    protected function makeStudentTeamAndTrainer(?string $trainerName = 'Тренер журнала'): array
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $trainer = $this->makeTrainerProfile($trainerName);

        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'trainer_profile_id' => $trainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $studentRoleId,
            'team_id' => $team->id,
        ]);

        return [$student, $team, $trainer];
    }

    protected function makeTrainerProfile(string $name, ?int $partnerId = null): TrainerProfile
    {
        $partnerId ??= $this->partner->id;

        $user = User::factory()->create([
            'partner_id' => $partnerId,
            'role_id' => $this->trainerRoleId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid() . '@example.test',
            'is_enabled' => 1,
        ]);

        return TrainerProfile::factory()->create([
            'partner_id' => $partnerId,
            'user_id' => $user->id,
            'is_enabled' => true,
        ]);
    }

    protected function makeStudent(?int $teamId = null): User
    {
        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $studentRoleId,
            'team_id' => $teamId,
        ]);
    }

    protected function makeCustomRoleUser(array $roleAttributes = [], array $userAttributes = []): User
    {
        $customRole = Role::query()->create(array_merge([
            'name' => 'custom-role-' . uniqid(),
            'label' => 'Кастомная роль',
            'is_sistem' => false,
            'is_visible' => true,
            'order_by' => 50,
        ], $roleAttributes));

        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $customRole->id,
            'is_enabled' => 1,
        ], $userAttributes));
    }

    protected function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * Единый контур журнала: событие «Посетил» с тренером для агрегатов (нагрузка тренеров и т.п.).
     * Повторные вызовы для того же ученика в тот же день недели попадают в один и тот же
     * team_schedule_slot — новое событие становится актуальным (последним) для этой даты.
     */
    protected function createVisitedScheduleEntry(int $userId, int $trainerProfileId, string $date): UserLessonOccurrenceStatusEvent
    {
        return $this->createScheduleStatusEntry($userId, (int) $this->visitedStatusId, $date, $trainerProfileId);
    }

    /**
     * Произвольное событие статуса занятия (без создания UTSS) — для сценариев,
     * где нужен просто факт "было такое событие в этот день" (нагрузка, история статусов).
     */
    protected function createScheduleStatusEntry(
        int $userId,
        int $statusId,
        string $date,
        ?int $trainerProfileId = null,
    ): UserLessonOccurrenceStatusEvent {
        $user = User::query()->findOrFail($userId);
        $slot = $this->teamScheduleSlotForUser($user, $date);

        return UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $user->partner_id,
            'user_id' => $userId,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $statusId,
            'trainer_profile_id' => $trainerProfileId,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Пробное занятие (UTSS без абонемента) для ученика в конкретной группе на дату —
     * основа для тестов schedule.update / cell-context в едином контуре журнала.
     */
    protected function createTrialUtss(User $student, Team $team, string $date): UserTeamScheduleSlot
    {
        $slot = $this->resolveOrCreateTeamScheduleSlot((int) $student->partner_id, (int) $team->id, $date);

        return UserTeamScheduleSlot::query()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => $date,
            'ends_at' => $date,
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Слот группы (team_schedule_slot) на день недели даты; создаётся при отсутствии,
     * подбирая свободное время, чтобы не нарушить unique(partner_id, weekday, time, date range).
     */
    protected function resolveOrCreateTeamScheduleSlot(int $partnerId, int $teamId, string $date): TeamScheduleSlot
    {
        $weekday = CarbonImmutable::parse($date)->isoWeekday();

        $slot = TeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('team_id', $teamId)
            ->where('weekday', $weekday)
            ->first();

        if ($slot) {
            return $slot;
        }

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $hour = 6 + random_int(0, 12);
            $minute = random_int(0, 59);

            try {
                return TeamScheduleSlot::query()->create([
                    'partner_id' => $partnerId,
                    'team_id' => $teamId,
                    'location_id' => null,
                    'weekday' => $weekday,
                    'time_start' => sprintf('%02d:%02d:00', $hour, $minute),
                    'time_end' => sprintf('%02d:%02d:00', $hour + 1, $minute),
                    'date_start' => '2020-01-01',
                    'date_end' => '9999-12-31',
                    'is_enabled' => true,
                ]);
            } catch (QueryException $e) {
                if ($attempt === 29) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Не удалось создать team_schedule_slot для теста.');
    }

    /**
     * Группа ученика для авто-создания слота: legacy team_id, иначе первая группа из pivot,
     * иначе — временная группа партнёра (для сущностей без группы, напр. тренер как user).
     */
    protected function teamScheduleSlotForUser(User $user, string $date): TeamScheduleSlot
    {
        $partnerId = (int) $user->partner_id;
        $teamId = $user->team_id ? (int) $user->team_id : null;

        if ($teamId === null) {
            $pivotTeamId = DB::table('team_user')->where('user_id', $user->id)->value('team_id');
            $teamId = $pivotTeamId ? (int) $pivotTeamId : null;
        }

        if ($teamId === null) {
            $teamId = (int) Team::factory()->create(['partner_id' => $partnerId])->id;
        }

        return $this->resolveOrCreateTeamScheduleSlot($partnerId, $teamId, $date);
    }

    protected function workloadSession(): array
    {
        return [
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ];
    }

    /**
     * @return array{0: User, 1: Team}
     */
    protected function makeStudentWithTeam(): array
    {
        [$student, $team] = array_slice($this->makeStudentTeamAndTrainer(), 0, 2);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);

        return [$student, $team];
    }

    /**
     * @param  list<int>  $weekdayIds
     */
    protected function attachWeekdays(Team $team, array $weekdayIds): void
    {
        foreach ($weekdayIds as $weekdayId) {
            DB::table('team_weekdays')->insertOrIgnore([
                'team_id' => $team->id,
                'weekday_id' => $weekdayId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function makeFixedAssignment(User $student, int $lessons = 2, int $durationDays = 14): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Журнал фикс '.uniqid(),
            'schedule_type' => 'fixed',
            'duration_days' => $durationDays,
            'lessons_count' => $lessons,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Месячный гибкий ULP из установки цен (полный период на billing_month).
     */
    protected function makeMonthlyFlexibleAssignment(
        User $student,
        int $teamId,
        string $billingMonth,
        int $lessons = 4,
    ): UserLessonPackage {
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, array_values(array_unique(array_filter([
            $teamId,
            ...$student->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all(),
        ]))));

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->flexible($lessons, 60)->create([
            'name' => 'Месячный гибкий '.uniqid(),
            'is_active' => true,
        ]);

        $monthStart = \Carbon\Carbon::parse($billingMonth)->startOfMonth()->format('Y-m-d');
        $monthEnd = \Carbon\Carbon::parse($billingMonth)->endOfMonth()->format('Y-m-d');

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $teamId,
            'billing_month' => $monthStart,
            'starts_at' => $monthStart,
            'ends_at' => $monthEnd,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount_cents' => 500000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function placeFlexiblePayload(
        UserLessonPackage $ulp,
        int $teamId,
        string $occurrenceDate,
        array $extra = [],
    ): array {
        $statusId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);
        $this->assertNotNull($statusId);

        return array_merge([
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $teamId,
            'occurrence_date' => $occurrenceDate,
            'lesson_occurrence_status_id' => $statusId,
        ], $extra);
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Пресеты быстрого выбора месяца на /schedule/trainer-workload (3 месяца: −2 … текущий).
     *
     * @return list<array{label: string, date_from: string, date_to: string}>
     */
    protected function trainerWorkloadMonthPresets(?\Carbon\Carbon $reference = null): array
    {
        $reference = ($reference ?? \Carbon\Carbon::now())->copy()->startOfDay();

        $monthNames = [
            '01' => 'Январь',
            '02' => 'Февраль',
            '03' => 'Март',
            '04' => 'Апрель',
            '05' => 'Май',
            '06' => 'Июнь',
            '07' => 'Июль',
            '08' => 'Август',
            '09' => 'Сентябрь',
            '10' => 'Октябрь',
            '11' => 'Ноябрь',
            '12' => 'Декабрь',
        ];

        $presets = [];
        for ($monthsAgo = 2; $monthsAgo >= 0; $monthsAgo--) {
            $monthStart = $reference->copy()->subMonths($monthsAgo)->startOfMonth();
            $presets[] = [
                'label' => $monthNames[$monthStart->format('m')] ?? $monthStart->format('m'),
                'date_from' => $monthStart->toDateString(),
                'date_to' => $monthStart->copy()->endOfMonth()->toDateString(),
            ];
        }

        return $presets;
    }
}
