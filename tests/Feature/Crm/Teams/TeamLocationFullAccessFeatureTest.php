<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\District;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use App\Services\TeamLocationSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный smoke-доступ (200) к страницам и API после перехода на teams.location_id.
 */
final class TeamLocationFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantCorePermissions();
    }

    private function grantCorePermissions(): void
    {
        foreach ([
            'groups.view',
            'locations.view',
            'locations.manage',
            'schedule.view',
            'scheduleSlots.manage',
            'trainers.view',
            'sport_types.view',
            'lessonPackages.view',
        ] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function grantGroupsViewOnly(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_teams_page_renders_location_select_without_address_and_multiselect(): void
    {
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'UI smoke object',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertSee('id="location_id"', false)
            ->assertSee('id="edit-location-id"', false)
            ->assertSee('Объект', false)
            ->assertDontSee('id="createTeamLocationIds"', false)
            ->assertDontSee('id="editTeamLocationIds"', false)
            ->assertDontSee('id="address"', false)
            ->assertDontSee('id="colAddress"', false)
            ->assertDontSee('groups.address.view', false)
            ->assertDontSee('id="training_base"', false)
            ->assertDontSee('id="colTrainingBase"', false)
            ->assertDontSee('groups.training_base.view', false);
    }

    public function test_teams_and_locations_endpoints_return_200_with_full_location_binding_flow(): void
    {
        $district = District::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Full access district',
        ]);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
            'name' => 'Full access object',
            'address' => 'ул. Полная, 1',
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Full access existing team',
        ]);

        // --- Страница групп ---
        $this->get(route('admin.team.index'))->assertOk();

        $teamDataUrls = [
            '/admin/teams/data?draw=1&start=0&length=10',
            '/admin/teams/data?draw=1&location_id=' . $location->id,
            '/admin/teams/data?draw=1&location_id=none',
            '/admin/teams/data?draw=1&status=active&location_id=' . $location->id,
        ];

        foreach ($teamDataUrls as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }

        $this->getJson('/admin/teams/columns-settings')->assertOk();
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'title' => true,
                'locations_label' => true,
                'month_price' => true,
                'status_label' => true,
                'actions' => true,
            ],
        ])->assertOk();

        $this->get(route('logs.data.team'))->assertOk();

        $store = $this->postJson(route('admin.team.store'), [
            'title' => 'Created via full access',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'location_id' => $location->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $createdTeamId = (int) $store->json('team.id');

        $this->getJson(route('admin.team.edit', ['id' => $createdTeamId]))
            ->assertOk()
            ->assertJsonPath('location_id', $location->id);

        $this->patchJson(route('admin.team.update', ['id' => $createdTeamId]), [
            'title' => 'Updated via full access',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'location_id' => '',
        ])->assertOk();

        // --- Страница объектов: sync team_ids → teams.location_id ---
        $this->get(route('admin.locations.index'))->assertOk();

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('admin.locations.show', $location))->assertOk();

        $this->putJson(route('admin.locations.update', $location), [
            'name' => $location->name,
            'address' => $location->address,
            'is_enabled' => 1,
            'team_ids' => [$team->id],
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
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

        // --- Расписание: слот с парой группа+объект ---
        $team->update(['location_id' => $location->id]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 2,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => now()->format('Y-m-d'),
            'date_end' => now()->addMonth()->format('Y-m-d'),
            'is_enabled' => 1,
        ])->assertOk();

        // --- Лендинг ---
        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'full-access-binding', 'is_landing_active' => true]);

        app(TeamLocationSyncService::class)->syncTeamsForLocation($location, [(int) $team->id]);

        $this->getJson(route('lead.teams', [
            'landingSlug' => 'full-access-binding',
            'location_id' => $location->id,
        ]))->assertOk();

        $this->getJson(route('lead.team-info', [
            'landingSlug' => 'full-access-binding',
            'location_id' => $location->id,
            'team_id' => $team->id,
        ]))->assertOk();

        $this->deleteJson(route('admin.team.delete', ['team' => $createdTeamId]))->assertOk();
    }

    public function test_school_schedule_page_returns_200_when_teams_have_location_id(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Schedule object',
            'is_enabled' => true,
        ]);

        Team::factory()->count(2)->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        $response = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertViewIs('admin.lessonPackages.index')
            ->assertViewHas('teams')
            ->assertViewHas('locations');

        $teams = $response->viewData('teams');
        $this->assertGreaterThanOrEqual(3, $teams->count());

        $withLocation = $teams->first(fn (Team $team) => (int) $team->location_id === (int) $location->id);
        $this->assertNotNull($withLocation);
        $this->assertSame($location->id, (int) $withLocation->location_id);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => now()->startOfWeek()->format('Y-m-d'),
            'location_id' => $location->id,
        ]))->assertOk();
    }

    public function test_user_with_groups_and_locations_view_gets_200_on_all_location_binding_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantGroupsViewOnly($actor);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->get(route('admin.team.index'))->assertOk();
        $this->getJson('/admin/teams/data?draw=1&location_id=' . $location->id)->assertOk();
        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('location_id', $location->id);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Actor create with object',
            'is_enabled' => 1,
            'location_id' => $location->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => 'Actor update',
            'is_enabled' => 1,
            'location_id' => '',
        ])->assertOk();
    }

    public function test_user_without_locations_view_still_gets_200_but_location_id_is_ignored(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->grantGroupsViewOnly($actor);
        $this->actingAs($actor);

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);

        $store = $this->postJson(route('admin.team.store'), [
            'title' => 'No locations view',
            'is_enabled' => 1,
            'location_id' => $location->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => (int) $store->json('team.id'),
            'location_id' => null,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonMissing(['location_id']);
    }

    public function test_without_groups_view_teams_endpoints_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get('/admin/teams')->assertStatus(403);
        $this->getJson('/admin/teams/data?draw=1&location_id=' . $location->id)->assertStatus(403);
        $this->postJson(route('admin.team.store'), [
            'title' => 'x',
            'is_enabled' => 1,
            'location_id' => $location->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => 'x',
            'is_enabled' => 1,
            'location_id' => $location->id,
        ])->assertStatus(403);
        $this->deleteJson(route('admin.team.delete', ['team' => $team->id]))->assertStatus(403);
    }

    public function test_guest_gets_redirect_or_forbidden_on_teams_and_location_binding_endpoints(): void
    {
        Auth::logout();

        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $calls = [
            fn () => $this->get('/admin/teams'),
            fn () => $this->getJson('/admin/teams/data?draw=1&location_id=' . $location->id),
            fn () => $this->postJson(route('admin.team.store'), [
                'title' => 'x', 'is_enabled' => 1, 'location_id' => $location->id,
            ]),
            fn () => $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
                'title' => 'x', 'is_enabled' => 1, 'location_id' => $location->id,
            ]),
            fn () => $this->deleteJson(route('admin.team.delete', ['team' => $team->id])),
            fn () => $this->putJson(route('admin.locations.update', $location), [
                'name' => $location->name, 'is_enabled' => 1, 'team_ids' => [$team->id],
            ]),
        ];

        foreach ($calls as $call) {
            $this->assertContains($call()->getStatusCode(), [302, 401, 403]);
        }
    }
}
