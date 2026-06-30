<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use App\Support\PartnerLegacyLegalFields;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Админка партнёров: убраны city/zip/ceo, подпись title «Название школы/секции», strip + сохранение в БД.
 */
final class PartnerAdminOperationalFieldsFeatureTest extends CrmTestCase
{
    private Partner $targetPartner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asSuperadmin();

        $this->targetPartner = Partner::factory()->create([
            'title' => 'Operational fields partner',
            'email' => 'ops_' . Str::lower(Str::random(8)) . '@example.test',
            'sms_name' => 'OPSPARTNER',
            'phone' => '+79990001122',
            'website' => 'https://example.test',
            'order_by' => 5,
            'is_enabled' => true,
        ]);
    }

    public function test_partners_index_modal_shows_school_title_label_without_removed_fields(): void
    {
        $this->get(route('admin.partner.index'))
            ->assertOk()
            ->assertSee('Название школы/секции', false)
            ->assertSee('id="editPartnerModal"', false)
            ->assertSee('id="createPartnerModal"', false)
            ->assertDontSee('name="city"', false)
            ->assertDontSee('name="zip"', false)
            ->assertDontSee('name="ceo[lastName]"', false)
            ->assertDontSee('edit-city', false)
            ->assertDontSee('Данные руководителя', false);
    }

    public function test_edit_json_returns_only_allowed_operational_fields(): void
    {
        $partner = Partner::factory()->create([
            'city' => 'СПб',
            'zip' => '197350',
            'ceo' => [
                'lastName' => 'Иванов',
                'firstName' => 'Иван',
                'middleName' => 'Иванович',
                'phone' => '+79991112233',
            ],
        ]);

        $json = $this->getJson(route('admin.partner.edit', $partner))
            ->assertOk()
            ->json();

        $this->assertSame([
            'id',
            'title',
            'sms_name',
            'phone',
            'email',
            'website',
            'order_by',
            'is_enabled',
        ], array_keys($json));

        $this->assertSame($partner->id, $json['id']);
        $this->assertSame($partner->title, $json['title']);
        $this->assertSame($partner->sms_name, $json['sms_name']);
        $this->assertSame($partner->phone, $json['phone']);
        $this->assertSame($partner->email, $json['email']);
        $this->assertSame($partner->website, $json['website']);
        $this->assertSame($partner->order_by, $json['order_by']);
        $this->assertTrue($json['is_enabled']);
    }

    public function test_store_ajax_persists_all_operational_fields(): void
    {
        $email = 'ops_store_' . Str::lower(Str::random(8)) . '@example.test';
        $payload = [
            'title' => 'Новая школа ' . Str::random(4),
            'sms_name' => 'NEWSCHOOL',
            'phone' => '+79998887766',
            'email' => $email,
            'website' => 'https://school.example.test',
            'order_by' => 42,
            'is_enabled' => true,
        ];

        $this->postJson(route('admin.partner.store'), $payload)
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'partner'])
            ->assertJsonPath('message', 'Партнёр успешно создан');

        $created = Partner::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertSame($payload['title'], $created->title);
        $this->assertSame($payload['sms_name'], $created->sms_name);
        $this->assertSame($payload['phone'], $created->phone);
        $this->assertSame($payload['website'], $created->website);
        $this->assertSame($payload['order_by'], $created->order_by);
        $this->assertTrue($created->is_enabled);
    }

    public function test_update_ajax_persists_all_operational_fields(): void
    {
        $payload = [
            'title' => 'Обновлённая школа ' . Str::random(4),
            'sms_name' => 'UPDATED',
            'phone' => '+79997776655',
            'email' => $this->targetPartner->email,
            'website' => 'https://updated.example.test',
            'order_by' => 99,
            'is_enabled' => false,
        ];

        $this->patchJson(route('admin.partner.update', $this->targetPartner), $payload)
            ->assertOk()
            ->assertJsonStructure(['message', 'partner'])
            ->assertJsonPath('message', 'Партнёр успешно обновлён');

        $fresh = $this->targetPartner->fresh();
        $this->assertSame($payload['title'], $fresh->title);
        $this->assertSame($payload['sms_name'], $fresh->sms_name);
        $this->assertSame($payload['phone'], $fresh->phone);
        $this->assertSame($payload['website'], $fresh->website);
        $this->assertSame($payload['order_by'], $fresh->order_by);
        $this->assertFalse($fresh->is_enabled);
    }

    public function test_update_ajax_does_not_wipe_existing_city_zip_ceo_in_database(): void
    {
        $this->targetPartner->update([
            'city' => 'Москва',
            'zip' => '101000',
            'ceo' => [
                'lastName' => 'Сидоров',
                'firstName' => 'Сидор',
                'middleName' => 'Сидорович',
                'phone' => '+79991234567',
            ],
        ]);

        $this->patchJson(route('admin.partner.update', $this->targetPartner), $this->validPartnerPayload([
            'title' => 'Title after update ' . Str::random(4),
            'email' => $this->targetPartner->email,
        ]))->assertOk();

        $fresh = $this->targetPartner->fresh();
        $this->assertSame('Москва', $fresh->city);
        $this->assertSame('101000', $fresh->zip);
        $this->assertSame('Сидоров', $fresh->ceo['lastName'] ?? null);
        $this->assertSame('Сидор', $fresh->ceo['firstName'] ?? null);
    }

    public function test_store_ajax_strips_city_zip_ceo_from_payload(): void
    {
        $email = 'strip_ops_ajax_' . Str::lower(Str::random(8)) . '@example.test';

        $this->postJson(route('admin.partner.store'), array_merge($this->validPartnerPayload([
            'email' => $email,
        ]), [
            'city' => 'Казань',
            'zip' => '420000',
            'ceo' => [
                'lastName' => 'Петров',
                'firstName' => 'Пётр',
                'middleName' => 'Петрович',
                'phone' => '+79990000000',
            ],
        ]))->assertStatus(201);

        $created = Partner::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertNull($created->city);
        $this->assertNull($created->zip);
        $this->assertNull($created->ceo);
    }

    public function test_store_ajax_validation_failure_uses_school_title_attribute(): void
    {
        $response = $this->postJson(route('admin.partner.store'), [
            'title' => '',
            'email' => 'bad',
            'is_enabled' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'email']);

        $titleErrors = $response->json('errors.title');
        $this->assertIsArray($titleErrors);
        $this->assertNotEmpty($titleErrors);
        $this->assertStringContainsString('Название школы/секции', (string) $titleErrors[0]);
    }

    public function test_legacy_keys_constant_includes_discarded_operational_fields(): void
    {
        $this->assertContains('city', PartnerLegacyLegalFields::KEYS);
        $this->assertContains('zip', PartnerLegacyLegalFields::KEYS);
        $this->assertContains('ceo', PartnerLegacyLegalFields::KEYS);
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
