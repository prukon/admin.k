<?php

namespace Tests\Feature\Crm\Trainers;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TeamTrainerLinkFeatureTest extends CrmTestCase
{
    private ?int $trainerRoleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_data_endpoint_returns_trainer_label_for_linked_trainer(): void
    {
        $this->grantPermission('trainers.view');
        $this->grantPermission('groups.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа с тренером']);
        $profile = $this->makeTrainerProfile('Иван');

        $this->patchJson("/admin/team/{$team->id}", [
            'title' => $team->title,
            'is_enabled' => 1,
            'trainer_profile_id' => $profile->id,
        ])->assertOk();

        $json = $this->get('/admin/teams/data')->assertOk()->json();
        $row = collect($json['data'])->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('trainer_label', $row);
        $this->assertStringContainsString('Иван', (string) $row['trainer_label']);
    }

    public function test_team_update_assigns_single_trainer(): void
    {
        $this->grantPermission('trainers.view');
        $this->grantPermission('groups.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа А']);
        $profile = $this->makeTrainerProfile('Тренер А');

        $this->patchJson("/admin/team/{$team->id}", [
            'title' => $team->title,
            'is_enabled' => 1,
            'trainer_profile_id' => $profile->id,
        ])->assertOk();

        $this->assertDatabaseHas('team_trainer', [
            'team_id' => $team->id,
            'trainer_profile_id' => $profile->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_assigning_new_trainer_replaces_previous_on_team(): void
    {
        $this->grantPermission('trainers.view');
        $this->grantPermission('groups.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $trainerA = $this->makeTrainerProfile('Тренер A');
        $trainerB = $this->makeTrainerProfile('Тренер B');

        $this->patchJson("/admin/team/{$team->id}", [
            'title' => $team->title,
            'is_enabled' => 1,
            'trainer_profile_id' => $trainerA->id,
        ])->assertOk();

        $this->patchJson("/admin/team/{$team->id}", [
            'title' => $team->title,
            'is_enabled' => 1,
            'trainer_profile_id' => $trainerB->id,
        ])->assertOk();

        $this->assertDatabaseMissing('team_trainer', [
            'team_id' => $team->id,
            'trainer_profile_id' => $trainerA->id,
        ]);
        $this->assertDatabaseHas('team_trainer', [
            'team_id' => $team->id,
            'trainer_profile_id' => $trainerB->id,
        ]);
    }

    public function test_trainer_update_syncs_teams(): void
    {
        $this->grantPermission('trainers.view');

        $team1 = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'G1']);
        $team2 = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'G2']);
        $profile = $this->makeTrainerProfile('Тренер Sync');

        $this->putJson(route('admin.trainers.update', $profile->id), [
            'lastname' => 'Sync',
            'name' => 'Тренер',
            'email' => $profile->user->email,
            'is_enabled' => 1,
            'team_ids' => [$team1->id, $team2->id],
        ])->assertOk();

        $this->assertDatabaseHas('team_trainer', [
            'trainer_profile_id' => $profile->id,
            'team_id' => $team1->id,
        ]);
        $this->assertDatabaseHas('team_trainer', [
            'trainer_profile_id' => $profile->id,
            'team_id' => $team2->id,
        ]);
    }

    private function makeTrainerProfile(string $name): TrainerProfile
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid() . '@example.test',
        ]);

        return TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
        ]);
    }
}
