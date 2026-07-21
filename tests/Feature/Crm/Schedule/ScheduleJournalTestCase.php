<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
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

    protected function createVisitedScheduleEntry(int $userId, int $trainerProfileId, string $date): void
    {
        \App\Models\ScheduleUser::query()->create([
            'user_id' => $userId,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainerProfileId,
        ]);
    }

    protected function workloadSession(): array
    {
        return [
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
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
