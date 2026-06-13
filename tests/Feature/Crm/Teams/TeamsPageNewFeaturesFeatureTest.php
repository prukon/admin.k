<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Location;
use App\Models\Team;
use App\Models\Weekday;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Новый UI/API страницы /admin/teams: короткие дни недели, локации в таблице, multiselect локаций.
 */
final class TeamsPageNewFeaturesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
        $this->grantPermission('groups.view');
    }

    private function grantPermission(string $permissionName): void
    {
        $this->grantPermissionForRole($this->user->role_id, $permissionName);
    }

    private function grantPermissionForRole(int $roleId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function attachTeamToLocation(Team $team, Location $location): void
    {
        $team->update(['location_id' => $location->id]);
    }

    public function test_data_returns_weekdays_items_with_short_titles(): void
    {
        $this->grantPermission('schedule.view');

        $weekdays = Weekday::query()->orderBy('id')->take(3)->get();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Weekdays items smoke',
        ]);
        $team->weekdays()->sync($weekdays->pluck('id')->all());

        $json = $this->getJson('/admin/teams/data?draw=1&start=0&length=50')
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('weekdays_items', $row);
        $this->assertArrayHasKey('weekdays_label', $row);

        $items = $row['weekdays_items'];
        $this->assertCount(3, $items);

        foreach ($weekdays as $weekday) {
            $item = collect($items)->firstWhere('id', $weekday->id);
            $this->assertNotNull($item);
            $this->assertSame($weekday->shortTitle(), $item['short']);
        }

        foreach ($weekdays as $weekday) {
            $this->assertStringContainsString($weekday->shortTitle(), (string) $row['weekdays_label']);
            $this->assertStringNotContainsString($weekday->title, (string) $row['weekdays_label']);
        }
    }

    public function test_data_locations_label_shows_object_name(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Альфа зал',
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Team with object',
            'location_id' => $location->id,
        ]);

        $row = collect(
            $this->getJson('/admin/teams/data?draw=1&start=0&length=50')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertSame('Альфа зал', $row['locations_label']);
        $this->assertSame('Альфа зал', $row['locations_label_full']);
        $this->assertSame(['Альфа зал'], $row['locations_names']);
    }

    public function test_data_locations_label_empty_when_team_has_no_object(): void
    {
        $this->grantPermission('locations.view');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Team without object',
            'location_id' => null,
        ]);

        $row = collect(
            $this->getJson('/admin/teams/data?draw=1&start=0&length=50')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertSame('', $row['locations_label']);
        $this->assertSame('', $row['locations_label_full']);
        $this->assertSame([], $row['locations_names']);
    }

    public function test_data_without_locations_view_returns_empty_location_fields(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantPermissionForRole($actor->role_id, 'groups.view');
        $this->actingAs($actor);

        $loc = Location::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => true]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'No locations column',
        ]);
        $this->attachTeamToLocation($team, $loc);

        $row = collect(
            $this->getJson('/admin/teams/data?draw=1&start=0&length=50')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('locations_label', $row);
        $this->assertSame('', $row['locations_label']);
        $this->assertSame('', $row['locations_label_full']);
        $this->assertSame([], $row['locations_names']);
    }

    public function test_index_renders_location_select_when_locations_view(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Локация для select',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewHas('locationOptions')
            ->assertSee('id="location_id"', false)
            ->assertSee('id="edit-location-id"', false)
            ->assertDontSee('id="createTeamLocationIds"', false)
            ->assertDontSee('id="editTeamLocationIds"', false);
    }

    public function test_index_renders_hover_list_for_locations_column(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Hover loc',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee("type: 'list'", false)
            ->assertSee("type: 'money'", false);
    }

    public function test_index_renders_weekdays_checkboxes_with_schedule_view(): void
    {
        $this->grantPermission('schedule.view');

        $monday = Weekday::query()->where('title', 'Понедельник')->firstOrFail();

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('id="weekdays"', false)
            ->assertSee('id="weekday-' . $monday->id . '"', false)
            ->assertSee('Понедельник', false);
    }

    public function test_store_and_edit_with_location_id_field(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $store = $this->postJson(route('admin.team.store'), [
            'title'                    => 'Team with object',
            'default_duration_minutes' => 60,
            'order_by'                 => 1,
            'is_enabled'               => 1,
            'location_id'              => $loc->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $teamId = (int) $store->json('team.id');

        $this->assertDatabaseHas('teams', [
            'id'          => $teamId,
            'location_id' => $loc->id,
            'partner_id'  => $this->partner->id,
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $teamId]))
            ->assertOk()
            ->assertJsonPath('location_id', $loc->id);
    }
}
