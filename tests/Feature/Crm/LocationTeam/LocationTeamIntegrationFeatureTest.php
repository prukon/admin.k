<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LocationTeam;

use App\Models\Location;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Бизнес-логика pivot location_team, фильтры и удаление users.location_id.
 */
final class LocationTeamIntegrationFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachTeamToLocation(Team $team, Location $location): void
    {
        DB::table('location_team')->insert([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_users_table_has_no_location_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'location_id'));
    }

    public function test_location_store_syncs_team_ids_on_create(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $response = $this->postJson(route('admin.locations.store'), [
            'name' => 'Локация с группами',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ]);

        $response->assertOk();

        $locationId = (int) Location::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Локация с группами')
            ->value('id');

        $this->assertDatabaseHas('location_team', [
            'location_id' => $locationId,
            'team_id' => $team->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_location_show_returns_team_ids(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($team, $location);

        $this->getJson(route('admin.locations.show', $location))
            ->assertOk()
            ->assertJsonPath('team_ids', [$team->id]);
    }

    public function test_location_update_replaces_team_ids(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($teamA, $location);

        $this->putJson(route('admin.locations.update', $location), [
            'name' => $location->name,
            'is_enabled' => 1,
            'team_ids' => [$teamB->id],
        ])->assertOk();

        $this->assertDatabaseMissing('location_team', [
            'location_id' => $location->id,
            'team_id' => $teamA->id,
        ]);
        $this->assertDatabaseHas('location_team', [
            'location_id' => $location->id,
            'team_id' => $teamB->id,
        ]);
    }

    public function test_location_data_includes_teams_label_when_locations_view(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'С меткой групп',
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа для метки',
        ]);
        $this->attachTeamToLocation($team, $location);

        $json = $this->getJson(route('admin.locations.data', ['draw' => 1, 'start' => 0, 'length' => 50]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $location->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Группа для метки', (string) ($row['teams_label'] ?? ''));
        $this->assertSame('Группа для метки', $row['teams_label_full'] ?? null);
    }

    public function test_location_data_truncates_teams_label_when_more_than_two_teams(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Много групп',
        ]);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа А',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Б',
        ]);
        $teamC = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа В',
        ]);
        $this->attachTeamToLocation($teamA, $location);
        $this->attachTeamToLocation($teamB, $location);
        $this->attachTeamToLocation($teamC, $location);

        $json = $this->getJson(route('admin.locations.data', ['draw' => 1, 'start' => 0, 'length' => 50]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $location->id);
        $this->assertNotNull($row);
        $this->assertSame('Группа А, еще 2 шт.', $row['teams_label'] ?? null);
        $this->assertSame('Группа А, Группа Б, Группа В', $row['teams_label_full'] ?? null);
        $this->assertSame(['Группа А', 'Группа Б', 'Группа В'], $row['teams_titles'] ?? null);
    }

    public function test_team_store_without_locations_view_ignores_location_ids(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantGroupsViewForUser($actor);
        $this->actingAs($actor);

        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);

        $response = $this->postJson(route('admin.team.store'), [
            'title' => 'Без права локаций',
            'type' => 'group',
            'is_enabled' => 1,
            'location_ids' => [$loc->id],
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();

        $teamId = (int) $response->json('team.id');
        $this->assertDatabaseMissing('location_team', ['team_id' => $teamId]);
    }

    public function test_team_edit_without_locations_view_omits_location_ids(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantGroupsViewForUser($actor);
        $this->actingAs($actor);

        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($team, $loc);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonMissing(['location_ids']);
    }

    public function test_teams_data_location_filter_none_returns_only_universal_teams(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);
        $bound = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Привязанная']);
        $universal = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Универсальная']);
        $this->attachTeamToLocation($bound, $loc);

        $titles = collect(
            $this->getJson('/admin/teams/data?draw=1&location_id=none')
                ->assertOk()
                ->json('data')
        )->pluck('title')->all();

        $this->assertContains('Универсальная', $titles);
        $this->assertNotContains('Привязанная', $titles);
    }

    public function test_deleting_location_cascades_pivot_rows(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($team, $location);

        $this->deleteJson(route('admin.locations.destroy', $location))
            ->assertOk();

        $this->assertDatabaseMissing('location_team', [
            'location_id' => $location->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_soft_deleting_team_via_api_does_not_remove_pivot_rows(): void
    {
        $this->grantPermission('groups.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($team, $location);

        $this->deleteJson(route('admin.team.delete', ['team' => $team->id]))->assertOk();

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('location_team', [
            'location_id' => $location->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_force_deleting_team_removes_pivot_rows(): void
    {
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($team, $location);

        $team->forceDelete();

        $this->assertDatabaseMissing('location_team', [
            'location_id' => $location->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_schedule_slot_update_rejects_team_not_at_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $locA = Location::factory()->create(['partner_id' => $this->partner->id]);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $this->attachTeamToLocation($team, $locA);

        $slot = TeamScheduleSlot::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $locA->id,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => now()->format('Y-m-d'),
            'is_enabled' => 1,
        ]);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'location_id' => $locB->id,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => $slot->date_start->format('Y-m-d'),
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_debts_filter_includes_student_with_universal_team_at_location(): void
    {
        Carbon::setTestNow('2026-03-10');
        $this->grantPermission('locations.view');
        $this->grantPermission('reports.view');

        $loc = Location::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => true]);
        $universalTeam = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $universalTeam->id,
            'is_enabled' => 1,
        ]);

        DB::table('users_prices')->insert([
            'user_id' => $student->id,
            'is_paid' => 0,
            'price' => 777,
            'new_month' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('debts.getDebts', [
                'draw' => 1,
                'filter_location_id' => $loc->id,
            ]))
            ->assertOk()
            ->json();

        $userIds = collect($json['data'] ?? [])->pluck('user_id')->unique()->values()->all();
        $this->assertContains($student->id, $userIds);

        Carbon::setTestNow();
    }

    public function test_users_store_and_update_ignore_location_id_in_request(): void
    {
        $this->grantPermission('users.view');
        $this->grantPermission('locations.view');

        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $store = $this->postJson(route('admin.user.store'), [
            'name' => 'Тест',
            'lastname' => 'Локация',
            'role_id' => $roleId,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $store->assertOk();
        $userId = (int) $store->json('user.id');

        $this->patchJson(route('admin.user.update', $userId), [
            'name' => 'Тест',
            'lastname' => 'Локация',
            'location_id' => $location->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $edit = $this->getJson('/admin/users/' . $userId . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->assertArrayNotHasKey('location_id', $edit->json('user') ?? []);
        $this->assertArrayNotHasKey('location', $edit->json('user') ?? []);
    }

    private function grantGroupsViewForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
