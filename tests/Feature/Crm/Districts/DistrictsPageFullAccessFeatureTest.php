<?php

namespace Tests\Feature\Crm\Districts;

use App\Models\District;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный smoke доступа к /admin/districts и связанным endpoint'ам.
 */
final class DistrictsPageFullAccessFeatureTest extends CrmTestCase
{
    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();

        $this->district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Full access district',
            'sort_order' => 1,
        ]);
    }

    private function grantDistrictsViewTo(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('districts.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_districts_index_page_returns_200_with_districts_view(): void
    {
        $this->get(route('admin.districts.index'))
            ->assertOk()
            ->assertViewIs('admin.districts.index')
            ->assertSee('id="districts-table"', false)
            ->assertSee('id="directoriesSectionTabs"', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_all_districts_page_endpoints_return_200_for_admin(): void
    {
        $this->get(route('admin.districts.index'))->assertOk();

        $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]))->assertOk();

        $this->getJson(route('admin.districts.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'name'    => 'Full access',
            'order'   => [['column' => 0, 'dir' => 'desc']],
            'columns' => [['name' => 'sort_order']],
        ]))->assertOk();

        $this->getJson(route('admin.districts.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.districts.columns-settings.save'), [
            'columns' => [
                'sort_order' => true,
                'name' => true,
                'locations_count' => true,
                'is_enabled_label' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.districts.show', $this->district->id))
            ->assertOk()
            ->assertJsonPath('id', $this->district->id);

        $this->postJson(route('admin.districts.store'), [
            'name' => 'Created via full access test',
            'sort_order' => 2,
            'is_enabled' => 1,
        ])->assertOk();

        $this->putJson(route('admin.districts.update', $this->district->id), [
            'name' => 'Full access district updated',
            'sort_order' => 3,
            'is_enabled' => 1,
        ])->assertOk();

        $disposable = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Disposable for delete smoke',
        ]);

        $this->deleteJson(route('admin.districts.destroy', $disposable->id))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_with_districts_view_can_access_all_district_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantDistrictsViewTo($actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))->assertOk();

        $this->getJson(route('admin.districts.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('admin.districts.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.districts.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertOk();

        $this->getJson(route('admin.districts.show', $this->district->id))->assertOk();

        $this->postJson(route('admin.districts.store'), [
            'name' => 'Actor created district',
            'is_enabled' => 1,
        ])->assertOk();

        $this->putJson(route('admin.districts.update', $this->district->id), [
            'name' => 'Actor updated district',
            'is_enabled' => 1,
        ])->assertOk();

        $empty = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Actor delete target',
        ]);

        $this->deleteJson(route('admin.districts.destroy', $empty->id))->assertOk();
    }

    public function test_destroy_rejects_district_with_attached_location_in_full_access_flow(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create();
        Location::factory()->forDistrict($district)->create(['name' => 'Attached object']);

        $this->deleteJson(route('admin.districts.destroy', $district->id))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя удалить район: к нему привязаны объекты');
    }

    public function test_districts_index_returns_403_without_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.districts.index'))->assertStatus(403);
    }

    public function test_districts_data_returns_403_without_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('admin.districts.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_districts_columns_settings_return_403_without_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('admin.districts.columns-settings.get'))->assertStatus(403);

        $this->postJson(route('admin.districts.columns-settings.save'), [
            'columns' => ['name' => true],
        ])->assertStatus(403);
    }

    public function test_districts_mutations_return_403_without_districts_view(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('admin.districts.show', $this->district->id))->assertStatus(403);

        $this->postJson(route('admin.districts.store'), [
            'name' => 'Forbidden',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->putJson(route('admin.districts.update', $this->district->id), [
            'name' => 'Forbidden',
            'is_enabled' => 1,
        ])->assertStatus(403);

        $this->deleteJson(route('admin.districts.destroy', $this->district->id))->assertStatus(403);
    }

    public function test_all_district_routes_require_auth(): void
    {
        auth()->logout();

        $calls = [
            fn () => $this->get(route('admin.districts.index')),
            fn () => $this->getJson(route('admin.districts.data', ['draw' => 1])),
            fn () => $this->getJson(route('admin.districts.columns-settings.get')),
            fn () => $this->postJson(route('admin.districts.columns-settings.save'), ['columns' => ['name' => true]]),
            fn () => $this->getJson(route('admin.districts.show', $this->district->id)),
            fn () => $this->postJson(route('admin.districts.store'), ['name' => 'Guest', 'is_enabled' => 1]),
            fn () => $this->putJson(route('admin.districts.update', $this->district->id), ['name' => 'Guest', 'is_enabled' => 1]),
            fn () => $this->deleteJson(route('admin.districts.destroy', $this->district->id)),
        ];

        foreach ($calls as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected status: ' . $status);
        }
    }
}
