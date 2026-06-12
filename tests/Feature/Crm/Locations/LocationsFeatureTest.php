<?php

namespace Tests\Feature\Crm\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LocationsFeatureTest extends CrmTestCase
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
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.locations.index'))->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('locations.view');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('Локации')
            ->assertSee('id="locations-table"', false)
            ->assertDontSee('locationCreateModal', false)
            ->assertDontSee('id="locationCreateSubmit"', false);
    }

    public function test_index_renders_datatables_and_manage_actions_when_allowed(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('js-location-edit', false);
    }

    public function test_data_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.locations.data'))->assertStatus(403);
    }

    public function test_data_returns_partner_scoped_locations(): void
    {
        $this->grantPermission('locations.view');

        $own = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет Alpha',
            'address' => 'Улица 1',
            'is_enabled' => true,
        ]);

        Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужая локация',
        ]);

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.name', 'Кабинет Alpha')
            ->assertJsonPath('data.0.address', 'Улица 1')
            ->assertJsonPath('data.0.is_enabled_label', 'Да');
    }

    public function test_index_edit_modal_has_confirm_delete_button(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('id="locationDeleteBtn"', false)
            ->assertSee('confirm-delete-modal', false)
            ->assertSee('deleteLocation', false)
            ->assertSee('showConfirmDeleteModal', false);
    }

    public function test_index_renders_toolbar_and_filters_like_users_page(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('locationsReportFiltersCollapse', false)
            ->assertSee('filter-name', false)
            ->assertSee('filter-status', false)
            ->assertSee('locationsColumnsDropdown', false)
            ->assertSee('id="new-location"', false);
    }

    public function test_data_panel_name_takes_precedence_over_datatables_search(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Panel Alpha',
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Search Beta',
        ]);

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Panel',
            'search' => ['value' => 'Search'],
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Panel Alpha');
    }

    public function test_data_returns_expected_row_structure(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Struct loc',
            'address' => 'Addr',
            'is_enabled' => false,
        ]);

        $json = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->json();

        $row = collect($json['data'])->firstWhere('id', $loc->id);
        $this->assertNotNull($row);
        $this->assertSame([
            'id',
            'name',
            'address',
            'teams_label',
            'teams_label_full',
            'teams_titles',
            'is_enabled',
            'is_enabled_label',
        ], array_keys($row));
        $this->assertSame('', $row['teams_label']);
        $this->assertSame('', $row['teams_label_full']);
        $this->assertSame([], $row['teams_titles']);
        $this->assertSame('Нет', $row['is_enabled_label']);
        $this->assertSame(0, $row['is_enabled']);
    }

    public function test_data_filters_by_status_inactive(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Active loc 2',
            'is_enabled' => true,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Inactive loc 2',
            'is_enabled' => false,
        ]);

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'inactive',
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Inactive loc 2')
            ->assertJsonPath('data.0.is_enabled_label', 'Нет');
    }

    public function test_data_search_filters_by_address(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Loc A',
            'address' => 'Уникальный адрес 42',
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Loc B',
            'address' => 'Другой адрес',
        ]);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'Уникальный'],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.address', 'Уникальный адрес 42');
    }

    public function test_data_search_filters_by_numeric_id(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'By id search',
        ]);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => (string) $loc->id],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $loc->id);
    }

    public function test_data_filters_combined_name_and_status(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Combo Active',
            'is_enabled' => true,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Combo Inactive',
            'is_enabled' => false,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Other Active',
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'name' => 'Combo',
            'status' => 'inactive',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Combo Inactive');
    }

    public function test_data_sort_by_is_enabled_desc(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Sort A',
            'is_enabled' => true,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Sort B',
            'is_enabled' => false,
        ]);

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'order' => [['column' => 3, 'dir' => 'desc']],
            'columns' => [
                ['name' => 'id'],
                ['name' => 'name'],
                ['name' => 'address'],
                ['name' => 'is_enabled_label'],
            ],
        ]));

        $response->assertOk();
        $labels = array_column($response->json('data'), 'is_enabled_label');
        $this->assertSame(['Да', 'Нет'], $labels);
    }

    public function test_columns_settings_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.locations.columns-settings.get'))->assertStatus(403);
        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertStatus(403);
    }

    public function test_columns_settings_save_requires_columns_array(): void
    {
        $this->grantPermission('locations.view');

        $this->postJson(route('admin.locations.columns-settings.save'), [])
            ->assertStatus(422);
    }

    public function test_data_filters_by_status_active(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Active loc',
            'is_enabled' => true,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Inactive loc',
            'is_enabled' => false,
        ]);

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Active loc');
    }

    public function test_columns_settings_roundtrip(): void
    {
        $this->grantPermission('locations.view');

        $this->postJson(route('admin.locations.columns-settings.save'), [
            'columns' => [
                'id' => true,
                'name' => false,
                'address' => true,
                'is_enabled_label' => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.locations.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('name', false)
            ->assertJsonPath('address', true);
    }

    public function test_data_search_filters_by_name(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет Alpha',
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет Beta',
        ]);

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'Alpha'],
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Кабинет Alpha');
    }

    public function test_data_paginates_results(): void
    {
        $this->grantPermission('locations.view');

        foreach (['Aaa', 'Bbb', 'Ccc'] as $name) {
            Location::factory()->create([
                'partner_id' => $this->partner->id,
                'name' => $name,
            ]);
        }

        $response = $this->getJson(route('admin.locations.data', [
            'draw' => 1,
            'start' => 1,
            'length' => 1,
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'id'],
                ['name' => 'name'],
                ['name' => 'address'],
                ['name' => 'is_enabled_label'],
            ],
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonPath('recordsFiltered', 3)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bbb');
    }

    public function test_index_ok_for_admin_by_default_base_permissions(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.locations.index'))
            ->assertOk();
    }

    public function test_store_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('locations.view');

        $this->post(route('admin.locations.store'), [
            'name' => 'Кабинет 1',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_store_creates_partner_scoped_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Кабинет 1',
            'address' => 'Адрес',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('locations', [
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет 1',
        ]);
    }

    public function test_store_rejects_duplicate_name_within_partner(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет 1',
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Кабинет 1',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Локация с таким названием уже существует');
    }

    public function test_show_returns_404_for_foreign_partner_location(): void
    {
        $this->grantPermission('locations.view');

        $foreign = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->getJson(route('admin.locations.show', $foreign->id))
            ->assertStatus(404);
    }

    public function test_show_returns_200_for_own_location(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет A',
        ]);

        $this->getJson(route('admin.locations.show', $loc->id))
            ->assertOk()
            ->assertJsonPath('id', $loc->id)
            ->assertJsonPath('name', 'Кабинет A');
    }

    public function test_update_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->putJson(route('admin.locations.update', $loc->id), [
            'name' => 'Новая',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_update_returns_200_and_updates_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет B',
            'is_enabled' => true,
        ]);

        $this->putJson(route('admin.locations.update', $loc->id), [
            'name' => 'Кабинет B2',
            'address' => 'Адрес 2',
            'description' => 'Описание',
            'is_enabled' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('locations', [
            'id' => $loc->id,
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет B2',
            'address' => 'Адрес 2',
            'description' => 'Описание',
            'is_enabled' => 0,
        ]);
    }

    public function test_destroy_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->deleteJson(route('admin.locations.destroy', $loc->id))
            ->assertStatus(403);
    }

    public function test_destroy_returns_200_and_deletes_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->deleteJson(route('admin.locations.destroy', $loc->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Локация удалена');

        $this->assertDatabaseMissing('locations', [
            'id' => $loc->id,
        ]);
    }
}

