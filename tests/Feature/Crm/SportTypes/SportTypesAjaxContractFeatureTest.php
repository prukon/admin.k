<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SportTypes;

use App\Models\SportType;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт (postJson/putJson/deleteJson): JSON-структура, статусы 200/422, не пустой 200.
 */
final class SportTypesAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions(['sport_types.view', 'sport_types.manage']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_store_ajax_json_contract(): void
    {
        $response = $this->postJson(route('admin.sport-types.store'), [
            'name' => 'Ajax Contract Sport',
            'description' => 'Описание',
            'sort' => 2,
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Вид спорта создан')
            ->assertJsonStructure([
                'message',
                'sport_type' => ['id', 'name', 'partner_id'],
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_store_validation_returns_422_with_field_errors(): void
    {
        $this->postJson(route('admin.sport-types.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_ajax_json_contract(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'До ajax update',
        ]);

        $this->putJson(route('admin.sport-types.update', $sportType), [
            'name' => 'После ajax update',
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Вид спорта обновлён');

        $this->assertSame('После ajax update', $sportType->fresh()->name);
    }

    public function test_destroy_ajax_json_contract(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление ajax',
        ]);

        $this->deleteJson(route('admin.sport-types.destroy', $sportType))
            ->assertOk()
            ->assertJsonPath('message', 'Вид спорта удалён')
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('sport_types', ['id' => $sportType->id]);
    }

    public function test_columns_settings_ajax_contract(): void
    {
        $this->getJson(route('admin.sport-types.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.sport-types.columns-settings.save'), [
            'columns' => [
                'name' => true,
                'sort' => false,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_data_endpoint_returns_datatable_json_not_empty(): void
    {
        SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'DT row sport',
        ]);

        $response = $this->getJson(route('admin.sport-types.data', [
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

    public function test_show_ajax_returns_entity_json_payload(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show Ajax Sport',
        ]);

        $this->getJson(route('admin.sport-types.show', $sportType))
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'description', 'sort', 'is_enabled'])
            ->assertJsonPath('id', $sportType->id)
            ->assertJsonPath('name', 'Show Ajax Sport');
    }

    public function test_authorized_user_all_endpoints_return_expected_status_not_500(): void
    {
        $sportType = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Matrix sport',
        ]);
        $disposable = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Matrix disposable',
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'sport_type_id' => $sportType->id,
        ]);

        $matrix = [
            ['GET', route('admin.sport-types.index'), [], 200],
            ['GET', route('admin.sport-types.data', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.sport-types.columns-settings.get'), [], 200],
            ['POST', route('admin.sport-types.columns-settings.save'), ['columns' => ['name' => true]], 200],
            ['GET', route('logs.data.sport-type', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.sport-types.show', $sportType), [], 200],
            ['POST', route('admin.sport-types.store'), [
                'name' => 'Matrix store sport',
                'is_enabled' => 1,
            ], 200],
            ['PUT', route('admin.sport-types.update', $sportType), [
                'name' => 'Matrix updated sport',
                'is_enabled' => 1,
            ], 200],
            ['DELETE', route('admin.sport-types.destroy', $disposable), [], 200],
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
