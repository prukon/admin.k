<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Location;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LocationTeamPivotFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
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

    public function test_team_store_syncs_location_ids_when_locations_view(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $locA = Location::factory()->create(['partner_id' => $this->partner->id, 'name' => 'Зал A']);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id, 'name' => 'Зал B']);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.team.store'), [
            'title' => 'Группа с локациями',
            'is_enabled' => 1,
            'location_ids' => [$locA->id, $locB->id],
            ]);

        $response->assertOk();

        $teamId = (int) $response->json('team.id');
        $this->assertDatabaseHas('location_team', [
            'team_id' => $teamId,
            'location_id' => $locA->id,
            'partner_id' => $this->partner->id,
        ]);
        $this->assertDatabaseHas('location_team', [
            'team_id' => $teamId,
            'location_id' => $locB->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_team_update_clears_locations_when_empty_array_sent(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'G1']);

        DB::table('location_team')->insert([
            'partner_id' => $this->partner->id,
            'location_id' => $loc->id,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'is_enabled' => 1,
            'location_ids' => [],
        ])->assertOk();

        $this->assertDatabaseMissing('location_team', [
            'team_id' => $team->id,
            'location_id' => $loc->id,
        ]);
    }

    public function test_team_edit_returns_location_ids(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        DB::table('location_team')->insert([
            'partner_id' => $this->partner->id,
            'location_id' => $loc->id,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('location_ids', [$loc->id]);
    }

    public function test_location_update_syncs_team_ids(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->putJson(route('admin.locations.update', $location), [
            'name' => $location->name,
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $this->assertDatabaseHas('location_team', [
            'location_id' => $location->id,
            'team_id' => $team->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_schedule_slot_store_rejects_team_not_allowed_at_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $locA = Location::factory()->create(['partner_id' => $this->partner->id]);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        DB::table('location_team')->insert([
            'partner_id' => $this->partner->id,
            'location_id' => $locA->id,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $locB->id,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => now()->format('Y-m-d'),
            'date_end' => now()->addMonth()->format('Y-m-d'),
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_schedule_slot_store_allows_team_without_location_bindings(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $loc->id,
            'weekday' => 2,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => now()->format('Y-m-d'),
            'date_end' => now()->addMonth()->format('Y-m-d'),
            'is_enabled' => 1,
        ])->assertOk();
    }

    public function test_teams_data_filters_by_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $loc = Location::factory()->create(['partner_id' => $this->partner->id]);
        $linked = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Linked']);
        $universal = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Universal']);
        $other = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'OtherLoc']);

        DB::table('location_team')->insert([
            [
                'partner_id' => $this->partner->id,
                'location_id' => $loc->id,
                'team_id' => $linked->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'partner_id' => $this->partner->id,
                'location_id' => Location::factory()->create(['partner_id' => $this->partner->id])->id,
                'team_id' => $other->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&location_id='.$loc->id);

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Linked', $titles);
        $this->assertContains('Universal', $titles);
        $this->assertNotContains('OtherLoc', $titles);
    }
}
