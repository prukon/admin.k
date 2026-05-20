<?php

namespace Tests\Feature\Crm\Trainers;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TrainerSyncAndTeamsFeatureTest extends CrmTestCase
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

    private function grantPermissions(array $names): void
    {
        foreach ($names as $name) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_store_with_team_ids_links_trainer_to_groups(): void
    {
        $this->grantPermissions(['trainers.view']);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Групповой',
            'name' => 'Тренер',
            'email' => 'teams-store-' . uniqid() . '@example.test',
            'password' => 'password123',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $profileId = (int) TrainerProfile::query()
            ->where('partner_id', $this->partner->id)
            ->whereIn('user_id', function ($q) {
                $q->select('id')->from('users')->where('lastname', 'Групповой');
            })
            ->value('id');

        $this->assertGreaterThan(0, $profileId);

        $this->assertDatabaseHas('team_trainer', [
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'trainer_profile_id' => $profileId,
        ]);
    }

    public function test_show_returns_team_ids_for_linked_groups(): void
    {
        $this->grantPermissions(['trainers.view']);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'email' => 'show-teams-' . uniqid() . '@example.test',
        ]);
        $profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
        ]);

        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'trainer_profile_id' => $profile->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(route('admin.trainers.show', $profile->id))
            ->assertOk()
            ->assertJsonPath('team_ids', [$team->id]);
    }

    public function test_user_update_syncs_trainer_team_ids(): void
    {
        $this->asAdmin();
        $this->grantPermissions(['trainers.view', 'users.view', 'users.role.update']);

        $team1 = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'UT1']);
        $team2 = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'UT2']);

        $trainerUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'email' => 'user-teams-' . uniqid() . '@example.test',
            'team_id' => null,
        ]);

        TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trainerUser->id,
        ]);

        $this->patchJson(route('admin.user.update', $trainerUser->id), [
            'name' => $trainerUser->name,
            'lastname' => $trainerUser->lastname,
            'email' => $trainerUser->email,
            'role_id' => $this->trainerRoleId,
            'is_enabled' => 1,
            'team_ids' => [$team1->id, $team2->id],
        ])->assertOk();

        $profileId = (int) TrainerProfile::query()->where('user_id', $trainerUser->id)->value('id');

        $this->assertDatabaseHas('team_trainer', [
            'trainer_profile_id' => $profileId,
            'team_id' => $team1->id,
        ]);
        $this->assertDatabaseHas('team_trainer', [
            'trainer_profile_id' => $profileId,
            'team_id' => $team2->id,
        ]);
    }

    public function test_user_role_changed_from_trainer_soft_deletes_profile(): void
    {
        $this->asAdmin();
        $this->grantPermissions(['trainers.view', 'users.view', 'users.role.update']);

        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');

        $trainerUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'email' => 'role-away-' . uniqid() . '@example.test',
        ]);

        $profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trainerUser->id,
        ]);

        $this->patchJson(route('admin.user.update', $trainerUser->id), [
            'name' => $trainerUser->name,
            'lastname' => $trainerUser->lastname,
            'email' => $trainerUser->email,
            'role_id' => $adminRoleId,
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);
    }

    public function test_user_update_team_ids_ignored_without_trainers_view(): void
    {
        $this->asAdmin();

        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $adminRoleId)
            ->where('permission_id', $this->permissionId('trainers.view'))
            ->delete();

        $this->grantPermissions(['users.view', 'users.role.update']);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $trainerUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'email' => 'no-perm-teams-' . uniqid() . '@example.test',
        ]);
        $profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trainerUser->id,
        ]);

        $this->patchJson(route('admin.user.update', $trainerUser->id), [
            'name' => $trainerUser->name,
            'lastname' => $trainerUser->lastname,
            'email' => $trainerUser->email,
            'role_id' => $this->trainerRoleId,
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $this->assertDatabaseMissing('team_trainer', [
            'trainer_profile_id' => $profile->id,
            'team_id' => $team->id,
        ]);
    }
}
