<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TeamsTrainingBaseAddressFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();
        $this->grantCorePermissions();
    }

    private function grantCorePermissions(): void
    {
        foreach (['groups.view', 'groups.training_base.view', 'groups.address.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_teams_index_includes_fields_in_view_when_permissions_granted(): void
    {
        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('id="training_base"', false)
            ->assertSee('id="address"', false)
            ->assertSee('id="colTrainingBase"', false)
            ->assertSee('id="colAddress"', false);
    }

    public function test_store_team_with_training_base_and_address(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title' => 'Группа с базой',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'training_base' => 'СК Олимп',
            'address' => 'ул. Примерная, 1',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Группа с базой',
            'training_base' => 'СК Олимп',
            'address' => 'ул. Примерная, 1',
        ]);
    }

    public function test_data_returns_training_base_and_address_columns(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа А',
            'training_base' => 'База 1',
            'address' => 'Адрес 1',
        ]);

        $this->getJson(route('admin.team.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.training_base', 'База 1')
            ->assertJsonPath('data.0.address', 'Адрес 1');
    }

    public function test_edit_returns_training_base_and_address(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'training_base' => 'База edit',
            'address' => 'Адрес edit',
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('training_base', 'База edit')
            ->assertJsonPath('address', 'Адрес edit');
    }

    public function test_update_team_training_base_and_address(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа B',
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => $team->order_by,
            'is_enabled' => (int) $team->is_enabled,
            'training_base' => 'Новая база',
            'address' => 'Новый адрес',
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'training_base' => 'Новая база',
            'address' => 'Новый адрес',
        ]);
    }

    public function test_without_training_base_permission_field_is_not_accepted_on_store(): void
    {
        $actor = $this->createUserWithoutPermission('groups.training_base.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.address.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Team without training base permission',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'training_base' => 'Should be ignored',
            'address' => 'ул. 1',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Team without training base permission',
            'training_base' => null,
            'address' => 'ул. 1',
        ]);
    }

    public function test_without_address_permission_field_is_not_accepted_on_store(): void
    {
        $actor = $this->createUserWithoutPermission('groups.address.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.training_base.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Team without address permission',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'training_base' => 'База',
            'address' => 'Should be ignored',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Team without address permission',
            'training_base' => 'База',
            'address' => null,
        ]);
    }

    public function test_data_hides_columns_without_permissions(): void
    {
        $actor = $this->createUserWithoutPermission('groups.training_base.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Hidden fields',
            'training_base' => 'Secret base',
            'address' => 'Secret address',
        ]);

        $this->getJson(route('admin.team.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.training_base', '')
            ->assertJsonPath('data.0.address', '');
    }

    public function test_index_hides_fields_without_permissions(): void
    {
        $actor = $this->createUserWithoutPermission('groups.training_base.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertDontSee('id="training_base"', false)
            ->assertDontSee('id="colTrainingBase"', false);
    }

    public function test_store_with_empty_training_base_and_address_saves_null(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title' => 'Группа пустые поля',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'training_base' => '',
            'address' => '',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $team = Team::query()->where('title', 'Группа пустые поля')->firstOrFail();
        $this->assertNull($team->training_base);
        $this->assertNull($team->address);
    }

    public function test_store_rejects_training_base_longer_than_255(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title' => 'Группа длинная база',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'training_base' => str_repeat('а', 256),
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['training_base']);
    }

    public function test_store_rejects_address_longer_than_255(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title' => 'Группа длинный адрес',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'address' => str_repeat('б', 256),
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    public function test_update_clears_training_base_and_address_when_empty_strings(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа очистка',
            'training_base' => 'База',
            'address' => 'Адрес',
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => $team->order_by,
            'is_enabled' => (int) $team->is_enabled,
            'training_base' => '',
            'address' => '',
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'training_base' => null,
            'address' => null,
        ]);
    }

    public function test_without_training_base_permission_update_ignores_field(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа update base',
            'training_base' => 'Старая база',
            'address' => 'Старый адрес',
        ]);

        $actor = $this->createUserWithoutPermission('groups.training_base.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.address.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => $team->order_by,
            'is_enabled' => (int) $team->is_enabled,
            'training_base' => 'Новая база игнор',
            'address' => 'Новый адрес ок',
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'training_base' => 'Старая база',
            'address' => 'Новый адрес ок',
        ]);
    }

    public function test_without_address_permission_update_ignores_field(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа update addr',
            'training_base' => 'Старая база 2',
            'address' => 'Старый адрес 2',
        ]);

        $actor = $this->createUserWithoutPermission('groups.address.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.training_base.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => $team->order_by,
            'is_enabled' => (int) $team->is_enabled,
            'training_base' => 'Новая база ок',
            'address' => 'Новый адрес игнор',
        ])->assertOk();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'training_base' => 'Новая база ок',
            'address' => 'Старый адрес 2',
        ]);
    }

    public function test_edit_without_training_base_permission_omits_key(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'training_base' => 'База edit omit',
            'address' => 'Адрес edit keep',
        ]);

        $actor = $this->createUserWithoutPermission('groups.training_base.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.address.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonMissingPath('training_base')
            ->assertJsonPath('address', 'Адрес edit keep');
    }

    public function test_edit_without_address_permission_omits_key(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'training_base' => 'База edit keep',
            'address' => 'Адрес edit omit',
        ]);

        $actor = $this->createUserWithoutPermission('groups.address.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.training_base.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJsonPath('training_base', 'База edit keep')
            ->assertJsonMissingPath('address');
    }

    public function test_columns_settings_roundtrip_for_training_base_and_address(): void
    {
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'title' => true,
                'training_base' => false,
                'address' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson('/admin/teams/columns-settings')
            ->assertOk()
            ->assertJsonPath('training_base', false)
            ->assertJsonPath('address', true);
    }

    public function test_data_sorts_by_training_base(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Team Z',
            'training_base' => 'Яблоко',
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Team A',
            'training_base' => 'Абрикос',
        ]);

        $json = $this->getJson('/admin/teams/data?draw=1&start=0&length=10&order[0][column]=3&order[0][dir]=asc&columns[3][name]=training_base')
            ->assertOk()
            ->json();

        $bases = array_column($json['data'] ?? [], 'training_base');
        $this->assertSame(['Абрикос', 'Яблоко'], array_values(array_filter($bases)));
    }

    public function test_data_sorts_by_address(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Addr Z',
            'address' => 'ул. Яблочная',
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Addr A',
            'address' => 'ул. Абрикосовая',
        ]);

        $json = $this->getJson('/admin/teams/data?draw=1&start=0&length=10&order[0][column]=4&order[0][dir]=asc&columns[4][name]=address')
            ->assertOk()
            ->json();

        $addresses = array_column($json['data'] ?? [], 'address');
        $this->assertSame(['ул. Абрикосовая', 'ул. Яблочная'], array_values(array_filter($addresses)));
    }

    public function test_data_hides_address_without_address_permission(): void
    {
        $actor = $this->createUserWithoutPermission('groups.address.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('groups.training_base.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Partial hide',
            'training_base' => 'Visible base',
            'address' => 'Hidden address',
        ]);

        $this->getJson(route('admin.team.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.training_base', 'Visible base')
            ->assertJsonPath('data.0.address', '');
    }
}
