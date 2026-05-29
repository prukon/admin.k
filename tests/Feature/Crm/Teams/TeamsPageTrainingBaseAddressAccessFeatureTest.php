<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к /admin/teams и всем эндпоинтам раздела при правах
 * groups.view + groups.training_base.view + groups.address.view (→ 200).
 */
final class TeamsPageTrainingBaseAddressAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();
        $this->grantTeamsTrainingBaseAddressPermissions();
    }

    private function grantTeamsTrainingBaseAddressPermissions(): void
    {
        foreach (['groups.view', 'groups.training_base.view', 'groups.address.view'] as $permission) {
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

    public function test_teams_page_and_all_section_endpoints_return_200_with_training_base_and_address_permissions(): void
    {
        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewIs('admin.team')
            ->assertViewHas(['weekdays', 'trainerOptions'])
            ->assertSee('id="training_base"', false)
            ->assertSee('id="address"', false)
            ->assertSee('id="colTrainingBase"', false)
            ->assertSee('id="colAddress"', false);

        foreach ([
            '/admin/teams/data?draw=1&start=0&length=10',
            '/admin/teams/data?draw=1&start=0&length=10&status=active',
            '/admin/teams/data?draw=1&start=0&length=10&title=TrainingBase',
            '/admin/teams/data?draw=1&start=0&length=10&search[value]=TrainingBase',
        ] as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }

        $this->getJson('/admin/teams/columns-settings')->assertOk();

        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'order_by'       => true,
                'title'          => true,
                'training_base'  => true,
                'address'        => true,
                'month_price'    => true,
                'status_label'   => true,
                'actions'        => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get(route('logs.data.team'))->assertOk();

        $team = Team::factory()->create([
            'partner_id'    => $this->partner->id,
            'title'         => 'TrainingBase access team',
            'training_base' => 'База до edit',
            'address'       => 'Адрес до edit',
            'order_by'      => 7,
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('training_base', 'База до edit')
            ->assertJsonPath('address', 'Адрес до edit');

        $store = $this->postJson(route('admin.team.store'), [
            'title'                    => 'Created training base access',
            'default_duration_minutes' => 60,
            'order_by'                 => 11,
            'is_enabled'               => 1,
            'training_base'            => 'СК Создание',
            'address'                  => 'ул. Создания, 2',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $createdId = (int) $store->json('team.id');
        $this->assertGreaterThan(0, $createdId);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => $team->title,
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
            'training_base'            => 'База после update',
            'address'                  => 'ул. Обновления, 3',
        ])->assertOk();

        $json = $this->getJson('/admin/teams/data?draw=1&start=0&length=100&title=TrainingBase')
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('training_base', $row);
        $this->assertArrayHasKey('address', $row);
        $this->assertSame('База после update', $row['training_base']);
        $this->assertSame('ул. Обновления, 3', $row['address']);

        $this->deleteJson(route('admin.team.delete', ['team' => $createdId]))->assertOk();
        $this->deleteJson(route('admin.team.delete', ['team' => $team->id]))->assertOk();
    }

    public function test_user_with_only_groups_view_still_gets_200_on_page_and_core_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->grantGroupsViewOnly($actor);
        $this->actingAs($actor);

        $team = Team::factory()->create([
            'partner_id'    => $this->partner->id,
            'title'         => 'Groups view only training fields',
            'training_base' => 'Hidden in UI',
            'address'       => 'Hidden in UI too',
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertDontSee('id="training_base"', false)
            ->assertDontSee('id="address"', false);

        $this->getJson('/admin/teams/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/admin/teams/columns-settings')->assertOk();
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['title' => true, 'month_price' => true, 'status_label' => true, 'actions' => true],
        ])->assertOk();
        $this->get(route('logs.data.team'))->assertOk();

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonMissingPath('training_base')
            ->assertJsonMissingPath('address');

        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Minimal create no training fields',
            'default_duration_minutes' => 60,
            'order_by'                 => 1,
            'is_enabled'               => 1,
            'training_base'            => 'Ignored',
            'address'                  => 'Ignored too',
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id'    => $this->partner->id,
            'title'         => 'Minimal create no training fields',
            'training_base' => null,
            'address'       => null,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Minimal update',
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => 1,
            'training_base'            => 'Ignored update',
            'address'                  => 'Ignored update addr',
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id'            => $team->id,
            'training_base' => 'Hidden in UI',
            'address'       => 'Hidden in UI too',
        ]);

        $deleteTarget = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'To delete minimal training',
        ]);

        $this->deleteJson(route('admin.team.delete', ['team' => $deleteTarget->id]))->assertOk();
    }

    public function test_without_groups_view_all_teams_endpoints_are_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('groups.training_base.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('groups.address.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $this->actingAs($actor);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get('/admin/teams')->assertStatus(403);
        $this->get('/admin/teams/data')->assertStatus(403);
        $this->getJson('/admin/teams/columns-settings')->assertStatus(403);
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => ['training_base' => true, 'address' => true],
        ])->assertStatus(403);
        $this->get(route('logs.data.team'))->assertStatus(403);
        $this->getJson(route('admin.team.edit', ['id' => $team->id]))->assertStatus(403);
        $this->postJson(route('admin.team.store'), [
            'title' => 'x', 'default_duration_minutes' => 60, 'order_by' => 1, 'is_enabled' => 1,
            'training_base' => 'x', 'address' => 'y',
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
            fn () => $this->postJson('/admin/teams/columns-settings', [
                'columns' => ['training_base' => true, 'address' => true],
            ]),
            fn () => $this->get(route('logs.data.team')),
            fn () => $this->getJson(route('admin.team.edit', ['id' => $team->id])),
            fn () => $this->postJson(route('admin.team.store'), [
                'title' => 'x', 'default_duration_minutes' => 60, 'order_by' => 1, 'is_enabled' => 1,
                'training_base' => 'x', 'address' => 'y',
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
