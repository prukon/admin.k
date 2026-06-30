<?php

namespace Tests\Feature\Phone;

use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;

/**
 * Партнёрская форма админки: phone — сохранение и reload через edit JSON.
 */
final class PhoneFormPartnerPersistenceFeatureTest extends CrmTestCase
{
    use InteractsWithPhoneInput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_partner_store_and_edit_reload_return_saved_phone(): void
    {
        $phoneMasked = $this->randomRuPhoneMasked();
        $email = 'partner-phone-' . Str::lower(Str::random(8)) . '@example.test';

        $store = $this->postJson(route('admin.partner.store'), [
            'title'      => 'Phone Partner ' . Str::random(4),
            'sms_name'   => 'TESTPARTNER',
            'phone'      => $phoneMasked,
            'email'      => $email,
            'website'    => 'https://example.test',
            'order_by'   => 10,
            'is_enabled' => true,
        ])->assertCreated();

        $partnerId = (int) $store->json('partner.id');
        $edit = $this->getJson(route('admin.partner.edit', $partnerId))->assertOk();

        $this->assertSameNormalizedPhone($phoneMasked, $edit->json('phone'));
    }

    public function test_partner_update_and_edit_reload_return_updated_phone(): void
    {
        $phoneMasked = $this->randomRuPhoneMasked();

        $this->patchJson(route('admin.partner.update', $this->partner), [
            'title'      => $this->partner->title,
            'phone'      => $phoneMasked,
            'email'      => $this->partner->email ?? ('upd_' . Str::random(8) . '@example.test'),
            'is_enabled' => true,
            'order_by'   => 0,
        ])->assertOk();

        $edit = $this->getJson(route('admin.partner.edit', $this->partner))->assertOk();
        $this->assertSameNormalizedPhone($phoneMasked, $edit->json('phone'));
    }
}
