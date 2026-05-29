<?php

namespace Tests\Feature\Crm\SportTypes;

use App\Models\SportType;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SportTypesFeatureTest extends CrmTestCase
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

    public function test_index_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('sport_types.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.sport-types.index'))->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('sport_types.view');

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('Виды спорта')
            ->assertSee('id="sport-types-table"', false)
            ->assertDontSee('sportTypeCreateModal', false);
    }

    public function test_index_renders_manage_ui_when_manage_allowed(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $this->get(route('admin.sport-types.index'))
            ->assertOk()
            ->assertSee('serverSide: true', false)
            ->assertSee('js-sport-type-edit', false)
            ->assertSee('id="new-sport-type"', false);
    }

    public function test_data_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('sport_types.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.sport-types.data'))->assertStatus(403);
    }

    public function test_data_returns_partner_scoped_sport_types_with_teams_count(): void
    {
        $this->grantPermission('sport_types.view');

        $own = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Футбол',
            'sort' => 5,
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'sport_type_id' => $own->id,
            'title' => 'Группа футбол',
        ]);

        SportType::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой спорт',
        ]);

        $this->getJson(route('admin.sport-types.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.name', 'Футбол')
            ->assertJsonPath('data.0.sort', 5)
            ->assertJsonPath('data.0.teams_count', 1)
            ->assertJsonPath('data.0.is_enabled_label', 'Да');
    }

    public function test_data_filters_by_status_inactive(): void
    {
        $this->grantPermission('sport_types.view');

        SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Active sport',
            'is_enabled' => true,
        ]);
        SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Inactive sport',
            'is_enabled' => false,
        ]);

        $this->getJson(route('admin.sport-types.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'inactive',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Inactive sport')
            ->assertJsonPath('data.0.is_enabled_label', 'Нет');
    }

    public function test_data_search_filters_by_name(): void
    {
        $this->grantPermission('sport_types.view');

        SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гимнастика Alpha',
        ]);
        SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Плавание Beta',
        ]);

        $this->getJson(route('admin.sport-types.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Alpha',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Гимнастика Alpha');
    }

    public function test_columns_settings_roundtrip(): void
    {
        $this->grantPermission('sport_types.view');

        $this->postJson(route('admin.sport-types.columns-settings.save'), [
            'columns' => [
                'sort' => true,
                'name' => false,
                'teams_count' => true,
                'is_enabled_label' => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.sport-types.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('name', false)
            ->assertJsonPath('teams_count', true);
    }

    public function test_store_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('sport_types.view');

        $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Теннис',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_store_creates_partner_scoped_sport_type(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Теннис',
            'description' => 'Описание',
            'sort' => 3,
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('sport_types', [
            'partner_id' => $this->partner->id,
            'name' => 'Теннис',
            'description' => 'Описание',
            'sort' => 3,
            'is_enabled' => 1,
        ]);
    }

    public function test_store_rejects_duplicate_name_within_partner(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Бокс',
        ]);

        $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Бокс',
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Вид спорта с таким названием уже существует');
    }

    public function test_show_returns_404_for_foreign_partner_sport_type(): void
    {
        $this->grantPermission('sport_types.view');

        $foreign = SportType::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->getJson(route('admin.sport-types.show', $foreign->id))
            ->assertStatus(404);
    }

    public function test_show_returns_200_for_own_sport_type(): void
    {
        $this->grantPermission('sport_types.view');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Хоккей',
        ]);

        $this->getJson(route('admin.sport-types.show', $sportType->id))
            ->assertOk()
            ->assertJsonPath('id', $sportType->id)
            ->assertJsonPath('name', 'Хоккей');
    }

    public function test_update_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('sport_types.view');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->putJson(route('admin.sport-types.update', $sportType->id), [
            'name' => 'Новое имя',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_update_returns_200_and_updates_sport_type(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старое имя',
            'is_enabled' => true,
        ]);

        $this->putJson(route('admin.sport-types.update', $sportType->id), [
            'name' => 'Новое имя',
            'description' => 'Новое описание',
            'sort' => 7,
            'is_enabled' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('sport_types', [
            'id' => $sportType->id,
            'partner_id' => $this->partner->id,
            'name' => 'Новое имя',
            'description' => 'Новое описание',
            'sort' => 7,
            'is_enabled' => 0,
        ]);
    }

    public function test_destroy_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('sport_types.view');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->deleteJson(route('admin.sport-types.destroy', $sportType->id))
            ->assertStatus(403);
    }

    public function test_destroy_returns_200_and_nulls_team_sport_type_id(): void
    {
        $this->grantPermission('sport_types.view');
        $this->grantPermission('sport_types.manage');

        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'sport_type_id' => $sportType->id,
        ]);

        $this->deleteJson(route('admin.sport-types.destroy', $sportType->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Вид спорта удалён');

        $this->assertDatabaseMissing('sport_types', ['id' => $sportType->id]);
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'sport_type_id' => null,
        ]);
    }

    public function test_index_ok_for_admin_by_default_base_permissions(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.sport-types.index'))->assertOk();
    }
}
