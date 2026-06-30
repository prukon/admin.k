<?php

namespace Tests\Feature\Crm\Payments\TBank\SmRegister;

use App\Services\Tinkoff\SmRegisterClient;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Legacy partner sm-register маршруты отключены — регистрация в справочнике «Юр. лица».
 */
class TbankSmRegisterAdminFlowTest extends CrmTestCase
{
    private function validRegisterPayload(array $overrides = []): array
    {
        return array_merge([
            'business_type' => 'company',
            'title' => 'ООО Ромашка',
            'organization_name' => 'ООО Ромашка',
            'email' => 'a@example.test',
            'tax_id' => '7700000000',
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

    public function test_legacy_sm_register_redirects_to_legal_entities_index(): void
    {
        $this->asSuperadmin();

        $originalShopCode = $this->partner->tinkoff_partner_id;

        $this->post(route('tinkoff.partners.smRegister', ['id' => $this->partner->id]), $this->validRegisterPayload())
            ->assertRedirect(route('admin.legal-entities.index'))
            ->assertSessionHas('warning');

        $this->partner->refresh();
        $this->assertSame($originalShopCode, $this->partner->tinkoff_partner_id);
    }

    public function test_legacy_sm_register_returns_410_json_for_ajax(): void
    {
        $this->asSuperadmin();

        $sm = Mockery::mock(SmRegisterClient::class);
        $sm->shouldNotReceive('register');
        $this->app->instance(SmRegisterClient::class, $sm);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('tinkoff.partners.smRegister', ['id' => $this->partner->id]), $this->validRegisterPayload())
            ->assertStatus(410)
            ->assertJson(['ok' => false]);
    }

    public function test_legacy_sm_patch_is_deprecated(): void
    {
        $this->asSuperadmin();

        $this->post(route('tinkoff.partners.smPatch', ['id' => $this->partner->id]), $this->validRegisterPayload())
            ->assertRedirect(route('admin.legal-entities.index'))
            ->assertSessionHas('warning');
    }

    public function test_legacy_sm_refresh_is_deprecated(): void
    {
        $this->asSuperadmin();

        $this->post(route('tinkoff.partners.smRefresh', ['id' => $this->partner->id]))
            ->assertRedirect(route('admin.legal-entities.index'))
            ->assertSessionHas('warning');
    }
}
