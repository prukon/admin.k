<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Locations;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI и API страницы /admin/locations: колонка групп, hover-list, Select2 multiselect, team_ids.
 */
final class LocationsTeamsUiFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
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

    public function test_index_renders_teams_multiselect_assets_when_teams_exist_and_manage_allowed(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа для multiselect',
        ]);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="colLocationTeams"', false)
            ->assertSee('teams-multiselect-field', false)
            ->assertSee('id="locationCreateTeamIds"', false)
            ->assertSee('id="locationEditTeamIds"', false)
            ->assertSee('js-teams-multiselect-select', false)
            ->assertSee('KidsCrmTeamsMultiselectSelect2', false)
            ->assertSee('KidsCrmTeamsMultiselectSelect2.init', false);
    }

    public function test_index_renders_hover_list_dropdown_assets_when_teams_exist(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');

        Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа для hover']);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('KidsCrmHoverListDropdown', false)
            ->assertSee('KidsCrmHoverListDropdown.renderCell', false)
            ->assertSee('js-kids-hover-list-dropdown', false)
            ->assertSee('kids-hover-list-dropdown__trigger', false);
    }

    public function test_index_without_teams_omits_teams_column_and_partials(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertDontSee('id="colLocationTeams"', false)
            ->assertDontSee('teams-multiselect-field', false)
            ->assertDontSee('id="locationCreateTeamIds"', false)
            ->assertDontSee('window.KidsCrmTeamsMultiselectSelect2 = {', false)
            ->assertDontSee('window.KidsCrmHoverListDropdown = {', false);
    }

    public function test_index_view_only_renders_teams_column_but_not_manage_multiselect_modals(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantLocationsViewForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Только просмотр']);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="colLocationTeams"', false)
            ->assertSee('KidsCrmHoverListDropdown.renderCell', false)
            ->assertDontSee('id="locationCreateModal"', false)
            ->assertDontSee('id="locationCreateTeamIds"', false)
            ->assertDontSee('id="locationEditTeamIds"', false);
    }

    public function test_data_teams_label_shows_full_list_for_two_teams_without_truncation(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $teamAlpha = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Alpha group',
        ]);
        $teamBeta = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Beta group',
        ]);
        $this->attachTeamToLocation($teamAlpha, $location);
        $this->attachTeamToLocation($teamBeta, $location);

        $json = $this->getJson(route('admin.locations.data', ['draw' => 1, 'start' => 0, 'length' => 50]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $location->id);
        $this->assertNotNull($row);
        $this->assertSame('Alpha group, Beta group', $row['teams_label'] ?? null);
        $this->assertSame('Alpha group, Beta group', $row['teams_label_full'] ?? null);
        $this->assertSame(['Alpha group', 'Beta group'], $row['teams_titles'] ?? null);
    }

    public function test_data_teams_titles_are_sorted_alphabetically(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $teamZ = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Я группа',
        ]);
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'А группа',
        ]);
        $this->attachTeamToLocation($teamZ, $location);
        $this->attachTeamToLocation($teamA, $location);

        $json = $this->getJson(route('admin.locations.data', ['draw' => 1, 'start' => 0, 'length' => 50]))
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $location->id);
        $this->assertNotNull($row);
        $this->assertSame(['А группа', 'Я группа'], $row['teams_titles'] ?? null);
        $this->assertSame('А группа, Я группа', $row['teams_label_full'] ?? null);
    }

    public function test_store_rejects_foreign_partner_team_ids(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $response = $this->postJson(route('admin.locations.store'), [
            'name' => 'С чужой группой',
            'is_enabled' => 1,
            'team_ids' => [$foreignTeam->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0']);

        $errors = $response->json('errors');
        $this->assertSame(
            'Выберите группу из списка текущего партнёра',
            $errors['team_ids.0'][0] ?? null
        );
    }

    public function test_update_rejects_foreign_partner_team_ids(): void
    {
        $this->asAdmin();
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $this->putJson(route('admin.locations.update', $location), [
            'name' => $location->name,
            'is_enabled' => 1,
            'team_ids' => [$foreignTeam->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0']);
    }

    public function test_store_and_show_require_locations_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantLocationsManageForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Без права view',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertStatus(403);

        $this->getJson(route('admin.locations.show', $location))->assertStatus(403);
    }

    private function grantLocationsViewForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantLocationsManageForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('locations.manage'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
