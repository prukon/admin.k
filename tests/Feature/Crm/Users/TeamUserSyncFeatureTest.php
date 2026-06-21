<?php

namespace Tests\Feature\Crm\Users;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TeamUserSyncFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_factory_legacy_team_id_syncs_to_pivot_on_create(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $studentRoleId = Role::where('name', 'user')->value('id');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $studentRoleId,
            'team_id'    => $team->id,
        ]);

        $this->assertDatabaseHas('team_user', [
            'user_id'    => $user->id,
            'team_id'    => $team->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_store_syncs_multiple_teams_to_pivot_without_touching_legacy_team_id(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Alpha']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Beta']);

        $email = 'multi-team-' . uniqid('', true) . '@example.com';

        $this->postJson('/admin/users', [
            'name'       => 'Мульти',
            'lastname'   => 'Группа',
            'email'      => $email,
            'role_id'    => $role->id,
            'team_ids'   => [$teamB->id, $teamA->id],
            'birthday'   => '2015-01-01',
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertNull($user->team_id);

        $pivotTeamIds = DB::table('team_user')
            ->where('user_id', $user->id)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$teamA->id, $teamB->id], $pivotTeamIds);
    }

    public function test_update_syncs_teams_and_writes_groups_audit_log(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $oldTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Старая',
        ]);
        $newTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Новая',
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
            'team_id'    => $oldTeam->id,
            'name'       => 'Ученик',
            'lastname'   => 'Тест',
        ]);

        $this->patchJson('/admin/users/' . $user->id, [
            'name'     => $user->name,
            'lastname' => $user->lastname,
            'team_ids' => [$newTeam->id],
        ])->assertOk();

        $user->refresh();

        $this->assertSame($oldTeam->id, (int) $user->team_id, 'Legacy users.team_id must remain frozen');
        $this->assertDatabaseHas('team_user', [
            'user_id' => $user->id,
            'team_id' => $newTeam->id,
        ]);
        $this->assertDatabaseMissing('team_user', [
            'user_id' => $user->id,
            'team_id' => $oldTeam->id,
        ]);

        $log = MyLog::query()
            ->where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('event', AuditEvent::UserUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Группы: Старая → Новая', $log->description);
    }

    public function test_edit_returns_team_ids_from_pivot(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
            'team_id'    => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamB->id,
            'user_id'    => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/admin/users/' . $user->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $response->assertOk();

        $teamIds = collect($response->json('user.team_ids'))
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$teamA->id, $teamB->id])->sort()->values()->all(),
            $teamIds
        );
    }

    public function test_data_filter_includes_user_when_belongs_to_selected_team(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $role = Role::where('name', 'user')->firstOrFail();

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
            'team_id'    => null,
        ]);

        DB::table('team_user')->insert([
            [
                'partner_id' => $this->partner->id,
                'team_id'    => $teamA->id,
                'user_id'    => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'partner_id' => $this->partner->id,
                'team_id'    => $teamB->id,
                'user_id'    => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([$teamA->id, $teamB->id] as $filterTeamId) {
            $json = $this->getJson('/admin/users/data?team_id=' . $filterTeamId)->json();
            $ids = collect($json['data'])->pluck('id')->all();
            $this->assertContains($user->id, $ids, 'User must appear when filtering by team ' . $filterTeamId);
        }
    }

    public function test_clearing_team_ids_detaches_student_from_all_groups(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
            'team_id'    => $team->id,
        ]);

        $this->patchJson('/admin/users/' . $user->id, [
            'name'     => $user->name,
            'lastname' => $user->lastname,
            'team_ids' => [],
        ])->assertOk();

        $this->assertSame(0, DB::table('team_user')->where('user_id', $user->id)->count());
    }
}
