<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\District;
use App\Models\Location;
use App\Models\Team;
use App\Services\PartnerWidgetService;
use App\Services\TeamLocationSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Бизнес-логика teams.location_id: привязка группы к одному объекту, лендинг, расписание, фильтры.
 */
final class TeamLocationBindingFeatureTest extends CrmTestCase
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

    private function grantGroupsAndLocationsView(): void
    {
        $this->grantPermission('groups.view');
        $this->grantPermission('locations.view');
    }

    public function test_schema_has_no_legacy_location_team_table_and_teams_address_column(): void
    {
        $this->assertFalse(Schema::hasTable('location_team'));
        $this->assertFalse(Schema::hasColumn('teams', 'address'));
        $this->assertFalse(Schema::hasColumn('teams', 'training_base'));
        $this->assertTrue(Schema::hasColumn('teams', 'location_id'));
        $this->assertNull(
            DB::table('permissions')->where('name', 'groups.training_base.view')->value('id')
        );
        $this->assertNull(
            DB::table('permissions')->where('name', 'groups.address.view')->value('id')
        );
    }

    public function test_team_can_be_created_without_location_id(): void
    {
        $this->grantGroupsAndLocationsView();

        $response = $this->postJson(route('admin.team.store'), [
            'title' => 'Группа без объекта',
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => (int) $response->json('team.id'),
            'title' => 'Группа без объекта',
            'location_id' => null,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_team_store_rejects_foreign_partner_location_id(): void
    {
        $this->grantGroupsAndLocationsView();

        $foreignLocation = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Чужой объект',
            'is_enabled' => 1,
            'location_id' => $foreignLocation->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_team_update_rejects_foreign_partner_location_id(): void
    {
        $this->grantGroupsAndLocationsView();

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $foreignLocation = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'is_enabled' => 1,
            'location_id' => $foreignLocation->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_team_update_changes_location_id_to_another_location(): void
    {
        $this->grantGroupsAndLocationsView();

        $locA = Location::factory()->create(['partner_id' => $this->partner->id]);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locA->id,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'is_enabled' => 1,
            'location_id' => $locB->id,
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'location_id' => $locB->id,
        ]);
    }

    public function test_location_sync_clears_location_id_when_team_removed_from_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->putJson(route('admin.locations.update', $location), [
            'name' => $location->name,
            'is_enabled' => 1,
            'team_ids' => [],
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'location_id' => null,
        ]);
    }

    public function test_assigning_team_to_new_location_moves_it_from_previous_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $locA = Location::factory()->create(['partner_id' => $this->partner->id]);
        $locB = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $locA->id,
        ]);

        $this->putJson(route('admin.locations.update', $locB), [
            'name' => $locB->name,
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'location_id' => $locB->id,
        ]);
    }

    public function test_teams_data_location_filter_none_returns_teams_without_object(): void
    {
        $this->grantGroupsAndLocationsView();

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'С объектом',
            'location_id' => $location->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Без объекта',
            'location_id' => null,
        ]);

        $titles = collect(
            $this->getJson('/admin/teams/data?draw=1&location_id=none')
                ->assertOk()
                ->json('data')
        )->pluck('title')->all();

        $this->assertContains('Без объекта', $titles);
        $this->assertNotContains('С объектом', $titles);
    }

    public function test_teams_data_includes_address_and_district_from_location(): void
    {
        $this->grantGroupsAndLocationsView();

        $district = \App\Models\District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Центральный',
        ]);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
            'address' => 'ул. Тестовая, 5',
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Data с адресом',
            'location_id' => $location->id,
        ]);

        $row = collect(
            $this->getJson('/admin/teams/data?draw=1&start=0&length=50')
                ->assertOk()
                ->json('data')
        )->firstWhere('title', 'Data с адресом');

        $this->assertNotNull($row);
        $this->assertSame('ул. Тестовая, 5', $row['address']);
        $this->assertSame('Центральный', $row['district_name']);
        $this->assertSame($location->name, $row['locations_label']);
    }

    public function test_teams_data_sorts_by_locations_label(): void
    {
        $this->grantGroupsAndLocationsView();

        $locZ = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Я объект',
        ]);
        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'А объект',
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Team Z',
            'location_id' => $locZ->id,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Team A',
            'location_id' => $locA->id,
        ]);

        $json = $this->getJson(
            '/admin/teams/data?draw=1&start=0&length=50'
            . '&order[0][column]=0&order[0][dir]=asc'
            . '&columns[0][name]=locations_label'
        )->assertOk()->json();

        $labels = collect($json['data'] ?? [])
            ->pluck('locations_label')
            ->filter(fn ($label) => $label !== '')
            ->values()
            ->all();

        $this->assertSame(['А объект', 'Я объект'], $labels);
    }

    public function test_location_destroy_nulls_team_location_id(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->deleteJson(route('admin.locations.destroy', $location))->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'location_id' => null,
        ]);
    }

    public function test_schedule_slot_store_succeeds_when_team_location_matches(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => now()->format('Y-m-d'),
            'date_end' => now()->addMonth()->format('Y-m-d'),
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('team_schedule_slots', [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'partner_id' => $this->partner->id,
        ]);
    }

    public function test_landing_teams_excludes_team_without_location_id(): void
    {
        [$location, $teamWithObject, $teamWithoutObject] = $this->landingFixtures();

        $this->getJson(route('lead.teams', [
            'landingSlug' => 'binding-test',
            'location_id' => $location->id,
        ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $teamWithObject->id, 'title' => $teamWithObject->title])
            ->assertJsonMissing(['id' => $teamWithoutObject->id]);
    }

    public function test_landing_teams_excludes_team_with_different_location_id(): void
    {
        [$location, $teamWithObject] = $this->landingFixtures();

        $otherLocation = Location::query()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $location->district_id,
            'name' => 'Другой объект',
            'is_enabled' => true,
        ]);

        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Чужой объект команда',
            'is_enabled' => true,
            'location_id' => $otherLocation->id,
        ]);

        $payload = $this->getJson(route('lead.teams', [
            'landingSlug' => 'binding-test',
            'location_id' => $location->id,
        ]))->assertOk()->json('data');

        $ids = collect($payload)->pluck('id')->all();

        $this->assertContains($teamWithObject->id, $ids);
        $this->assertNotContains($foreignTeam->id, $ids);
    }

    public function test_landing_team_info_address_comes_from_location(): void
    {
        [$location, $teamWithObject] = $this->landingFixtures();

        $location->update(['address' => 'ул. Объектная, 10']);

        $response = $this->getJson(route('lead.team-info', [
            'landingSlug' => 'binding-test',
            'location_id' => $location->id,
            'team_id' => $teamWithObject->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.rows.0.label', 'Адрес')
            ->assertJsonPath('data.rows.0.value', 'ул. Объектная, 10');

        $labels = collect($response->json('data.rows'))->pluck('label')->all();
        $this->assertNotContains('Тренировочная база', $labels);
    }

    public function test_landing_team_info_returns_404_for_team_without_matching_location(): void
    {
        [$location] = $this->landingFixtures();

        $teamWithoutObject = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Без объекта для info',
            'is_enabled' => true,
            'location_id' => null,
        ]);

        $this->getJson(route('lead.team-info', [
            'landingSlug' => 'binding-test',
            'location_id' => $location->id,
            'team_id' => $teamWithoutObject->id,
        ]))->assertNotFound();
    }

    public function test_team_location_sync_service_assigns_and_clears_via_location_form(): void
    {
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        app(TeamLocationSyncService::class)->syncTeamsForLocation($location, [$teamA->id]);

        $this->assertDatabaseHas('teams', [
            'id' => $teamA->id,
            'location_id' => $location->id,
        ]);
        $this->assertDatabaseHas('teams', [
            'id' => $teamB->id,
            'location_id' => null,
        ]);
    }

    /**
     * @return array{0: Location, 1: Team, 2: Team}
     */
    private function landingFixtures(): array
    {
        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'binding-test', 'is_landing_active' => true]);

        $district = District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Тестовый район',
        ]);

        $location = Location::query()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
            'name' => 'Объект лендинга',
            'is_enabled' => true,
        ]);

        $teamWithObject = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа с объектом',
            'is_enabled' => true,
        ]);

        $teamWithoutObject = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без объекта',
            'is_enabled' => true,
            'location_id' => null,
        ]);

        app(TeamLocationSyncService::class)->syncTeamsForLocation(
            $location,
            [(int) $teamWithObject->id],
        );

        $teamWithObject->refresh();

        return [$location, $teamWithObject, $teamWithoutObject];
    }
}
