<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к /admin/teams с полем month_price: groups.view → 200 на всех эндпоинтах.
 */
final class TeamsPageMonthPriceAccessFeatureTest extends CrmTestCase
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

    public function test_teams_page_and_month_price_endpoints_return_200_with_full_access(): void
    {
        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team');

        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Month price access team',
            'month_price' => 5500,
            'order_by'    => 3,
        ]);

        $this->getJson('/admin/teams/data?draw=1&start=0&length=10&title=Month price access')
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $dataQuery = http_build_query([
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'title'   => 'Month price access',
            'order'   => [['column' => 0, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'month_price'],
            ],
        ]);

        $this->getJson('/admin/teams/data?' . $dataQuery)->assertOk();

        $this->getJson('/admin/teams/columns-settings')->assertOk();

        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'order_by'     => true,
                'title'        => true,
                'month_price'  => true,
                'status_label' => true,
                'actions'      => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get(route('logs.data.team'))->assertOk();

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonStructure(['id', 'title', 'month_price']);

        $store = $this->postJson(route('admin.team.store'), [
            'title'                    => 'Created month price access',
            'default_duration_minutes' => 60,
            'month_price'              => 7000,
            'order_by'                 => 8,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $createdId = (int) $store->json('team.id');

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Month price access team updated',
            'default_duration_minutes' => 60,
            'month_price'              => 6000,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
        ])->assertOk();

        $row = collect(
            $this->getJson('/admin/teams/data?draw=1&start=0&length=100&title=Month price access')
                ->assertOk()
                ->json('data') ?? []
        )->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('month_price', $row);
        $this->assertSame(6000, $row['month_price']);

        $this->deleteJson(route('admin.team.delete', ['team' => $createdId]))->assertOk();
    }

    public function test_user_with_only_groups_view_gets_200_on_month_price_crud_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantGroupsViewOnly($actor);
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Groups view month price',
            'month_price' => 1500,
        ]);

        $this->get(route('admin.team.index'))->assertOk();
        $this->getJson('/admin/teams/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/admin/teams/columns-settings')->assertOk();
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['title' => true, 'month_price' => true, 'status_label' => true, 'actions' => true],
        ])->assertOk();
        $this->get(route('logs.data.team'))->assertOk();
        $this->getJson(route('admin.team.edit', ['id' => $team->id]))->assertOk();

        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Minimal month price create',
            'default_duration_minutes' => 60,
            'month_price'              => 0,
            'order_by'                 => 1,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Minimal month price update',
            'default_duration_minutes' => 60,
            'month_price'              => 1800,
            'order_by'                 => $team->order_by,
            'is_enabled'               => 1,
        ])->assertOk();

        $deleteTarget = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'To delete month price',
        ]);

        $this->deleteJson(route('admin.team.delete', ['team' => $deleteTarget->id]))->assertOk();
    }

    public function test_without_groups_view_month_price_endpoints_are_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'month_price' => 1000,
        ]);

        $this->get('/admin/teams')->assertStatus(403);
        $this->get('/admin/teams/data')->assertStatus(403);
        $this->getJson('/admin/teams/columns-settings')->assertStatus(403);
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['month_price' => true],
        ])->assertStatus(403);
        $this->get(route('logs.data.team'))->assertStatus(403);
        $this->getJson(route('admin.team.edit', ['id' => $team->id]))->assertStatus(403);
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'x',
            'default_duration_minutes' => 60,
            'month_price'              => 1000,
            'order_by'                 => 1,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'x',
            'default_duration_minutes' => 60,
            'month_price'              => 2000,
            'order_by'                 => 1,
            'is_enabled'               => 1,
        ])->assertStatus(403);
        $this->deleteJson(route('admin.team.delete', ['team' => $team->id]))->assertStatus(403);
    }

    public function test_guest_gets_redirect_or_forbidden_on_month_price_endpoints(): void
    {
        Auth::logout();

        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'month_price' => 1000,
        ]);

        $calls = [
            fn () => $this->get('/admin/teams'),
            fn () => $this->get('/admin/teams/data'),
            fn () => $this->getJson('/admin/teams/columns-settings'),
            fn () => $this->postJson('/admin/teams/columns-settings', ['columns' => ['month_price' => true]]),
            fn () => $this->get(route('logs.data.team')),
            fn () => $this->getJson(route('admin.team.edit', ['id' => $team->id])),
            fn () => $this->postJson(route('admin.team.store'), [
                'title'                    => 'x',
                'default_duration_minutes' => 60,
                'month_price'              => 1000,
                'order_by'                 => 1,
                'is_enabled'               => 1,
            ]),
            fn () => $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
                'title'                    => 'x',
                'default_duration_minutes' => 60,
                'month_price'              => 2000,
                'order_by'                 => 1,
                'is_enabled'               => 1,
            ]),
            fn () => $this->deleteJson(route('admin.team.delete', ['team' => $team->id])),
        ];

        foreach ($calls as $call) {
            $this->assertContains($call()->getStatusCode(), [302, 401, 403]);
        }
    }
}
