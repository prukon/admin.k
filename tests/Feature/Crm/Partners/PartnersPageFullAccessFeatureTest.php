<?php

namespace Tests\Feature\Crm\Partners;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к странице /admin/partners и связанным эндпоинтам
 * (partner.view → 200/201, без права → 403).
 */
final class PartnersPageFullAccessFeatureTest extends CrmTestCase
{
    private Partner $targetPartner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPartnerViewForRole((int) $this->user->role_id);

        $this->targetPartner = Partner::factory()->create([
            'title' => 'Full access smoke partner',
            'email' => 'full_access_' . Str::lower(Str::random(6)) . '@example.test',
            'is_enabled' => true,
            'order_by' => 3,
        ]);
    }

    public function test_partners_index_page_returns_200_with_partner_view(): void
    {
        $this->get(route('admin.partner.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertSee('partnersSectionTabs', false)
            ->assertSee('role="tab">Партнеры</a>', false)
            ->assertSee('id="partners-table"', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('partnersReportFiltersCollapse', false)
            ->assertSee('partnersColumnsDropdown', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('>№<', false);
    }

    public function test_all_partners_page_endpoints_return_success_for_user_with_partner_view(): void
    {
        $this->get(route('admin.partner.index'))->assertOk();

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'title' => 'Full access',
            'status' => 'active',
        ]))->assertOk();

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'inactive',
        ]))->assertOk();

        $this->getJson(route('admin.partner.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => [
                'order_by' => true,
                'title' => true,
                'organization_name' => true,
                'tax_id' => true,
                'email' => true,
                'phone' => true,
                'status_label' => true,
                'actions' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('logs.data.partner', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('admin.partner.edit', $this->targetPartner))->assertOk();

        $this->postJson(route('admin.partner.store'), $this->validPartnerPayload([
            'title' => 'Created via full access test',
        ]))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Партнёр успешно создан');

        $this->patchJson(route('admin.partner.update', $this->targetPartner), $this->validPartnerPayload(
            $this->payloadMatchingPartner($this->targetPartner, [
                'title' => 'Full access smoke partner updated',
            ])
        ))
            ->assertOk()
            ->assertJsonPath('message', 'Партнёр успешно обновлён');

        $disposable = Partner::factory()->create([
            'title' => 'Disposable for delete smoke',
            'email' => 'del_smoke_' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $this->deleteJson(route('admin.partner.delete', $disposable))
            ->assertOk()
            ->assertJsonPath('message', 'Партнёр удалён');
    }

    public function test_user_with_partner_view_can_access_page_and_all_section_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->grantPartnerViewForRole((int) $actor->role_id);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $partner = Partner::factory()->create([
            'title' => 'Partner view only smoke',
            'email' => 'view_only_' . Str::lower(Str::random(6)) . '@example.test',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.partner.index'))
            ->assertOk()
            ->assertSee('id="partners-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('id="new-partner"', false)
            ->assertSee('edit-partner-link', false);

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'active',
        ]))->assertOk();

        $this->getJson(route('admin.partner.data', [
            'draw' => 1,
            'title' => 'Partner',
            'status' => 'active',
            'search' => ['value' => 'view'],
        ]))->assertOk();

        $this->getJson(route('admin.partner.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => [
                'title' => true,
                'status_label' => true,
            ],
        ])->assertOk();

        $this->getJson(route('logs.data.partner', ['draw' => 1, 'start' => 0, 'length' => 5]))
            ->assertOk();

        $this->getJson(route('admin.partner.edit', $partner))->assertOk();

        $this->postJson(route('admin.partner.store'), $this->validPartnerPayload([
            'title' => 'Created with partner.view only',
        ]))->assertStatus(201);

        $this->patchJson(route('admin.partner.update', $partner), $this->validPartnerPayload(
            $this->payloadMatchingPartner($partner, [
                'title' => 'Updated with partner.view only',
            ])
        ))->assertOk();

        $deleteTarget = Partner::factory()->create([
            'title' => 'To delete partner view only',
            'email' => 'del_view_' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $this->deleteJson(route('admin.partner.delete', $deleteTarget))
            ->assertOk()
            ->assertJsonPath('message', 'Партнёр удалён');
    }

    public function test_tbank_payouts_tab_page_and_all_endpoints_return_200_with_payouts_manage(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantTbankPayoutsManageForRole((int) $actor->role_id);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $payout = \App\Models\TinkoffPayout::query()->create([
            'payment_id'                => null,
            'partner_id'                => $this->partner->id,
            'deal_id'                   => 'partners-full-' . uniqid(),
            'amount'                    => 100,
            'is_final'                  => true,
            'status'                    => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run'               => now()->addDay(),
            'completed_at'              => null,
        ]);

        $this->get(route('admin.tinkoff.payouts.index'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertViewHas('activeTab', 'payouts')
            ->assertSee('partnersSectionTabs', false)
            ->assertSee('role="tab">Выплаты T‑Bank</a>', false)
            ->assertSee('id="payouts-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('tbankPayoutsToolbarTotals', false);

        $this->getJson('/admin/tinkoff/payouts/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson('/admin/tinkoff/payouts/total')
            ->assertOk()
            ->assertJsonStructure([
                'payments_total_formatted',
                'payouts_total_formatted',
                'platform_fee_total_formatted',
            ]);

        $this->getJson('/admin/tinkoff/payouts/columns-settings')->assertOk();

        $this->postJson('/admin/tinkoff/payouts/columns-settings', [
            'columns' => ['status' => true, 'net' => true],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/admin/tinkoff/payouts/payers-search?q=')->assertOk();

        $this->get('/admin/tinkoff/payouts/' . $payout->id)->assertOk();

        $this->post('/admin/tinkoff/payouts/' . $payout->id . '/schedule', [
            'when_to_run' => now()->addHours(3)->format('Y-m-d\TH:i'),
        ])->assertRedirect('/admin/tinkoff/payouts/' . $payout->id);
    }

    public function test_tbank_payouts_endpoints_return_403_without_payouts_manage(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.tinkoff.payouts.index'))->assertForbidden();
        $this->getJson('/admin/tinkoff/payouts/data?draw=1')->assertForbidden();
        $this->getJson('/admin/tinkoff/payouts/total')->assertForbidden();
        $this->getJson('/admin/tinkoff/payouts/columns-settings')->assertForbidden();
        $this->postJson('/admin/tinkoff/payouts/columns-settings', ['columns' => ['status' => true]])->assertForbidden();
        $this->getJson('/admin/tinkoff/payouts/payers-search?q=')->assertForbidden();
    }

    public function test_partner_leads_tab_page_returns_200_with_partner_leads_view(): void
    {
        $actor = $this->createUserWithoutPermission('partnerLeads.view', $this->partner);
        $this->grantPartnerLeadsViewForRole((int) $actor->role_id);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertSee('partnersSectionTabs', false)
            ->assertSee('role="tab">Лиды</a>', false)
            ->assertSee('partnerLeadsReportToolbar', false)
            ->assertSee('partnerLeadsFiltersCollapse', false)
            ->assertSee('columnsDropdownPartnerLeads', false)
            ->assertSee('id="leads-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('partner-leads-column-toggle', false);

        $this->getJson(route('admin.partner-leads.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'stats', 'data']);

        $this->getJson(route('admin.partner-leads.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.partner-leads.columns-settings.save'), [
            'columns' => [
                'name' => true,
                'phone' => true,
                'email' => true,
                'status' => true,
                'actions' => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_partners_data_new_filter_and_search_params_return_200(): void
    {
        Partner::factory()->create([
            'title' => 'Filter 200 partner',
            'is_enabled' => true,
        ]);

        $queries = [
            route('admin.partner.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'active']),
            route('admin.partner.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'inactive']),
            route('admin.partner.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'title' => 'Filter']),
            route('admin.partner.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => 'Filter']]),
            route('admin.partner.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'title' => 'Filter',
                'search' => ['value' => 'Other'],
                'status' => 'active',
            ]),
        ];

        foreach ($queries as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_partners_index_returns_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.partner.index'))->assertStatus(403);
    }

    public function test_partners_data_returns_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.partner.data', ['draw' => 1]))->assertStatus(403);
    }

    public function test_columns_settings_return_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.partner.columns-settings.get'))->assertStatus(403);

        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => ['title' => true],
        ])->assertStatus(403);
    }

    public function test_partner_edit_returns_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.partner.edit', $this->targetPartner))->assertStatus(403);
    }

    public function test_partner_store_returns_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('admin.partner.store'), $this->validPartnerPayload())
            ->assertStatus(403);
    }

    public function test_partner_update_returns_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->patchJson(route('admin.partner.update', $this->targetPartner), $this->validPartnerPayload())
            ->assertStatus(403);
    }

    public function test_partner_delete_returns_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->deleteJson(route('admin.partner.delete', $this->targetPartner))
            ->assertStatus(403);
    }

    public function test_partner_logs_returns_403_without_partner_view(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('logs.data.partner', ['draw' => 1]))->assertStatus(403);
    }

    public function test_guest_cannot_access_any_partners_endpoint(): void
    {
        Auth::logout();

        $endpoints = [
            fn () => $this->get(route('admin.partner.index')),
            fn () => $this->getJson(route('admin.partner.data', ['draw' => 1])),
            fn () => $this->getJson(route('admin.partner.columns-settings.get')),
            fn () => $this->postJson(route('admin.partner.columns-settings.save'), [
                'columns' => ['title' => true],
            ]),
            fn () => $this->getJson(route('logs.data.partner', ['draw' => 1])),
            fn () => $this->getJson(route('admin.partner.edit', $this->targetPartner)),
            fn () => $this->postJson(route('admin.partner.store'), $this->validPartnerPayload()),
            fn () => $this->patchJson(route('admin.partner.update', $this->targetPartner), $this->validPartnerPayload()),
            fn () => $this->deleteJson(route('admin.partner.delete', $this->targetPartner)),
            fn () => $this->get(route('admin.tinkoff.payouts.index')),
            fn () => $this->getJson('/admin/tinkoff/payouts/data?draw=1'),
            fn () => $this->getJson('/admin/tinkoff/payouts/total'),
        ];

        foreach ($endpoints as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected status: ' . $status);
        }
    }

    private function grantPartnerViewForRole(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantPartnerLeadsViewForRole(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('partnerLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantTbankPayoutsManageForRole(int $roleId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('tbank.payouts.manage'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function payloadMatchingPartner(Partner $partner, array $overrides = []): array
    {
        return array_merge([
            'email' => $partner->email,
            'tax_id' => $partner->tax_id,
            'kpp' => $partner->kpp,
            'registration_number' => $partner->registration_number,
            'sms_name' => $partner->sms_name,
            'organization_name' => $partner->organization_name,
            'phone' => $partner->phone,
            'order_by' => $partner->order_by,
            'is_enabled' => (bool) $partner->is_enabled,
        ], $overrides);
    }

    private function validPartnerPayload(array $overrides = []): array
    {
        $email = $overrides['email'] ?? ('partner_' . Str::lower(Str::random(8)) . '@example.test');
        $unique = Str::lower(Str::random(6));

        return array_merge([
            'business_type' => 'company',
            'title' => 'Тестовый партнёр',
            'organization_name' => 'ООО Тест',
            'tax_id' => '77' . random_int(10000000, 99999999),
            'kpp' => (string) random_int(100000000, 999999999),
            'registration_number' => $unique . random_int(1000000, 9999999),
            'sms_name' => 'TESTPARTNER',
            'city' => 'СПб',
            'zip' => '197350',
            'address' => 'Невский пр., 1',
            'phone' => '+79990001122',
            'email' => $email,
            'website' => 'https://example.test',
            'bank_name' => 'Банк',
            'bank_bik' => '123456789',
            'bank_account' => '12345678901234567890',
            'order_by' => 10,
            'is_enabled' => true,
            'ceo' => [
                'lastName' => 'Иванов',
                'firstName' => 'Иван',
                'middleName' => 'Иванович',
                'phone' => '+79991112233',
            ],
        ], $overrides);
    }
}
