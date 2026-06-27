<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Models\PartnerLegalEntity;
use App\Services\Tinkoff\SmRegisterClient;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;

/**
 * sm-register / sm-patch / sm-refresh / sm-pull для юр. лиц (AJAX-контракт + доступ).
 */
final class LegalEntitiesSmRegisterFeatureTest extends CrmTestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSmPayload(array $overrides = []): array
    {
        return array_merge([
            'business_type' => 'OOO',
            'title' => 'ООО SM Test',
            'organization_name' => 'ООО SM Test',
            'email' => 'sm@example.test',
            'tax_id' => '7700000001',
            'registration_number' => '1234567890123',
            'address' => 'ул. Пушкина, д. 1',
            'city' => 'Москва',
            'zip' => '101000',
            'bank_name' => 'Т-Банк',
            'bank_bik' => '044525974',
            'bank_account' => '40702810900000000001',
            'sm_details_template' => 'Назначение платежа',
            'phone' => '+79990000000',
            'website' => 'https://example.test',
            'kpp' => '770101001',
        ], $overrides);
    }

    private function bindSmMock(): Mockery\MockInterface
    {
        $sm = Mockery::mock(SmRegisterClient::class);
        $this->app->instance(SmRegisterClient::class, $sm);

        return $sm;
    }

    public function test_sm_register_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('register')
            ->once()
            ->andReturn(['shopCode' => 'SC-LEGAL-001', 'status' => 'REGISTERED']);

        $this->postJson(route('admin.legal-entities.sm-register', $entity), $this->validSmPayload())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('shopCode', 'SC-LEGAL-001')
            ->assertJsonPath('status', 'REGISTERED');

        $entity->refresh();
        $this->assertSame('SC-LEGAL-001', $entity->tinkoff_shop_code);
    }

    public function test_sm_register_when_already_registered_returns_422(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-EXISTING')->create();

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('register');

        $this->postJson(route('admin.legal-entities.sm-register', $entity), $this->validSmPayload())
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_sm_register_validation_returns_422_with_field_errors(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create();

        $this->postJson(route('admin.legal-entities.sm-register', $entity), [
            'business_type' => 'OOO',
            'title' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'organization_name', 'email']);
    }

    public function test_sm_patch_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PATCH-001')->create();

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')
            ->once()
            ->with('SC-PATCH-001', Mockery::type('array'))
            ->andReturn(['ok' => true]);

        $this->postJson(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload([
            'title' => 'ООО SM Patched',
            'organization_name' => 'ООО SM Patched',
        ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('ООО SM Patched', $entity->fresh()->title);
    }

    public function test_sm_patch_without_shop_code_returns_422(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->postJson(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload())
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_sm_refresh_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-REFRESH')->create([
            'sm_register_status' => 'PENDING',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('getStatus')
            ->once()
            ->with('SC-REFRESH')
            ->andReturn(['status' => 'ACTIVE']);

        $this->postJson(route('admin.legal-entities.sm-refresh', $entity))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'ACTIVE');

        $this->assertSame('ACTIVE', $entity->fresh()->sm_register_status);
    }

    public function test_sm_pull_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PULL')->create([
            'organization_name' => 'Старое имя',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('getStatus')
            ->once()
            ->with('SC-PULL')
            ->andReturn([
                'fullName' => 'Новое имя из банка',
                'inn' => '7700000099',
                'kpp' => '770101001',
                'ogrn' => '1234567890123',
                'status' => 'ACTIVE',
                'addresses' => [[
                    'city' => 'Москва',
                    'zip' => '101000',
                    'street' => 'ул. Банковская, 2',
                ]],
                'bankAccount' => [
                    'bankName' => 'Т-Банк',
                    'bik' => '044525974',
                    'account' => '40702810900000000002',
                    'details' => 'Назначение из банка',
                ],
                'phones' => [['phone' => '+79991112233']],
            ]);

        $this->postJson(route('admin.legal-entities.sm-pull', $entity))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['changed']);

        $entity->refresh();
        $this->assertSame('Новое имя из банка', $entity->organization_name);
    }

    public function test_sm_register_non_ajax_redirects_to_show_after_success(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('register')
            ->once()
            ->andReturn(['shopCode' => 'SC-NON-AJAX', 'status' => 'REGISTERED']);

        $this->post(route('admin.legal-entities.sm-register', $entity), $this->validSmPayload())
            ->assertRedirect(route('admin.legal-entities.show', $entity))
            ->assertSessionHas('ok');

        $this->assertSame('SC-NON-AJAX', $entity->fresh()->tinkoff_shop_code);
    }
}
