<?php

namespace Tests\Feature\Crm\Districts;

use App\Models\District;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class DistrictsFeatureTest extends CrmTestCase
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
        $user = $this->createUserWithoutPermission('districts.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.districts.index'))->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('districts.view');

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('Справочники')
            ->assertSee('Районы')
            ->assertSee('id="districts-table"', false)
            ->assertSee('id="new-district"', false)
            ->assertSee("linkClass: 'js-district-edit'", false);
    }

    public function test_index_shows_objects_tab_when_locations_view_allowed(): void
    {
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('Объекты');
    }

    public function test_data_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('districts.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.districts.data'))->assertStatus(403);
    }

    public function test_data_returns_partner_scoped_districts_with_locations_count(): void
    {
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $own = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Кудрово',
            'sort_order' => 5,
        ]);

        Location::factory()->forDistrict($own)->create([
            'name' => 'Школа №2',
        ]);

        District::factory()->forPartner($this->foreignPartner->id)->create([
            'name' => 'Чужой район',
        ]);

        $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.name', 'Кудрово')
            ->assertJsonPath('data.0.sort_order', 5)
            ->assertJsonPath('data.0.locations_count', 1)
            ->assertJsonPath('data.0.locations_label', 'Школа №2')
            ->assertJsonPath('data.0.locations_names', ['Школа №2'])
            ->assertJsonPath('data.0.is_enabled_label', 'Да');
    }

    public function test_data_filters_by_status_inactive(): void
    {
        $this->grantPermission('districts.view');

        District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Active district',
            'is_enabled' => true,
        ]);
        District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Inactive district',
            'is_enabled' => false,
        ]);

        $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'inactive',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Inactive district')
            ->assertJsonPath('data.0.is_enabled_label', 'Нет');
    }

    public function test_columns_settings_roundtrip(): void
    {
        $this->grantPermission('districts.view');

        $this->postJson(route('admin.districts.columns-settings.save'), [
            'columns' => [
                'sort_order' => true,
                'name' => false,
                'locations_label' => true,
                'is_enabled_label' => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.districts.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('name', false)
            ->assertJsonPath('locations_label', true);
    }

    public function test_store_creates_partner_scoped_district(): void
    {
        $this->grantPermission('districts.view');

        $this->postJson(route('admin.districts.store'), [
            'name' => 'Мурино',
            'sort_order' => 3,
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('districts', [
            'partner_id' => $this->partner->id,
            'name' => 'Мурино',
            'sort_order' => 3,
            'is_enabled' => 1,
        ]);
    }

    public function test_store_rejects_duplicate_name_within_partner(): void
    {
        $this->grantPermission('districts.view');

        District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Кудрово',
        ]);

        $this->postJson(route('admin.districts.store'), [
            'name' => 'Кудрово',
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Район с таким названием уже существует');
    }

    public function test_show_returns_404_for_foreign_partner_district(): void
    {
        $this->grantPermission('districts.view');

        $foreign = District::factory()->forPartner($this->foreignPartner->id)->create();

        $this->getJson(route('admin.districts.show', $foreign->id))
            ->assertStatus(404);
    }

    public function test_update_returns_200_and_updates_district(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Старое имя',
            'is_enabled' => true,
        ]);

        $this->putJson(route('admin.districts.update', $district->id), [
            'name' => 'Новое имя',
            'sort_order' => 7,
            'is_enabled' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
            'partner_id' => $this->partner->id,
            'name' => 'Новое имя',
            'sort_order' => 7,
            'is_enabled' => 0,
        ]);
    }

    public function test_destroy_rejects_when_locations_attached(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create();
        Location::factory()->forDistrict($district)->create(['name' => 'Школа №1']);

        $this->deleteJson(route('admin.districts.destroy', $district->id))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя удалить район: к нему привязаны объекты');

        $this->assertDatabaseHas('districts', ['id' => $district->id, 'name' => $district->name]);
    }

    public function test_destroy_hard_deletes_district_without_locations(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create();

        $this->deleteJson(route('admin.districts.destroy', $district->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Район удалён');

        $this->assertDatabaseMissing('districts', ['id' => $district->id]);
    }

    public function test_store_allows_reusing_district_name_after_hard_delete(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Повтор']);

        $this->deleteJson(route('admin.districts.destroy', $district->id))->assertOk();

        $this->postJson(route('admin.districts.store'), [
            'name'       => 'Повтор',
            'is_enabled' => 1,
        ])
            ->assertOk();

        $this->assertDatabaseHas('districts', [
            'partner_id' => $this->partner->id,
            'name'       => 'Повтор',
        ]);
    }

    public function test_index_ok_for_admin_by_default_base_permissions(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('Районы');
    }

    public function test_create_and_edit_modals_render_location_multiselect_when_locations_view(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Зал для multiselect',
        ]);

        $html = $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertSee('id="districtCreateLocationIds"', false)
            ->assertSee('id="districtEditLocationIds"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('Зал для multiselect', false)
            ->getContent();

        $createModal = (string) preg_replace('/^.*?id="districtCreateModal"/s', 'id="districtCreateModal"', $html, 1);
        $createModal = (string) preg_replace('/id="districtEditModal".*$/s', '', $createModal, 1);
        $this->assertStringContainsString('name="location_ids[]"', $createModal);
        $this->assertStringContainsString('districtCreateLocationIds', $createModal);
    }

    public function test_store_syncs_location_ids_for_new_district(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект при создании',
            'district_id' => null,
        ]);

        $response = $this->postJson(route('admin.districts.store'), [
            'name' => 'Новый район',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ])->assertOk();

        $districtId = (int) $response->json('district.id');
        $this->assertGreaterThan(0, $districtId);
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'district_id' => $districtId]);

        $this->getJson(route('admin.districts.show', ['district' => $districtId]))
            ->assertOk()
            ->assertJsonPath('location_ids', [$location->id]);
    }

    public function test_store_rejects_foreign_location_ids(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $foreignLocation = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой объект',
        ]);

        $this->postJson(route('admin.districts.store'), [
            'name' => 'Новый район',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [$foreignLocation->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_ids.0']);
    }

    public function test_update_without_location_ids_preserves_location_bindings(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Старый']);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'district_id' => $district->id,
        ]);

        $this->putJson(route('admin.districts.update', ['district' => $district->id]), [
            'name' => 'Новое имя',
            'sort_order' => 0,
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('districts', ['id' => $district->id, 'name' => 'Новое имя']);
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'district_id' => $district->id]);
    }

    public function test_show_and_update_sync_location_ids_for_district(): void
    {
        $this->asAdmin();
        $this->grantPermission('districts.view');
        $this->grantPermission('locations.view');

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Центр']);
        $otherDistrict = District::factory()->forPartner($this->partner->id)->create(['name' => 'Окраина']);

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект A',
            'district_id' => $district->id,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект B',
            'district_id' => null,
        ]);
        $locC = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Объект C',
            'district_id' => $otherDistrict->id,
        ]);

        $this->getJson(route('admin.districts.show', ['district' => $district->id]))
            ->assertOk()
            ->assertJsonPath('location_ids', [$locA->id]);

        $this->putJson(route('admin.districts.update', ['district' => $district->id]), [
            'name' => 'Центр',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [$locB->id, $locC->id],
        ])->assertOk();

        $this->assertDatabaseHas('locations', ['id' => $locA->id, 'district_id' => null]);
        $this->assertDatabaseHas('locations', ['id' => $locB->id, 'district_id' => $district->id]);
        $this->assertDatabaseHas('locations', ['id' => $locC->id, 'district_id' => $district->id]);

        $foreignLocation = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой объект',
        ]);

        $this->putJson(route('admin.districts.update', ['district' => $district->id]), [
            'name' => 'Центр',
            'sort_order' => 0,
            'is_enabled' => 1,
            'location_ids' => [$foreignLocation->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['location_ids.0']);
    }
}
