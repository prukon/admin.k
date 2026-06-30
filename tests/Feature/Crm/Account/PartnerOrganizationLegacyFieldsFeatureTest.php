<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Partner;
use App\Models\User;
use App\Support\PartnerLegacyLegalFields;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Учётная запись → Организация: отказ от legacy-полей partners, AJAX-контракт, доступ.
 */
final class PartnerOrganizationLegacyFieldsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_organization_page_shows_legal_entities_hint_without_legacy_fields(): void
    {
        $this->grantPartnerView($this->user);

        $this->get(route('admin.cur.company.edit'))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('activeTab', 'partner')
            ->assertSee('Юр. лица', false)
            ->assertSee('name="title"', false)
            ->assertDontSee('name="tax_id"', false)
            ->assertDontSee('name="business_type"', false)
            ->assertDontSee('name="organization_name"', false)
            ->assertDontSee('name="bank_account"', false);
    }

    public function test_ajax_update_updates_allowed_fields_and_returns_json_contract(): void
    {
        $this->grantPartnerView($this->user);
        $this->grantPartnerUpdate($this->user);

        $payload = $this->validOrganizationPayload([
            'title' => 'Ajax Org ' . Str::random(8),
        ]);

        $this->patchJson(route('admin.cur.partner.update', $this->partner), $payload)
            ->assertOk()
            ->assertJsonStructure(['success', 'message'])
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('partners', [
            'id' => $this->partner->id,
            'title' => $payload['title'],
            'email' => $payload['email'],
        ]);
    }

    public function test_ajax_update_strips_legacy_legal_fields_from_payload(): void
    {
        $this->grantPartnerView($this->user);
        $this->grantPartnerUpdate($this->user);

        $this->partner->update([
            'tax_id' => '1111111111',
            'organization_name' => 'Legacy Org Name',
            'business_type' => 'company',
            'kpp' => '123456789',
            'registration_number' => '1234567890123',
            'address' => 'Legacy address',
            'bank_name' => 'Legacy Bank',
            'bank_bik' => '044525974',
            'bank_account' => '40702810900000000001',
            'vat' => 20,
        ]);

        $newTitle = 'Strip Legacy ' . Str::random(6);

        $this->patchJson(route('admin.cur.partner.update', $this->partner), array_merge(
            $this->validOrganizationPayload(['title' => $newTitle]),
            [
                'tax_id' => '9999999999',
                'organization_name' => 'Hacked Org',
                'business_type' => 'individual_entrepreneur',
                'kpp' => '999999999',
                'registration_number' => '9999999999999',
                'address' => 'Hacked address',
                'bank_name' => 'Hacked Bank',
                'bank_bik' => '111111111',
                'bank_account' => '11111111111111111111',
                'vat' => 10,
            ],
        ))->assertOk();

        $fresh = $this->partner->fresh();
        $this->assertSame($newTitle, $fresh->title);
        $this->assertSame('1111111111', $fresh->tax_id);
        $this->assertSame('Legacy Org Name', $fresh->organization_name);
        $this->assertSame('company', $fresh->business_type);
        $this->assertSame('123456789', $fresh->kpp);
        $this->assertSame('1234567890123', $fresh->registration_number);
        $this->assertSame('Legacy address', $fresh->address);
        $this->assertSame('Legacy Bank', $fresh->bank_name);
        $this->assertSame('044525974', $fresh->bank_bik);
        $this->assertSame('40702810900000000001', $fresh->bank_account);
        $this->assertSame(20, (int) $fresh->vat);
    }

    public function test_ajax_update_strips_city_zip_ceo_and_preserves_existing_db_values(): void
    {
        $this->grantPartnerView($this->user);
        $this->grantPartnerUpdate($this->user);

        $this->partner->update([
            'city' => 'СПб',
            'zip' => '197350',
            'ceo' => [
                'lastName' => 'Иванов',
                'firstName' => 'Иван',
                'middleName' => 'Иванович',
                'phone' => '+79991112233',
            ],
        ]);

        $newTitle = 'Strip Ops ' . Str::random(6);

        $this->patchJson(route('admin.cur.partner.update', $this->partner), array_merge(
            $this->validOrganizationPayload(['title' => $newTitle]),
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
        ))->assertOk();

        $fresh = $this->partner->fresh();
        $this->assertSame($newTitle, $fresh->title);
        $this->assertSame('СПб', $fresh->city);
        $this->assertSame('197350', $fresh->zip);
        $this->assertSame('Иванов', $fresh->ceo['lastName'] ?? null);
    }

    public function test_ajax_update_validation_failure_returns_422_with_field_errors(): void
    {
        $this->grantPartnerView($this->user);
        $this->grantPartnerUpdate($this->user);

        $this->patchJson(route('admin.cur.partner.update', $this->partner), [
            'title' => '',
            'email' => 'not-an-email',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'email']);
    }

    public function test_legacy_keys_constant_matches_stripped_fields(): void
    {
        $this->assertSame([
            'business_type',
            'organization_name',
            'tax_id',
            'kpp',
            'registration_number',
            'address',
            'bank_name',
            'bank_bik',
            'bank_account',
            'vat',
            'city',
            'zip',
            'ceo',
        ], PartnerLegacyLegalFields::KEYS);
    }

    public function test_guest_cannot_access_organization_endpoints(): void
    {
        Auth::logout();

        foreach ($this->sectionRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'text/html'],
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}",
            );
        }
    }

    public function test_user_without_account_partner_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('account.partner.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->sectionRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без account.partner.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}",
            );
        }
    }

    public function test_user_with_view_only_cannot_update_partner(): void
    {
        $actor = $this->createUserWithoutPermission('account.partner.update', $this->partner);
        $this->grantPartnerView($actor);
        $this->actingAs($actor);

        $this->get(route('admin.cur.company.edit'))->assertOk();

        $this->patchJson(route('admin.cur.partner.update', $this->partner), $this->validOrganizationPayload())
            ->assertForbidden();
    }

    public function test_partner_update_forbidden_for_foreign_partner_id(): void
    {
        $this->grantPartnerView($this->user);
        $this->grantPartnerUpdate($this->user);

        $originalTitle = $this->foreignPartner->title;

        $this->patchJson(route('admin.cur.partner.update', $this->foreignPartner), $this->validOrganizationPayload([
            'title' => 'HackOrg_' . Str::random(8),
        ]))
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Доступ запрещён.',
            ]);

        $this->assertSame($originalTitle, $this->foreignPartner->fresh()->title);
    }

    /**
     * @return array<string, mixed>
     */
    private function validOrganizationPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Школа ' . Str::random(8),
            'email' => 'org_' . Str::lower(Str::random(8)) . '@example.test',
            'phone' => '+79990001122',
            'website' => 'https://example.test',
            'sms_name' => 'SMSORG',
        ], $overrides);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function sectionRoutes(): array
    {
        return [
            ['method' => 'GET', 'url' => route('admin.cur.company.edit')],
            [
                'method' => 'PATCH',
                'url' => route('admin.cur.partner.update', $this->partner),
                'data' => $this->validOrganizationPayload(),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
        ];
    }

    private function grantPartnerView(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('account.partner.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPartnerUpdate(User $user, ?Partner $partner = null): void
    {
        $partner ??= $this->partner;

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('account.partner.update'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
