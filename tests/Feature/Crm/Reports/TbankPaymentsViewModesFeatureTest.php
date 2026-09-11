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
    private const INVALID_VIEW_MESSAGE = 'Поле «Вид» содержит недопустимое значение.';

    public function test_guest_cannot_open_summary_view(): void
    {
        Auth::logout();

        foreach (['days', 'months'] as $view) {
            $this->get(route('reports.tbank-payments.index', ['view' => $view]), ['HTTP_ACCEPT' => 'text/html'])
                ->assertRedirect();

            $data = $this->getJson(route('reports.tbank-payments.data', [
                'draw' => 1,
                'view' => $view,
            ]));
            $this->assertContains($data->getStatusCode(), [401, 403], 'data view='.$view);

            $total = $this->getJson(route('reports.tbank-payments.total', ['view' => $view]));
            $this->assertContains($total->getStatusCode(), [401, 403], 'total view='.$view);

            $getCols = $this->getJson('/admin/reports/tbank-payments/columns-settings?view='.$view);
            $this->assertContains($getCols->getStatusCode(), [401, 403], 'GET columns view='.$view);

            $postCols = $this->postJson('/admin/reports/tbank-payments/columns-settings?view='.$view, [
                'columns' => ['amount' => true],
            ]);
            $this->assertContains($postCols->getStatusCode(), [401, 403, 419], 'POST columns view='.$view);
        }
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

        $this->getJson(route('reports.tbank-payments.total', ['view' => 'days']))
            ->assertForbidden();

        $this->getJson('/admin/reports/tbank-payments/columns-settings?view=months')
            ->assertForbidden();

        $this->postJson('/admin/reports/tbank-payments/columns-settings?view=days', [
            'columns' => ['period' => true],
        ])->assertForbidden();
    }

    public function test_viewer_with_permission_can_open_days_and_months(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.tbank-payments.index', ['view' => 'days']))
            ->assertOk()
            ->assertViewHas('tpView', 'days')
            ->assertViewHas('tpCanFilterPartner', false);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'months']))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('reports.tbank-payments.total', ['view' => 'months']))
            ->assertOk()
            ->assertJsonStructure(['total_formatted', 'total_raw']);
    }

    public function test_invalid_view_returns_field_error(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach (['weeks', 'all', 'yearly'] as $invalid) {
            $this->from(route('reports.tbank-payments.index'))
                ->get(route('reports.tbank-payments.index', ['view' => $invalid]))
                ->assertStatus(302)
                ->assertSessionHasErrors(['view']);
            $this->assertSame(self::INVALID_VIEW_MESSAGE, session('errors')->first('view'));

            $this->getJson(route('reports.tbank-payments.data', ['draw' => 1, 'view' => $invalid]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['view'])
                ->assertJsonPath('errors.view.0', self::INVALID_VIEW_MESSAGE);

            $this->getJson(route('reports.tbank-payments.total', ['view' => $invalid]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['view'])
                ->assertJsonPath('errors.view.0', self::INVALID_VIEW_MESSAGE);
        }
    }

    public function test_invalid_view_non_ajax_on_data_and_total_redirects_with_field_error(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.total', ['view' => 'weeks']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'weeks']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);
    }

    public function test_invalid_view_redirect_shows_error_under_switcher(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->from(route('reports.tbank-payments.index'))
            ->get(route('reports.tbank-payments.index', ['view' => 'weeks']))
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-error-for="view"', $html);
        $this->assertStringContainsString(self::INVALID_VIEW_MESSAGE, $html);
        $this->assertStringContainsString('style="display:block"', $html);
        $this->assertSame('payments', $this->hiddenViewValue($html));
        $thead = $this->tableThead($html);
        $this->assertStringContainsString('<th>Чек</th>', $thead);
        $this->assertStringNotContainsString('<th>Период</th>', $thead);
    }

    public function test_index_with_days_view_renders_switcher_and_summary_headers(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['view' => 'days']))
            ->assertOk()
            ->assertViewHas('tpView', 'days')
            ->assertViewHas('tpHasActiveFilters', true)
            ->assertViewHas('tpStatusDefaulted', true)
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
        $this->assertStringContainsString('value="CONFIRMED" selected', $html);
        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringContainsString('show', $collapseTag[0]);
    }

    public function test_view_query_with_status_all_does_not_open_filters_panel(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', [
            'view' => 'months',
            'status' => 'all',
        ]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->assertViewHas('tpStatusDefaulted', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="tbankPaymentsFiltersCollapse"[^>]*\bshow\b/',
            $html
        );
        $this->assertStringContainsString('<option value="" selected>Все статусы</option>', $html);
        $this->assertStringNotContainsString('value="CONFIRMED" selected', $html);
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
            ->assertJsonValidationErrors(['view'])
            ->assertJsonPath('errors.view.0', self::INVALID_VIEW_MESSAGE);

        $this->postJson('/admin/reports/tbank-payments/columns-settings?view=weeks', [
            'columns' => ['amount' => true],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['view'])
            ->assertJsonPath('errors.view.0', self::INVALID_VIEW_MESSAGE);

        $this->from(route('reports.tbank-payments.index'))
            ->post('/admin/reports/tbank-payments/columns-settings?view=weeks', [
                'columns' => ['amount' => true],
            ], ['HTTP_ACCEPT' => 'text/html'])
            ->assertStatus(302)
            ->assertSessionHasErrors(['view']);
    }

    public function test_first_open_without_view_keeps_payments_table_and_does_not_open_filters(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewHas('tpView', 'payments')
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $this->assertStringContainsString('id="tp-view-switch"', $html);
        $this->assertStringContainsString('btn-group btn-group-sm js-tbank-payments-view', $html);
        $this->assertStringContainsString('id="tp-view-btn-payments"', $html);
        $this->assertStringContainsString('js-tbank-view-btn active', $html);
        $this->assertSame('payments', $this->hiddenViewValue($html));

        $thead = $this->tableThead($html);
        $this->assertStringContainsString('<th>ID</th>', $thead);
        $this->assertStringContainsString('<th>Чек</th>', $thead);
        $this->assertStringNotContainsString('<th>Период</th>', $thead);

        $this->assertDoesNotMatchRegularExpression(
            '/id="tbankPaymentsFiltersCollapse"[^>]*\bshow\b/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="tp-columns-payments-panel"[^>]*\bd-none\b/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="tp-columns-summary-panel"[^>]*\bd-none\b/',
            $html
        );
        $this->assertStringContainsString('id="tp-toolbar-commissions"', $html);
    }

    public function test_empty_view_query_is_payments_and_does_not_force_summary(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['amount' => 12300]);

        $html = $this->get(route('reports.tbank-payments.index', ['view' => '']))
            ->assertOk()
            ->assertViewHas('tpView', 'payments')
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $thead = $this->tableThead($html);
        $this->assertStringContainsString('<th>Чек</th>', $thead);
        $this->assertStringNotContainsString('<th>Период</th>', $thead);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => '']))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($payment->id, $ids);
    }

    public function test_included_partial_receives_tp_view_so_days_thead_is_not_payments(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $include = (string) file_get_contents(resource_path('views/admin/report/index.blade.php'));
        $this->assertStringContainsString("'tpView' => \$tpView ?? 'payments'", $include);

        $html = $this->get(route('reports.tbank-payments.index', ['view' => 'days']))
            ->assertOk()
            ->getContent();

        $this->assertSame('days', $this->hiddenViewValue($html));
        $thead = $this->tableThead($html);
        $this->assertStringContainsString('<th>Период</th>', $thead);
        $this->assertStringNotContainsString('<th>Чек</th>', $thead);
        $this->assertStringNotContainsString('<th>ID</th>', $thead);
        $this->assertMatchesRegularExpression(
            '/id="tp-columns-payments-panel"[^>]*\bd-none\b/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="tp-columns-summary-panel"[^>]*\bd-none\b/',
            $html
        );
    }

    public function test_months_view_renders_summary_headers_and_active_button(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['view' => 'months']))
            ->assertOk()
            ->assertViewHas('tpView', 'months')
            ->assertViewHas('tpHasActiveFilters', true)
            ->assertViewHas('tpStatusDefaulted', true)
            ->getContent();

        $this->assertSame('months', $this->hiddenViewValue($html));
        $this->assertStringContainsString('data-view="months"', $html);
        $thead = $this->tableThead($html);
        $this->assertStringContainsString('<th>Период</th>', $thead);
        $this->assertStringContainsString('<th>Платежей</th>', $thead);
        $this->assertStringNotContainsString('<th>Чек</th>', $thead);
    }

    public function test_toolbar_total_does_not_depend_on_view(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->makePayment(['amount' => 150000]);
        $this->makePayment(['amount' => 50000]);

        $payments = $this->getJson(route('reports.tbank-payments.total', ['view' => 'payments', 'status' => 'all']))
            ->assertOk()
            ->json();
        $days = $this->getJson(route('reports.tbank-payments.total', ['view' => 'days', 'status' => 'all']))
            ->assertOk()
            ->json();
        $months = $this->getJson(route('reports.tbank-payments.total', ['view' => 'months', 'status' => 'all']))
            ->assertOk()
            ->json();

        $this->assertSame($payments['total_raw'], $days['total_raw']);
        $this->assertSame($payments['total_raw'], $months['total_raw']);
        $this->assertSame($payments['total_formatted'], $days['total_formatted']);
        $this->assertEquals(2000.0, (float) $payments['total_raw']);
    }

    public function test_payments_view_does_not_default_confirmed_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewHas('tpView', 'payments')
            ->assertViewHas('tpHasActiveFilters', false)
            ->assertViewHas('tpStatusDefaulted', false)
            ->getContent();

        $this->assertStringContainsString('<option value="" selected>Все статусы</option>', $html);
        $this->assertStringNotContainsString('value="CONFIRMED" selected', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="tbankPaymentsFiltersCollapse"[^>]*\bshow\b/',
            $html
        );
    }

    public function test_days_and_months_without_status_filter_only_confirmed(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $confirmed = $this->makePayment(['amount' => 10000, 'status' => 'CONFIRMED']);
        $confirmed->forceFill(['created_at' => Carbon::parse('2026-09-07 10:00:00')])->save();
        $rejected = $this->makePayment(['amount' => 50000, 'status' => 'REJECTED']);
        $rejected->forceFill(['created_at' => Carbon::parse('2026-09-07 11:00:00')])->save();
        $fresh = $this->makePayment(['amount' => 30000, 'status' => 'NEW']);
        $fresh->forceFill(['created_at' => Carbon::parse('2026-09-07 12:00:00')])->save();

        $daysDefault = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'days']))
                ->assertOk()
                ->json('data')
        )->firstWhere('period_key', '2026-09-07');
        $this->assertIsArray($daysDefault);
        $this->assertSame(1, (int) $daysDefault['payments_count']);
        $this->assertEquals(100.0, (float) $daysDefault['amount']);

        $daysAll = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'days',
                    'status' => 'all',
                ]))
                ->assertOk()
                ->json('data')
        )->firstWhere('period_key', '2026-09-07');
        $this->assertIsArray($daysAll);
        $this->assertSame(3, (int) $daysAll['payments_count']);
        $this->assertEquals(900.0, (float) $daysAll['amount']);

        $this->assertEquals(100.0, (float) $this->getJson(route('reports.tbank-payments.total', ['view' => 'days']))->json('total_raw'));
        $this->assertEquals(900.0, (float) $this->getJson(route('reports.tbank-payments.total', ['view' => 'months', 'status' => 'all']))->json('total_raw'));
        $this->assertEquals(900.0, (float) $this->getJson(route('reports.tbank-payments.total', ['view' => 'payments']))->json('total_raw'));
    }

    public function test_explicit_status_on_days_is_not_overwritten(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', [
            'view' => 'days',
            'status' => 'REJECTED',
        ]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->assertViewHas('tpStatusDefaulted', false)
            ->getContent();

        $this->assertStringContainsString('value="REJECTED" selected', $html);
        $this->assertStringNotContainsString('value="CONFIRMED" selected', $html);

        $emptyStatus = $this->get('/admin/reports/tbank-payments?view=months&status=')
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->assertViewHas('tpStatusDefaulted', false)
            ->getContent();
        $this->assertStringContainsString('<option value="" selected>Все статусы</option>', $emptyStatus);
        $this->assertStringNotContainsString('value="CONFIRMED" selected', $emptyStatus);
    }

    public function test_summary_groups_by_created_at_not_confirmed_at(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['amount' => 100000, 'status' => 'CONFIRMED']);
        $payment->forceFill([
            'created_at' => Carbon::parse('2026-08-31 23:40:00'),
            'confirmed_at' => Carbon::parse('2026-09-01 00:10:00'),
        ])->save();

        $dayKeys = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'days']))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();

        $this->assertContains('2026-08-31', $dayKeys);
        $this->assertNotContains('2026-09-01', $dayKeys);

        $monthKeys = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'months']))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();

        $this->assertContains('2026-08', $monthKeys);
        $this->assertNotContains('2026-09', $monthKeys);
    }

    public function test_summary_payout_ignores_rejected_and_keeps_latest_non_rejected(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $onlyRejected = $this->makePayment(['amount' => 80000]);
        $onlyRejected->forceFill(['created_at' => Carbon::parse('2026-09-07 10:00:00')])->save();
        $this->makePayout($onlyRejected, ['amount' => 1000, 'net_amount' => 1000, 'status' => 'REJECTED']);

        $retried = $this->makePayment(['amount' => 20000]);
        $retried->forceFill(['created_at' => Carbon::parse('2026-09-08 10:00:00')])->save();
        $this->makePayout($retried, ['amount' => 1111, 'net_amount' => 1111, 'status' => 'REJECTED']);
        $this->makePayout($retried, ['amount' => 19000, 'net_amount' => 19000, 'status' => 'COMPLETED']);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => 'days']))
                ->assertOk()
                ->json('data')
        );

        $sept7 = $rows->firstWhere('period_key', '2026-09-07');
        $this->assertIsArray($sept7);
        $this->assertNull($sept7['payout_amount']);

        $sept8 = $rows->firstWhere('period_key', '2026-09-08');
        $this->assertIsArray($sept8);
        $this->assertEquals(190.0, (float) $sept8['payout_amount']);
    }

    public function test_days_summary_respects_without_payout_and_date_filters(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $paid = $this->makePayment(['amount' => 30000, 'status' => 'CONFIRMED']);
        $paid->forceFill(['created_at' => Carbon::parse('2026-09-07 10:00:00')])->save();
        $this->makePayout($paid, ['amount' => 29000, 'net_amount' => 29000, 'status' => 'COMPLETED']);

        $unpaid = $this->makePayment(['amount' => 40000, 'status' => 'CONFIRMED']);
        $unpaid->forceFill(['created_at' => Carbon::parse('2026-09-07 11:00:00')])->save();

        $nextDay = $this->makePayment(['amount' => 10000, 'status' => 'CONFIRMED']);
        $nextDay->forceFill(['created_at' => Carbon::parse('2026-09-08 09:00:00')])->save();

        $withoutPayout = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'days',
                    'without_payout' => 1,
                ]))
                ->assertOk()
                ->json('data')
        );

        $sept7 = $withoutPayout->firstWhere('period_key', '2026-09-07');
        $this->assertIsArray($sept7);
        $this->assertSame(1, (int) $sept7['payments_count']);
        $this->assertEquals(400.0, (float) $sept7['amount']);
        $this->assertNull($sept7['payout_amount']);

        $dateFiltered = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'days',
                    'created_from' => '2026-09-08',
                    'created_to' => '2026-09-08',
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();

        $this->assertContains('2026-09-08', $dateFiltered);
        $this->assertNotContains('2026-09-07', $dateFiltered);
    }

    public function test_months_search_matches_yyyy_mm_not_amount(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $aug = $this->makePayment(['amount' => 77700]);
        $aug->forceFill(['created_at' => Carbon::parse('2026-08-15 12:00:00')])->save();
        $sep = $this->makePayment(['amount' => 77700]);
        $sep->forceFill(['created_at' => Carbon::parse('2026-09-15 12:00:00')])->save();

        $keys = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'months',
                    'search' => ['value' => '2026-09'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();

        $this->assertContains('2026-09', $keys);
        $this->assertNotContains('2026-08', $keys);

        $byAmount = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'view' => 'months',
                    'search' => ['value' => '777'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('period_key')->all();
        $this->assertSame([], $byAmount);
    }

    public function test_non_ajax_days_data_returns_json_not_empty_html(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->makePayment(['amount' => 12000]);

        $response = $this->get(route('reports.tbank-payments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'view' => 'days',
        ]), ['HTTP_ACCEPT' => 'text/html']);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
        $this->assertArrayNotHasKey('show_url', $json['data'][0] ?? []);
    }

    public function test_non_ajax_columns_settings_for_days_saves_to_day_key(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $response = $this->post('/admin/reports/tbank-payments/columns-settings?view=days', [
            'columns' => [
                'period' => true,
                'payout_amount' => false,
            ],
        ], ['HTTP_ACCEPT' => 'text/html']);

        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $daySettings = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments_by_day')
            ->first();
        $this->assertNotNull($daySettings);
        $this->assertFalse($daySettings->columns['payout_amount'] ?? true);

        $this->assertSame(
            0,
            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', 'reports_tbank_payments')
                ->whereNotNull('columns')
                ->count()
        );
    }

    public function test_unsupported_methods_with_view_do_not_return_500_or_empty_200(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $urls = [
            route('reports.tbank-payments.index', ['view' => 'days']),
            route('reports.tbank-payments.total', ['view' => 'months']),
            '/admin/reports/tbank-payments/columns-settings?view=days',
        ];

        foreach ($urls as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, [
                    'columns' => ['period' => true],
                    'page_length' => 50,
                ]);
                $this->assertNotSame(500, $response->getStatusCode(), "$method $url");
                $this->assertContains($response->getStatusCode(), [404, 405], "$method $url");
            }
        }

        $this->assertSame(
            0,
            UserTableSetting::where('user_id', $this->user->id)
                ->whereIn('table_key', [
                    'reports_tbank_payments',
                    'reports_tbank_payments_by_day',
                    'reports_tbank_payments_by_month',
                ])
                ->count()
        );
    }

    public function test_switching_view_unwraps_scroll_host_before_destroy_so_summary_does_not_keep_payment_rows(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/report/tbank_payments.blade.php'));

        $destroyPos = strpos($blade, 'function destroyTbankPaymentsTable()');
        $this->assertNotFalse($destroyPos);
        $destroyChunk = substr($blade, $destroyPos, 2200);
        $unwrapPos = strpos($destroyChunk, "if (\$table.parent().hasClass('kids-dt-scroll-x'))");
        $destroyCallPos = strpos($destroyChunk, 'dtApi.table.destroy()');
        $this->assertNotFalse($unwrapPos);
        $this->assertNotFalse($destroyCallPos);
        $this->assertLessThan($destroyCallPos, $unwrapPos);
        $this->assertStringContainsString("\$table.find('tbody').remove()", $destroyChunk);
        $this->assertStringContainsString("\$table.append('<tbody></tbody>')", $destroyChunk);
        $this->assertStringContainsString('tbankPaymentsTheadHtml(currentView)', $destroyChunk);

        $clickPos = strpos($blade, '$(\'.js-tbank-view-btn\').on(\'click\'');
        $this->assertNotFalse($clickPos);
        $this->assertSame(1, substr_count($blade, '$(\'.js-tbank-view-btn\').on(\'click\''));
        $clickChunk = substr($blade, $clickPos, 1800);
        $this->assertStringContainsString('if (view === currentView)', $clickChunk);
        $this->assertStringContainsString('tpApplyConfirmedDefault()', $clickChunk);
        $this->assertStringContainsString('tpClearConfirmedDefaultIfNeeded()', $clickChunk);
        $this->assertStringContainsString('destroyTbankPaymentsTable()', $clickChunk);
        $this->assertStringContainsString('mountTbankPaymentsTable()', $clickChunk);
        $this->assertStringNotContainsString('dtApi.reload()', $clickChunk);
        $this->assertStringNotContainsString('$(\'#tbank-payments-table thead\').html', $clickChunk);

        $resetPos = strpos($blade, '$(\'#tbankPaymentsResetBtn\').on(\'click\'');
        $this->assertNotFalse($resetPos);
        $resetChunk = substr($blade, $resetPos, 1200);
        $this->assertStringContainsString('var keepView = currentView', $resetChunk);
        $this->assertStringContainsString('currentView = keepView', $resetChunk);
        $this->assertStringContainsString('$(\'#tp-view-hidden\').val(currentView)', $resetChunk);
        $this->assertStringContainsString("tpStatusSelect().val('CONFIRMED')", $resetChunk);
        $this->assertStringContainsString('dtApi.reload()', $resetChunk);
        $this->assertStringNotContainsString('destroyTbankPaymentsTable()', $resetChunk);
        $this->assertStringNotContainsString('KidsCrmDataTable.create', $resetChunk);
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

    private function tableThead(string $html): string
    {
        preg_match('/id="tbank-payments-table"[\s\S]*?<thead>([\s\S]*?)<\/thead>/', $html, $match);

        return $match[1] ?? '';
    }

    private function hiddenViewValue(string $html): string
    {
        if (preg_match('/id="tp-view-hidden"[^>]*value="([^"]*)"/', $html, $match) !== 1) {
            $this->fail('hidden view input not found');
        }

        return $match[1];
    }
}
