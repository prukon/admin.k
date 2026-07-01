<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Districts;

use App\Models\District;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт (postJson/putJson/deleteJson): JSON-структура, статусы 200/422, не пустой 200.
 */
final class DistrictsAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    private function grantLocationsViewTo(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_store_ajax_json_contract(): void
    {
        $response = $this->postJson(route('admin.districts.store'), [
            'name' => 'Ajax Contract District',
            'sort_order' => 2,
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Район создан')
            ->assertJsonStructure([
                'message',
                'district' => ['id', 'name', 'partner_id'],
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_store_validation_returns_422_with_field_errors(): void
    {
        $this->postJson(route('admin.districts.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_ajax_json_contract(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'До ajax update',
        ]);

        $this->putJson(route('admin.districts.update', $district), [
            'name' => 'После ajax update',
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Район обновлён');

        $this->assertSame('После ajax update', $district->fresh()->name);
    }

    public function test_destroy_ajax_json_contract(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'На удаление ajax',
        ]);

        $this->deleteJson(route('admin.districts.destroy', $district))
            ->assertOk()
            ->assertJsonPath('message', 'Район удалён')
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('districts', ['id' => $district->id]);
    }

    public function test_destroy_ajax_returns_422_when_locations_attached(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create();
        Location::factory()->forDistrict($district)->create(['name' => 'Attached']);

        $this->deleteJson(route('admin.districts.destroy', $district))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя удалить район: к нему привязаны объекты');
    }

    public function test_columns_settings_ajax_contract(): void
    {
        $this->getJson(route('admin.districts.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.districts.columns-settings.save'), [
            'columns' => [
                'name' => true,
                'sort_order' => false,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_data_endpoint_returns_datatable_json_not_empty(): void
    {
        District::factory()->forPartner($this->partner->id)->create([
            'name' => 'DT row district',
        ]);

        $response = $this->getJson(route('admin.districts.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);

        $this->assertGreaterThan(0, (int) $response->json('recordsTotal'));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_show_ajax_returns_entity_json_payload_with_location_ids_when_allowed(): void
    {
        $this->grantLocationsViewTo($this->user->role_id);

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Show Ajax District',
        ]);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Bound location',
        ]);

        $this->putJson(route('admin.districts.update', $district), [
            'name' => $district->name,
            'is_enabled' => 1,
            'location_ids' => [$location->id],
        ])->assertOk();

        $this->getJson(route('admin.districts.show', $district))
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'sort_order', 'is_enabled', 'location_ids'])
            ->assertJsonPath('id', $district->id)
            ->assertJsonPath('location_ids', [$location->id]);
    }

    public function test_authorized_user_all_endpoints_return_expected_status_not_500(): void
    {
        $district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Matrix district',
        ]);
        $disposable = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Matrix disposable district',
        ]);

        $matrix = [
            ['GET', route('admin.districts.index'), [], 200],
            ['GET', route('admin.districts.data', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.districts.columns-settings.get'), [], 200],
            ['POST', route('admin.districts.columns-settings.save'), ['columns' => ['name' => true]], 200],
            ['GET', route('logs.data.district', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.districts.show', $district), [], 200],
            ['POST', route('admin.districts.store'), [
                'name' => 'Matrix store district',
                'is_enabled' => 1,
            ], 200],
            ['PUT', route('admin.districts.update', $district), [
                'name' => 'Matrix updated district',
                'is_enabled' => 1,
            ], 200],
            ['DELETE', route('admin.districts.destroy', $disposable), [], 200],
        ];

        foreach ($matrix as [$method, $url, $data, $expectedStatus]) {
            $response = $this->json($method, $url, $data);

            $this->assertSame(
                $expectedStatus,
                $response->getStatusCode(),
                "{$method} {$url} → {$response->getStatusCode()}, body: " . mb_substr((string) $response->getContent(), 0, 200)
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }
}
