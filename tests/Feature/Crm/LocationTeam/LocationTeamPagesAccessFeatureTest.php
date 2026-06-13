<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LocationTeam;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Smoke-доступ (200) к страницам и API, связанным с привязкой групп и локаций.
 */
final class LocationTeamPagesAccessFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

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

    public function test_teams_section_all_pivot_related_endpoints_return_200(): void
    {
        $this->grantPermission('groups.view');
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Pivot smoke loc',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team');

        $teamQueries = [
            '/admin/teams/data?draw=1&start=0&length=10',
            '/admin/teams/data?draw=1&location_id=' . $location->id,
            '/admin/teams/data?draw=1&location_id=none',
            '/admin/teams/data?draw=1&status=active&location_id=' . $location->id,
        ];

        foreach ($teamQueries as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }

        $this->getJson('/admin/teams/columns-settings')->assertOk();
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['title' => true, 'locations_label' => true],
        ])->assertOk();

        $store = $this->postJson(route('admin.team.store'), [
            'title' => 'Pivot access team',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'location_id' => $location->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $teamId = (int) $store->json('team.id');

        $this->getJson(route('admin.team.edit', ['id' => $teamId]))
            ->assertOk()
            ->assertJsonPath('location_id', $location->id);

        $this->patchJson(route('admin.team.update', ['id' => $teamId]), [
            'title' => 'Pivot access team updated',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'location_id' => '',
        ])->assertOk();

        $this->deleteJson(route('admin.team.delete', ['team' => $teamId]))->assertOk();
    }

    public function test_locations_section_all_pivot_related_endpoints_return_200(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');
        $this->grantPermission('groups.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'For loc pivot']);

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertViewIs('admin.locations.index');

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.locations.columns-settings.get'))->assertOk();
        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => ['name' => true, 'teams_label' => true],
        ])->assertOk();

        $create = $this->postJson(route('admin.locations.store'), [
            'name' => 'Pivot access location',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $location = Location::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Pivot access location')
            ->firstOrFail();

        $this->getJson(route('admin.locations.show', $location))
            ->assertOk()
            ->assertJsonPath('team_ids', [$team->id]);

        $this->putJson(route('admin.locations.update', $location), [
            'name' => 'Pivot access location updated',
            'is_enabled' => 1,
            'team_ids' => [],
        ])->assertOk();

        $this->deleteJson(route('admin.locations.destroy', $location))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_school_schedule_and_slot_endpoints_return_200_with_location_context(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalLocation', false);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
            'location_id' => $location->id,
        ]))->assertOk();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 3,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => CarbonImmutable::parse(self::WEEK_MONDAY)->format('Y-m-d'),
            'date_end' => CarbonImmutable::parse(self::WEEK_MONDAY)->addMonth()->format('Y-m-d'),
            'is_enabled' => 1,
        ])->assertOk();
    }

    public function test_user_with_groups_and_locations_view_can_access_both_sections(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantGroupsViewForUser($actor);
        $this->grantLocationsViewForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.team.index'))->assertOk();
        $this->getJson('/admin/teams/data?draw=1&location_id=none')->assertOk();

        $this->get(route('admin.locations.index'))->assertOk();
        $this->getJson(route('admin.locations.data', ['draw' => 1]))->assertOk();
    }

    public function test_teams_location_filter_params_return_200_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantGroupsViewForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.team.index'))->assertOk();
        $this->getJson('/admin/teams/data?draw=1&location_id=' . $location->id)->assertOk();
        $this->getJson('/admin/teams/data?draw=1&location_id=none')->assertOk();
    }

    public function test_guest_cannot_access_location_team_sections(): void
    {
        Auth::logout();

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $calls = [
            fn () => $this->get(route('admin.team.index')),
            fn () => $this->getJson('/admin/teams/data?draw=1'),
            fn () => $this->get(route('admin.locations.index')),
            fn () => $this->getJson(route('admin.locations.data', ['draw' => 1])),
            fn () => $this->postJson(route('admin.team.store'), [
                'title' => 'x', 'is_enabled' => 1, 'location_id' => $location->id,
            ]),
            fn () => $this->postJson(route('admin.locations.store'), [
                'name' => 'x', 'is_enabled' => 1, 'team_ids' => [$team->id],
            ]),
        ];

        foreach ($calls as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected status: ' . $status);
        }
    }

    public function test_locations_mutations_return_403_without_manage_even_with_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.manage', $this->partner);
        $this->grantLocationsViewForUser($actor);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.locations.index'))->assertOk();
        $this->getJson(route('admin.locations.data', ['draw' => 1]))->assertOk();
        $this->getJson(route('admin.locations.show', $location))->assertOk();

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Forbidden',
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertStatus(403);

        $this->putJson(route('admin.locations.update', $location), [
            'name' => $location->name,
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertStatus(403);
    }
}
