<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\UserTableSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankPaymentsViewModesFeatureTest extends CrmTestCase
{
    public function test_guest_cannot_open_summary_view(): void
    {
        Auth::logout();

        $this->get(route('reports.tbank-payments.index', ['view' => 'days']), ['HTTP_ACCEPT' => 'text/html'])
            ->assertRedirect();

        $json = $this->getJson(route('reports.tbank-payments.data', [
            'draw' => 1,
            'view' => 'days',
        ]));
        $this->assertContains($json->getStatusCode(), [401, 403]);
    }

    public function test_without_permission_summary_view_is_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.tbank-payments.index', ['view' => 'months']))
            ->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'days']))
            ->assertForbidden();
    }

    public function test_invalid_view_returns_field_error(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.index', ['view' => 'weeks']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);

        $this->getJson(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'weeks']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['view'])
            ->assertJsonPath('errors.view.0', 'Поле «Вид» содержит недопустимое значение.');

        $this->getJson(route('reports.tbank-payments.total', ['view' => 'weeks']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['view']);
    }

    public function test_index_with_days_view_renders_switcher_and_summary_headers(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['view' => 'days']))
            ->assertOk()
            ->assertViewHas('tpView', 'days')
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $this->assertStringContainsString('id="tp-view-switch"', $html);
        $this->assertStringContainsString('id="tp-view-btn-days"', $html);
        $this->assertStringContainsString('js-tbank-view-btn active', $html);
        $this->assertStringContainsString('id="tp-view-hidden" value="days"', $html);

        preg_match('/id="tbank-payments-table"[\s\S]*?<thead>([\s\S]*?)<\/thead>/', $html, $theadMatch);
        $thead = $theadMatch[1] ?? '';
        $this->assertStringContainsString('<th>Период</th>', $thead);
        $this->assertStringContainsString('<th>Платежей</th>', $thead);
        $this->assertStringNotContainsString('<th>Чек</th>', $thead);
        $this->assertStringNotContainsString('<th>ID</th>', $thead);

        $this->assertStringContainsString('id="tp-columns-summary-panel"', $html);
        $this->assertStringContainsString('tbank-payments-summary-column-toggle', $html);
        $this->assertStringContainsString('data-error-for="view"', $html);
    }

    public function test_view_query_does_not_open_filters_panel(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['view' => 'months']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="tbankPaymentsFiltersCollapse"[^>]*\bshow\b/',
            $html
        );
    }

    public function test_days_summary_groups_by_created_at_and_sums_amount_commission_payout(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0,
        ]);

        $dayA1 = $this->makePayment([
            'amount' => 150000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
        $dayA1->forceFill(['created_at' => Carbon::parse('2026-09-07 10:15:00')])->save();
        $this->makePayout($dayA1, ['amount' => 147000, 'net_amount' => 147000, 'status' => 'COMPLETED']);

        $dayA2 = $this->makePayment([
            'amount' => 50000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
        $dayA2->forceFill(['created_at' => Carbon::parse('2026-09-07 18:00:00')])->save();

        $dayB = $this->makePayment([
            'amount' => 20000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
        $dayB->forceFill(['created_at' => Carbon::parse('2026-09-08 09:00:00')])->save();

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'days']))
                ->assertOk()
                ->json('data')
        );

        $sept7 = $rows->firstWhere('period_key', '2026-09-07');
        $this->assertIsArray($sept7);
        $this->assertSame('07.09.2026', $sept7['period_title']);
        $this->assertSame(2, (int) $sept7['payments_count']);
        $this->assertEquals(2000.0, (float) $sept7['amount']);
        $this->assertEquals(40.0, (float) $sept7['platform_commission']);
        $this->assertEquals(1470.0, (float) $sept7['payout_amount']);
        $this->assertArrayNotHasKey('show_url', $sept7);
        $this->assertArrayNotHasKey('receipt_url', $sept7);

        $sept8 = $rows->firstWhere('period_key', '2026-09-08');
        $this->assertIsArray($sept8);
        $this->assertSame(1, (int) $sept8['payments_count']);
        $this->assertEquals(200.0, (float) $sept8['amount']);
        $this->assertEquals(4.0, (float) $sept8['platform_commission']);
        $this->assertNull($sept8['payout_amount']);
    }

    public function test_months_summary_groups_calendar_month_of_created_at(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 1.00,
            'platform_min_fixed' => 0,
        ]);

        $aug = $this->makePayment(['amount' => 100000, 'method' => 'card']);
        $aug->forceFill(['created_at' => Carbon::parse('2026-08-31 23:00:00')])->save();
        $this->makePayout($aug, ['amount' => 99000, 'net_amount' => 99000, 'status' => 'COMPLETED']);

        $sep1 = $this->makePayment(['amount' => 30000, 'method' => 'card']);
        $sep1->forceFill(['created_at' => Carbon::parse('2026-09-01 00:10:00')])->save();
        $sep2 = $this->makePayment(['amount' => 70000, 'method' => 'card']);
        $sep2->forceFill(['created_at' => Carbon::parse('2026-09-20 12:00:00')])->save();

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'months']))
                ->assertOk()
                ->json('data')
        );

        $august = $rows->firstWhere('period_key', '2026-08');
        $this->assertIsArray($august);
        $this->assertSame('Август 2026', $august['period_title']);
        $this->assertSame(1, (int) $august['payments_count']);
        $this->assertEquals(1000.0, (float) $august['amount']);
        $this->assertEquals(10.0, (float) $august['platform_commission']);
        $this->assertEquals(990.0, (float) $august['payout_amount']);

        $september = $rows->firstWhere('period_key', '2026-09');
        $this->assertIsArray($september);
        $this->assertSame('Сентябрь 2026', $september['period_title']);
        $this->assertSame(2, (int) $september['payments_count']);
        $this->assertEquals(1000.0, (float) $september['amount']);
        $this->assertEquals(10.0, (float) $september['platform_commission']);
        $this->assertNull($september['payout_amount']);
    }

    public function test_summary_search_matches_period_not_amount(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $hit = $this->makePayment(['amount' => 12300]);
        $hit->forceFill(['created_at' => Carbon::parse('2026-09-07 11:00:00')])->save();
        $miss = $this->makePayment(['amount' => 12300]);
        $miss->forceFill(['created_at' => Carbon::parse('2026-08-01 11:00:00')])->save();

        $dayKeys = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'days',
                    'search' => ['value' => '07.09.2026'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();

        $this->assertContains('2026-09-07', $dayKeys);
        $this->assertNotContains('2026-08-01', $dayKeys);

        $amountKeys = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'days',
                    'search' => ['value' => '123'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();
        $this->assertSame([], $amountKeys);

        $monthKeys = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'months',
                    'search' => ['value' => 'Сентябрь 2026'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();
        $this->assertContains('2026-09', $monthKeys);
        $this->assertNotContains('2026-08', $monthKeys);
    }

    public function test_summary_respects_filters_and_partner_isolation(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $own = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 40000,
            'status' => 'CONFIRMED',
            'method' => 'sbp',
        ]);
        $own->forceFill(['created_at' => Carbon::parse('2026-09-07 12:00:00')])->save();

        $foreign = $this->makePayment([
            'partner_id' => $this->foreignPartner->id,
            'amount' => 88000,
            'status' => 'CONFIRMED',
            'method' => 'sbp',
        ]);
        $foreign->forceFill(['created_at' => Carbon::parse('2026-09-07 12:00:00')])->save();

        $otherMethod = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'status' => 'CONFIRMED',
            'method' => 'card',
        ]);
        $otherMethod->forceFill(['created_at' => Carbon::parse('2026-09-07 13:00:00')])->save();

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'days',
                    'method' => 'sbp',
                    'partner_id' => $this->foreignPartner->id,
                ]))
                ->assertOk()
                ->json('data')
        )->firstWhere('period_key', '2026-09-07');

        $this->assertIsArray($row);
        $this->assertSame(1, (int) $row['payments_count']);
        $this->assertEquals(400.0, (float) $row['amount']);
    }

    public function test_payments_view_is_unchanged_one_row_per_payment(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $a = $this->makePayment(['amount' => 10000]);
        $a->forceFill(['created_at' => Carbon::parse('2026-09-07 10:00:00')])->save();
        $b = $this->makePayment(['amount' => 20000]);
        $b->forceFill(['created_at' => Carbon::parse('2026-09-07 11:00:00')])->save();

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'payments']))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_columns_settings_for_days_use_separate_table_key_and_share_page_length(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson('/admin/reports/tbank-payments/columns-settings?view=days', [
            'columns' => [
                'period' => true,
                'payout_amount' => false,
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $daySettings = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments_by_day')
            ->first();
        $this->assertNotNull($daySettings);
        $this->assertFalse($daySettings->columns['payout_amount'] ?? true);

        $this->postJson('/admin/reports/tbank-payments/columns-settings?view=days', [
            'page_length' => 50,
        ])->assertOk()->assertJson(['success' => true]);

        $canonical = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments')
            ->first();
        $this->assertNotNull($canonical);
        $this->assertSame(50, $canonical->page_length);

        $this->get('/admin/reports/tbank-payments/columns-settings?view=days')
            ->assertOk()
            ->assertJsonPath('payout_amount', false);

        $this->get('/admin/reports/tbank-payments/columns-settings')
            ->assertOk()
            ->assertJsonMissing(['payout_amount' => false]);
    }

    public function test_invalid_view_on_columns_settings_returns_field_error(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson('/admin/reports/tbank-payments/columns-settings?view=weeks')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['view']);

        $this->postJson('/admin/reports/tbank-payments/columns-settings?view=weeks', [
            'columns' => ['amount' => true],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['view']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePayment(array $overrides = []): TinkoffPayment
    {
        return TinkoffPayment::query()->create(array_merge([
            'order_id' => 'order-'.uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePayout(TinkoffPayment $payment, array $overrides = []): TinkoffPayout
    {
        return TinkoffPayout::query()->create(array_merge([
            'payment_id' => $payment->id,
            'partner_id' => $payment->partner_id,
            'deal_id' => (string) ($payment->deal_id ?: 'deal-'.$payment->id),
            'amount' => 1000,
            'is_final' => true,
            'status' => 'COMPLETED',
            'source' => 'auto',
        ], $overrides));
    }

    private function grantTbankPaymentsViewToAdmin(): \App\Models\User
    {
        $actor = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => (int) $actor->role_id,
                'permission_id' => $this->permissionId('reports.tbank.payments.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $actor;
    }
}
