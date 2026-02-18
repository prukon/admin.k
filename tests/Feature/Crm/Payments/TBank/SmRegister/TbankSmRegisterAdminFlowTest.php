<?php

namespace Tests\Feature\Crm\Payments\TBank\SmRegister;

use App\Services\Tinkoff\SmRegisterClient;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;

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

    public function test_sm_register_success_saves_shopcode_and_partner_fields(): void
    {
        $this->asSuperadmin();

        $sm = Mockery::mock(SmRegisterClient::class);
        $sm->shouldReceive('register')->andReturn(['shopCode' => 'SHOP-777', 'status' => 'registered']);
        $this->app->instance(SmRegisterClient::class, $sm);

        $this->post(route('tinkoff.partners.smRegister', ['id' => $this->partner->id]), $this->validRegisterPayload())
            ->assertStatus(302)
            ->assertSessionHas('ok');

        $this->partner->refresh();
        $this->assertSame('SHOP-777', (string) $this->partner->tinkoff_partner_id);
        $this->assertSame('Назначение платежа', (string) $this->partner->sm_details_template);
        $this->assertSame('044525974', (string) $this->partner->bank_bik);
    }

    public function test_sm_register_validation_errors_return_302_with_session_errors(): void
    {
        $this->asSuperadmin();

        $payload = $this->validRegisterPayload();
        unset($payload['tax_id']);

        $this->post(route('tinkoff.partners.smRegister', ['id' => $this->partner->id]), $payload)
            ->assertStatus(302)
            ->assertSessionHasErrors(['tax_id']);
    }

    public function test_sm_register_client_error_returns_422_json_for_ajax(): void
    {
        $this->asSuperadmin();

        $sm = Mockery::mock(SmRegisterClient::class);
        $sm->shouldReceive('register')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(SmRegisterClient::class, $sm);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('tinkoff.partners.smRegister', ['id' => $this->partner->id]), $this->validRegisterPayload())
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_sm_patch_requires_existing_partner_id(): void
    {
        $this->asSuperadmin();

        $this->partner->tinkoff_partner_id = null;
        $this->partner->save();

        $this->post(route('tinkoff.partners.smPatch', ['id' => $this->partner->id]), $this->validRegisterPayload())
            ->assertStatus(302)
            ->assertSessionHasErrors(['sm']);
    }

    public function test_sm_refresh_requires_existing_partner_id(): void
    {
        $this->asSuperadmin();

        $this->partner->tinkoff_partner_id = null;
        $this->partner->save();

        $this->post(route('tinkoff.partners.smRefresh', ['id' => $this->partner->id]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['sm']);
    }
}

