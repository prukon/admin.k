<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\Role;
use App\Models\Status;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
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
        $this->seedGlobalScheduleStatuses();
        $this->visitedStatusId = Status::globalVisitedId();
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

    protected function seedGlobalScheduleStatuses(): void
    {
        foreach ([
            ['name' => Status::VISITED_NAME, 'sort_order' => 1, 'color' => '#dff0d8'],
            ['name' => 'Не был', 'sort_order' => 2, 'color' => '#f8d7da'],
        ] as $row) {
            Status::query()->firstOrCreate(
                [
                    'partner_id' => null,
                    'name' => $row['name'],
                    'is_system' => true,
                ],
                [
                    'icon' => 'fas fa-check',
                    'color' => $row['color'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }

    protected function createCustomScheduleStatus(string $name = 'Тестовый статус'): Status
    {
        return Status::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $name,
            'icon' => 'fas fa-star',
            'color' => '#eeeeee',
            'is_system' => false,
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
}
