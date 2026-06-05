<?php

namespace Tests\Feature\Phone;

use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;

/**
 * Партнёрские формы: phone и ceo.phone — сохранение и reload через edit JSON.
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

    public function test_partner_store_and_edit_reload_return_saved_phones(): void
    {
        $phoneMasked = $this->randomRuPhoneMasked();
        $ceoMasked = $this->randomRuPhoneMasked();
        $email = 'partner-phone-' . Str::lower(Str::random(8)) . '@example.test';

        $store = $this->postJson(route('admin.partner.store'), [
            'business_type'       => 'company',
            'title'               => 'Phone Partner ' . Str::random(4),
            'organization_name'   => 'ООО Тест',
            'tax_id'              => '1234567890',
            'kpp'                 => '123456789',
            'registration_number' => '1234567890123',
            'sms_name'            => 'TESTPARTNER',
            'city'                => 'СПб',
            'zip'                 => '197350',
            'address'             => 'Невский пр., 1',
            'phone'               => $phoneMasked,
            'email'               => $email,
            'website'             => 'https://example.test',
            'bank_name'           => 'Банк',
            'bank_bik'            => '123456789',
            'bank_account'        => '12345678901234567890',
            'order_by'            => 10,
            'is_enabled'          => true,
            'ceo'                 => [
                'lastName'   => 'Иванов',
                'firstName'  => 'Иван',
                'middleName' => 'Иванович',
                'phone'      => $ceoMasked,
            ],
        ])->assertCreated();

        $partnerId = (int) $store->json('partner.id');
        $edit = $this->getJson(route('admin.partner.edit', $partnerId))->assertOk();

        $this->assertSameNormalizedPhone($phoneMasked, $edit->json('phone'));
        $this->assertSameNormalizedPhone($ceoMasked, $edit->json('ceo.phone'));
    }

    public function test_partner_update_and_edit_reload_return_updated_phones(): void
    {
        $phoneMasked = $this->randomRuPhoneMasked();
        $ceoMasked = $this->randomRuPhoneMasked();

        $this->patchJson(route('admin.partner.update', $this->partner), [
            'business_type'       => 'company',
            'title'               => $this->partner->title,
            'organization_name'   => $this->partner->organization_name ?? 'ООО Тест',
            'tax_id'              => $this->partner->tax_id ?? '1234567890',
            'kpp'                 => $this->partner->kpp ?? '123456789',
            'registration_number' => $this->partner->registration_number ?? '1234567890123',
            'phone'               => $phoneMasked,
            'email'               => $this->partner->email ?? ('upd_' . Str::random(8) . '@example.test'),
            'ceo'                 => [
                'lastName'   => 'Петров',
                'firstName'  => 'Пётр',
                'middleName' => 'Петрович',
                'phone'      => $ceoMasked,
            ],
            'is_enabled'          => true,
            'order_by'            => 0,
        ])->assertOk();

        $edit = $this->getJson(route('admin.partner.edit', $this->partner))->assertOk();
        $this->assertSameNormalizedPhone($phoneMasked, $edit->json('phone'));
        $this->assertSameNormalizedPhone($ceoMasked, $edit->json('ceo.phone'));
    }
}
