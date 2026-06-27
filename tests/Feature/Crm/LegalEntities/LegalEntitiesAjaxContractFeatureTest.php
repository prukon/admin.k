<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт (postJson/putJson/deleteJson + X-Requested-With): JSON-структура, статусы 200/422.
 */
final class LegalEntitiesAjaxContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions(['legal_entities.view', 'legal_entities.manage']);
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
        $response = $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'ANO',
            'title' => 'АНО Ajax Contract',
            'tax_id' => '7701234567',
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Юр. лицо создано')
            ->assertJsonStructure([
                'message',
                'legal_entity' => ['id', 'title', 'business_type', 'partner_id'],
            ])
            ->assertJsonPath('legal_entity.title', 'АНО Ajax Contract');

        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_store_validation_returns_422_with_field_errors(): void
    {
        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'UNKNOWN',
            'title' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_type', 'title']);
    }

    public function test_show_ajax_returns_entity_json_payload(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Show Ajax Entity',
            'business_type' => PartnerLegalEntityBusinessType::NKO,
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'business_type',
                'title',
                'organization_name',
                'tax_id',
                'is_default',
                'is_enabled',
            ])
            ->assertJsonPath('id', $entity->id)
            ->assertJsonPath('title', 'Show Ajax Entity')
            ->assertJsonPath('business_type', 'NKO');
    }

    public function test_update_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'До ajax update',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'OOO',
            'title' => 'После ajax update',
            'is_default' => true,
            'is_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Юр. лицо обновлено');

        $this->assertSame('После ajax update', $entity->fresh()->title);
    }

    public function test_destroy_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'На удаление ajax',
        ]);

        $this->deleteJson(route('admin.legal-entities.destroy', $entity))
            ->assertOk()
            ->assertJsonPath('message', 'Юр. лицо удалено')
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('partner_legal_entities', ['id' => $entity->id]);
    }

    public function test_columns_settings_ajax_contract(): void
    {
        $this->getJson(route('admin.legal-entities.columns-settings.get'))
            ->assertOk()
            ->assertJson([]);

        $this->postJson(route('admin.legal-entities.columns-settings.save'), [
            'columns' => [
                'title' => true,
                'tax_id' => false,
                'teams_count' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('admin.legal-entities.columns-settings.get'))
            ->assertOk()
            ->assertJsonFragment(['title' => true, 'tax_id' => false, 'teams_count' => true]);
    }

    public function test_columns_settings_save_validation_returns_422(): void
    {
        $this->postJson(route('admin.legal-entities.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_data_endpoint_returns_datatable_json_not_empty(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'DT row']);

        $response = $this->getJson(route('admin.legal-entities.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'business_type_label',
                        'show_url',
                    ],
                ],
            ]);

        $this->assertGreaterThan(0, (int) $response->json('recordsTotal'));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_logs_data_returns_datatable_json(): void
    {
        $this->getJson(route('logs.data.legal-entity', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_authorized_user_all_endpoints_return_expected_status_not_500(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'Matrix entity']);
        $disposable = PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'Matrix disposable']);

        $matrix = [
            ['GET', route('admin.legal-entities.index'), [], 200],
            ['GET', route('admin.legal-entities.data', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.legal-entities.columns-settings.get'), [], 200],
            ['POST', route('admin.legal-entities.columns-settings.save'), ['columns' => ['title' => true]], 200],
            ['GET', route('logs.data.legal-entity', ['draw' => 1, 'start' => 0, 'length' => 10]), [], 200],
            ['GET', route('admin.legal-entities.show', $entity), [], 200],
            ['POST', route('admin.legal-entities.store'), [
                'business_type' => 'OOO',
                'title' => 'Matrix store',
                'is_enabled' => 1,
            ], 200],
            ['PUT', route('admin.legal-entities.update', $entity), [
                'business_type' => 'OOO',
                'title' => 'Matrix updated',
                'is_default' => true,
                'is_enabled' => true,
            ], 200],
            ['DELETE', route('admin.legal-entities.destroy', $disposable), [], 200],
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
