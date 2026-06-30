<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Админка партнёров: отказ от legacy-полей partners, AJAX-контракт edit/store/update.
 */
final class PartnerAdminLegacyFieldsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asSuperadmin();
    }

    public function test_edit_json_payload_excludes_legacy_legal_fields(): void
    {
        $partner = Partner::factory()->create([
            'tax_id' => '7707083893',
            'organization_name' => 'ООО Legacy',
            'business_type' => 'company',
        ]);

        $json = $this->getJson(route('admin.partner.edit', $partner))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('title', $json);
        $this->assertArrayNotHasKey('city', $json);
        $this->assertArrayNotHasKey('zip', $json);
        $this->assertArrayNotHasKey('ceo', $json);
        $this->assertArrayNotHasKey('tax_id', $json);
        $this->assertArrayNotHasKey('organization_name', $json);
        $this->assertArrayNotHasKey('business_type', $json);
        $this->assertArrayNotHasKey('bank_name', $json);
        $this->assertArrayNotHasKey('vat', $json);
    }

    public function test_store_ajax_strips_unused_operational_fields(): void
    {
        $email = 'strip_ops_' . Str::lower(Str::random(8)) . '@example.test';

        $this->postJson(route('admin.partner.store'), array_merge($this->validPartnerPayload([
            'email' => $email,
        ]), [
            'city' => 'СПб',
            'zip' => '197350',
            'ceo' => [
                'lastName' => 'Иванов',
                'firstName' => 'Иван',
                'middleName' => 'Иванович',
                'phone' => '+79991112233',
            ],
        ]))
            ->assertStatus(201);

        $created = Partner::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertNull($created->city);
        $this->assertNull($created->zip);
        $this->assertNull($created->ceo);
    }

    public function test_store_ajax_strips_legacy_fields_from_payload(): void
    {
        $email = 'strip_store_' . Str::lower(Str::random(8)) . '@example.test';
        $title = 'Store Strip ' . Str::random(6);

        $this->postJson(route('admin.partner.store'), array_merge($this->validPartnerPayload([
            'title' => $title,
            'email' => $email,
        ]), [
            'tax_id' => '1234567890',
            'organization_name' => 'Не должно сохраниться',
            'business_type' => 'company',
            'bank_account' => '40702810900000000001',
        ]))
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'partner'])
            ->assertJsonPath('message', 'Партнёр успешно создан');

        $created = Partner::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertSame($title, $created->title);
        $this->assertNull($created->tax_id);
        $this->assertNull($created->organization_name);
        $this->assertNull($created->bank_account);
    }

    public function test_update_ajax_strips_legacy_fields_from_payload(): void
    {
        $partner = Partner::factory()->create([
            'tax_id' => '1111111111',
            'organization_name' => 'Старое юр. имя',
            'business_type' => 'company',
        ]);

        $newTitle = 'Updated Strip ' . Str::random(6);

        $this->patchJson(route('admin.partner.update', $partner), array_merge(
            $this->validPartnerPayload([
                'title' => $newTitle,
                'email' => $partner->email,
            ]),
            [
                'tax_id' => '9999999999',
                'organization_name' => 'Взлом',
                'vat' => 20,
            ],
        ))
            ->assertOk()
            ->assertJsonStructure(['message', 'partner'])
            ->assertJsonPath('message', 'Партнёр успешно обновлён');

        $fresh = $partner->fresh();
        $this->assertSame($newTitle, $fresh->title);
        $this->assertSame('1111111111', $fresh->tax_id);
        $this->assertSame('Старое юр. имя', $fresh->organization_name);
        $this->assertNull($fresh->vat);
    }

    public function test_store_ajax_validation_failure_returns_422(): void
    {
        $this->postJson(route('admin.partner.store'), [
            'title' => '',
            'email' => 'not-email',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'email']);
    }

    public function test_update_ajax_validation_failure_returns_422(): void
    {
        $partner = Partner::factory()->create();

        $this->patchJson(route('admin.partner.update', $partner), [
            'title' => '',
            'email' => 'bad',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'email']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPartnerPayload(array $overrides = []): array
    {
        $email = $overrides['email'] ?? ('partner_' . Str::lower(Str::random(8)) . '@example.test');

        return array_merge([
            'title' => 'Тестовый партнёр',
            'sms_name' => 'TESTPARTNER',
            'phone' => '+79990001122',
            'email' => $email,
            'website' => 'https://example.test',
            'order_by' => 10,
            'is_enabled' => true,
        ], $overrides);
    }
}
