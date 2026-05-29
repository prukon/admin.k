<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный smoke-доступ к /admin/teams и всем связанным эндпоинтам (groups.view + смежные права → 200).
 */
final class TeamsPageCompleteAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
        $this->grantCorePermissions();
    }

    private function grantCorePermissions(): void
    {
        foreach (['groups.view', 'locations.view', 'schedule.view', 'trainers.view', 'sport_types.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id'    => $this->partner->id,
                'role_id'       => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    private function grantGroupsViewOnly(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_teams_page_and_all_section_endpoints_return_200(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Complete access loc',
            'is_enabled' => true,
        ]);

        $sportType = \App\Models\SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Complete access sport',
        ]);

        $weekdayIds = Weekday::take(2)->pluck('id')->all();

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertViewHas(['weekdays', 'trainerOptions', 'locationOptions', 'sportTypeOptions']);

        $dataUrls = [
            '/admin/teams/data?draw=1&start=0&length=10',
            '/admin/teams/data?draw=1&start=0&length=10&status=active',
            '/admin/teams/data?draw=1&start=0&length=10&status=inactive',
            '/admin/teams/data?draw=1&start=0&length=10&title=Complete',
            '/admin/teams/data?draw=1&start=0&length=10&trainer_profile_id=none',
            '/admin/teams/data?draw=1&start=0&length=10&location_id=' . $location->id,
            '/admin/teams/data?draw=1&start=0&length=10&location_id=none',
            '/admin/teams/data?draw=1&start=0&length=10&sport_type_id=' . $sportType->id,
            '/admin/teams/data?draw=1&start=0&length=10&sport_type_id=none',
            '/admin/teams/data?draw=1&start=0&length=10&search[value]=Complete',
            '/admin/teams/data?draw=1&start=0&length=10&status=active&location_id=' . $location->id,
        ];

        foreach ($dataUrls as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }

        $this->getJson('/admin/teams/columns-settings')->assertOk();

        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'order_by'        => true,
                'title'           => true,
                'trainer_label'   => true,
                'locations_label' => true,
                'weekdays_label'  => true,
                'status_label'    => true,
                'actions'         => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get(route('logs.data.team'))->assertOk();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Complete access team',
            'order_by'   => 4,
        ]);
        $team->weekdays()->sync($weekdayIds);

        DB::table('location_team')->insert([
            'partner_id'  => $this->partner->id,
            'location_id' => $location->id,
            'team_id'     => $team->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonStructure(['id', 'title', 'location_ids', 'team_weekdays', 'sport_type_id']);

        $store = $this->postJson(route('admin.team.store'), [
            'title'                    => 'Created complete access',
            'default_duration_minutes' => 45,
            'order_by'                 => 8,
            'is_enabled'               => 1,
            'location_ids'             => [$location->id],
            'weekdays'                 => $weekdayIds,
            'sport_type_id'            => $sportType->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $createdId = (int) $store->json('team.id');

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Complete access team updated',
            'default_duration_minutes' => 50,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
            'location_ids'             => [],
            'weekdays'                 => $weekdayIds,
        ])->assertOk();

        $json = $this->getJson('/admin/teams/data?draw=1&start=0&length=100&title=Complete')
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('weekdays_items', $row);
        $this->assertArrayHasKey('locations_names', $row);

        $this->deleteJson(route('admin.team.delete', ['team' => $createdId]))->assertOk();
        $this->deleteJson(route('admin.team.delete', ['team' => $team->id]))->assertOk();
    }

    public function test_user_with_only_groups_view_gets_200_on_page_and_core_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantGroupsViewOnly($actor);
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Groups view only complete',
        ]);

        $this->get(route('admin.team.index'))->assertOk();
        $this->getJson('/admin/teams/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/admin/teams/columns-settings')->assertOk();
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['title' => true, 'status_label' => true, 'actions' => true],
        ])->assertOk();
        $this->get(route('logs.data.team'))->assertOk();
        $this->getJson(route('admin.team.edit', ['id' => $team->id]))->assertOk();

        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Minimal create',
            'default_duration_minutes' => 60,
            'order_by'                 => 1,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Minimal update',
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => 1,
        ])->assertOk();

        $deleteTarget = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'To delete minimal',
        ]);

        $this->deleteJson(route('admin.team.delete', ['team' => $deleteTarget->id]))->assertOk();
    }

    public function test_without_groups_view_all_teams_endpoints_are_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get('/admin/teams')->assertStatus(403);
        $this->get('/admin/teams/data')->assertStatus(403);
        $this->getJson('/admin/teams/columns-settings')->assertStatus(403);
        $this->postJson('/admin/teams/columns-settings', ['columns' => ['title' => true]])->assertStatus(403);
        $this->get(route('logs.data.team'))->assertStatus(403);
        $this->getJson(route('admin.team.edit', ['id' => $team->id]))->assertStatus(403);
        $this->postJson(route('admin.team.store'), [
            'title' => 'x', 'default_duration_minutes' => 60, 'order_by' => 1, 'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => 'x', 'default_duration_minutes' => 60, 'order_by' => 1, 'is_enabled' => 1,
        ])->assertStatus(403);
        $this->deleteJson(route('admin.team.delete', ['team' => $team->id]))->assertStatus(403);
    }

    public function test_guest_gets_redirect_or_forbidden_on_teams_endpoints(): void
    {
        Auth::logout();

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $calls = [
            fn () => $this->get('/admin/teams'),
            fn () => $this->get('/admin/teams/data'),
            fn () => $this->getJson('/admin/teams/columns-settings'),
            fn () => $this->postJson('/admin/teams/columns-settings', ['columns' => ['title' => true]]),
            fn () => $this->get(route('logs.data.team')),
            fn () => $this->getJson(route('admin.team.edit', ['id' => $team->id])),
            fn () => $this->postJson(route('admin.team.store'), [
                'title' => 'x', 'default_duration_minutes' => 60, 'order_by' => 1, 'is_enabled' => 1,
            ]),
            fn () => $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
                'title' => 'x', 'default_duration_minutes' => 60, 'order_by' => 1, 'is_enabled' => 1,
            ]),
            fn () => $this->deleteJson(route('admin.team.delete', ['team' => $team->id])),
        ];

        foreach ($calls as $call) {
            $this->assertContains($call()->getStatusCode(), [302, 401, 403]);
        }
    }
}
