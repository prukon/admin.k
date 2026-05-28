<?php

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к странице /admin/teams и всем связанным эндпоинтам (groups.view → 200, без права → 403).
 */
class TeamsPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_teams_index_page_returns_200_with_groups_view(): void
    {
        $this->get('/admin/teams')
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertViewHas(['weekdays', 'trainerOptions']);
    }

    public function test_all_teams_page_endpoints_return_200_for_user_with_groups_view(): void
    {
        $this->get('/admin/teams')->assertOk();

        $this->get('/admin/teams/data?draw=1&start=0&length=10')->assertOk();

        $this->getJson('/admin/teams/columns-settings')->assertOk();

        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'order_by'      => true,
                'title'         => true,
                'trainer_label' => true,
                'status_label'  => true,
                'actions'       => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get(route('logs.data.team'))->assertOk();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Full access smoke',
            'order_by'   => 5,
        ]);

        $this->getJson(route('admin.team.edit', $team->id))->assertOk();

        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Created via full access test',
            'default_duration_minutes' => 60,
            'order_by'                 => 99,
            'is_enabled'               => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->patchJson(route('admin.team.update', $team->id), [
            'title'                    => 'Full access smoke updated',
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
        ])->assertOk();

        $this->deleteJson(route('admin.team.delete', $team->id))->assertOk();
    }

    public function test_user_with_only_groups_view_can_access_page_and_all_section_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantGroupsViewForUser($actor);
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Groups view only smoke',
            'order_by'   => 3,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertViewHas(['weekdays', 'trainerOptions']);

        $this->getJson('/admin/teams/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/admin/teams/data?draw=1&status=active&search[value]=Groups')->assertOk();
        $this->getJson('/admin/teams/data?draw=1&title=Groups&status=active&trainer_profile_id=none')->assertOk();

        $this->getJson('/admin/teams/columns-settings')->assertOk();
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'order_by'     => true,
                'title'        => true,
                'status_label' => true,
                'actions'      => true,
            ],
        ])->assertOk();

        $this->get(route('logs.data.team'))->assertOk();
        $this->getJson(route('admin.team.edit', $team->id))->assertOk();

        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Created with groups.view only',
            'default_duration_minutes' => 60,
            'order_by'                 => 7,
            'is_enabled'               => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->patchJson(route('admin.team.update', $team->id), [
            'title'                    => 'Updated with groups.view only',
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
        ])->assertOk();

        $deleteTarget = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'To delete groups view only',
        ]);

        $this->deleteJson(route('admin.team.delete', $deleteTarget->id))->assertOk();
    }

    public function test_teams_data_new_filter_and_search_params_return_200(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Filter 200 smoke',
            'is_enabled' => 1,
        ]);

        $queries = [
            '/admin/teams/data?draw=1&start=0&length=10&status=active',
            '/admin/teams/data?draw=1&start=0&length=10&status=inactive',
            '/admin/teams/data?draw=1&start=0&length=10&title=Filter',
            '/admin/teams/data?draw=1&start=0&length=10&trainer_profile_id=none',
            '/admin/teams/data?draw=1&start=0&length=10&search[value]=Filter',
            '/admin/teams/data?draw=1&start=0&length=10&title=Filter&search[value]=Other&status=active',
        ];

        foreach ($queries as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_data_endpoint_with_full_column_layout_returns_200(): void
    {
        $this->grantScheduleViewForAdmin();

        $weekdayIds = Weekday::take(1)->pluck('id')->all();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Layout smoke',
        ]);
        $team->weekdays()->sync($weekdayIds);

        $query = http_build_query([
            'order'   => [['column' => 3, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'trainer_label'],
                ['name' => 'weekdays_label'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'title'  => 'Layout',
        ]);

        $json = $this->get('/admin/teams/data?' . $query)
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $row = collect($json['data'])->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('trainer_label', $row);
        $this->assertArrayHasKey('weekdays_label', $row);
    }

    public function test_teams_index_returns_403_without_groups_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->get('/admin/teams')->assertStatus(403);
    }

    public function test_teams_data_returns_403_without_groups_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->get('/admin/teams/data')->assertStatus(403);
    }

    public function test_columns_settings_get_and_post_return_403_without_groups_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson('/admin/teams/columns-settings')->assertStatus(403);

        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['title' => true],
        ])->assertStatus(403);
    }

    public function test_team_logs_endpoint_returns_403_without_groups_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('logs.data.team'))->assertStatus(403);
    }

    public function test_team_edit_returns_403_without_groups_view(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('admin.team.edit', $team->id))->assertStatus(403);
    }

    public function test_team_store_returns_403_without_groups_view(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Forbidden create',
            'default_duration_minutes' => 60,
            'order_by'                 => 1,
            'is_enabled'               => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(403);
    }

    public function test_team_update_returns_403_without_groups_view(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->patchJson(route('admin.team.update', $team->id), [
            'title'                    => 'Forbidden update',
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => 1,
        ])->assertStatus(403);
    }

    public function test_team_delete_returns_403_without_groups_view(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $this->deleteJson(route('admin.team.delete', $team->id))->assertStatus(403);
    }

    public function test_guest_cannot_access_any_teams_endpoint(): void
    {
        Auth::logout();

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $endpoints = [
            fn () => $this->get('/admin/teams'),
            fn () => $this->get('/admin/teams/data'),
            fn () => $this->getJson('/admin/teams/columns-settings'),
            fn () => $this->postJson('/admin/teams/columns-settings', ['columns' => ['title' => true]]),
            fn () => $this->get(route('logs.data.team')),
            fn () => $this->getJson(route('admin.team.edit', $team->id)),
            fn () => $this->postJson(route('admin.team.store'), [
                'title' => 'x', 'default_duration_minutes' => 60,
                'order_by' => 1, 'is_enabled' => 1,
            ]),
            fn () => $this->patchJson(route('admin.team.update', $team->id), [
                'title' => 'x', 'default_duration_minutes' => 60,
                'order_by' => 1, 'is_enabled' => 1,
            ]),
            fn () => $this->deleteJson(route('admin.team.delete', $team->id)),
        ];

        foreach ($endpoints as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected status: ' . $status);
        }
    }

    private function grantGroupsViewForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantScheduleViewForAdmin(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->roleId('admin'),
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
