<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Location;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TeamLocationFeatureTest extends CrmTestCase
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

    public function test_team_store_sets_location_id_when_locations_view(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id, 'name' => 'Зал A']);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.team.store'), [
                'title' => 'Группа с объектом',
                'is_enabled' => 1,
                'location_id' => $location->id,
            ]);

        $response->assertOk();

        $teamId = (int) $response->json('team.id');
        $this->assertDatabaseHas('teams', [
            'id' => $teamId,
            'location_id' => $location->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_team_update_clears_location_when_empty_value_sent(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'G1',
            'location_id' => $location->id,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'is_enabled' => 1,
            'location_id' => '',
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'location_id' => null,
        ]);
    }

    public function test_team_edit_returns_location_id(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('location_id', $location->id);
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

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'location_id' => $location->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_schedule_slot_store_rejects_team_not_allowed_at_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $locA = Location::factory()->create(['partner_id' => $this->partner->id]);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locA->id,
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

    public function test_schedule_slot_store_rejects_team_without_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 2,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => now()->format('Y-m-d'),
            'date_end' => now()->addMonth()->format('Y-m-d'),
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_teams_data_filters_by_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('groups.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $linked = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Linked',
            'location_id' => $location->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'WithoutLocation',
            'location_id' => null,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'OtherLoc',
            'location_id' => Location::factory()->create(['partner_id' => $this->partner->id])->id,
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&location_id='.$location->id);

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Linked', $titles);
        $this->assertNotContains('WithoutLocation', $titles);
        $this->assertNotContains('OtherLoc', $titles);
    }
}
