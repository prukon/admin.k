<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net: store/update партнёра без X-Requested-With → redirect, запись в БД.
 */
final class PartnerAdminNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_store_non_ajax_redirects_and_creates_partner(): void
    {
        $email = 'non_ajax_store_' . Str::lower(Str::random(8)) . '@example.test';
        $title = 'Партнёр без ajax';

        $this->post(route('admin.partner.store'), $this->validPartnerPayload([
            'title' => $title,
            'email' => $email,
        ]))
            ->assertRedirect(route('admin.partner.index'))
            ->assertSessionHas('ok');

        $partner = Partner::query()->where('email', $email)->first();
        $this->assertNotNull($partner);
        $this->assertSame($title, $partner->title);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.partner.index'))
            ->post(route('admin.partner.store'), [
                'title' => '',
                'email' => 'bad',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['title', 'email']);
    }

    public function test_update_non_ajax_redirects_and_updates_partner(): void
    {
        $partner = Partner::factory()->create([
            'title' => 'До non-ajax update',
        ]);

        $newTitle = 'После non-ajax update';

        $this->patch(route('admin.partner.update', $partner), $this->validPartnerPayload([
            'title' => $newTitle,
            'email' => $partner->email,
        ]))
            ->assertRedirect(route('admin.partner.index'))
            ->assertSessionHas('ok');

        $this->assertSame($newTitle, $partner->fresh()->title);
    }

    public function test_update_non_ajax_strips_legacy_fields(): void
    {
        $partner = Partner::factory()->create([
            'tax_id' => '2222222222',
            'organization_name' => 'Было',
        ]);

        $this->patch(route('admin.partner.update', $partner), array_merge(
            $this->validPartnerPayload([
                'title' => 'Non ajax strip',
                'email' => $partner->email,
            ]),
            ['tax_id' => '7777777777'],
        ))
            ->assertRedirect(route('admin.partner.index'));

        $fresh = $partner->fresh();
        $this->assertSame('Non ajax strip', $fresh->title);
        $this->assertSame('2222222222', $fresh->tax_id);
    }

    public function test_update_non_ajax_preserves_existing_city_zip_ceo_in_database(): void
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

        $this->patch(route('admin.partner.update', $partner), array_merge(
            $this->validPartnerPayload([
                'title' => 'Non ajax preserve ops',
                'email' => $partner->email,
            ]),
            [
                'city' => 'Казань',
                'zip' => '420000',
                'ceo' => [
                    'lastName' => 'Петров',
                    'firstName' => 'Пётр',
                    'middleName' => 'Петрович',
                    'phone' => '+79990000000',
                ],
            ],
        ))
            ->assertRedirect(route('admin.partner.index'));

        $fresh = $partner->fresh();
        $this->assertSame('Non ajax preserve ops', $fresh->title);
        $this->assertSame('СПб', $fresh->city);
        $this->assertSame('197350', $fresh->zip);
        $this->assertSame('Иванов', $fresh->ceo['lastName'] ?? null);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $partner = Partner::factory()->create(['title' => 'Валидация non-ajax']);

        $this->from(route('admin.partner.index'))
            ->patch(route('admin.partner.update', $partner), [
                'title' => '',
                'email' => 'bad',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['title', 'email']);

        $this->assertSame('Валидация non-ajax', $partner->fresh()->title);
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
