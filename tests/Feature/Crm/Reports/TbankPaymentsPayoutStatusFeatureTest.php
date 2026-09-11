<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\UserTableSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Колонка «Статус выплаты» на вкладке «Платежи T‑Bank».
 */
final class TbankPaymentsPayoutStatusFeatureTest extends CrmTestCase
{
    public function test_guest_cannot_open_report_or_read_payout_status_json(): void
    {
        Auth::logout();

        $this->get(route('reports.tbank-payments.index'), ['HTTP_ACCEPT' => 'text/html'])
            ->assertRedirect();

        $json = $this->getJson(route('reports.tbank-payments.data', ['draw' => 1]));
        $this->assertContains($json->getStatusCode(), [401, 403]);

        $total = $this->getJson(route('reports.tbank-payments.total'));
        $this->assertContains($total->getStatusCode(), [401, 403]);

        $columns = $this->getJson('/admin/reports/tbank-payments/columns-settings');
        $this->assertContains($columns->getStatusCode(), [401, 403]);
    }

    public function test_user_without_report_permission_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.tbank-payments.index'))->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.tbank-payments.data', ['draw' => 1]))
            ->assertForbidden();
        $this->get(route('reports.tbank-payments.total'))->assertForbidden();
        $this->get('/admin/reports/tbank-payments/columns-settings')->assertForbidden();
        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => ['payout_status' => true],
        ])->assertForbidden();
    }

    public function test_first_open_renders_payout_status_after_payout_amount(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<th>Выплата<\/th>\s*<th>Статус выплаты<\/th>\s*<th>Способ<\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-column-key="payout_amount"[^>]*>[\s\S]*data-column-key="payout_status"[^>]*>[\s\S]*data-column-key="method"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="form-check-input tbank-payments-column-toggle"[^>]*data-column-key="payout_status"[^>]*checked/',
            $html
        );
        $this->assertStringContainsString('id="tpColPayoutStatus"', $html);
        $this->assertStringContainsString('payout_status: true', $html);
        $this->assertStringContainsString('function renderPayoutStatusCell', $html);
        $this->assertStringNotContainsString("type: 'custom'", $html);

        $checkbox = $this->payoutStatusCheckboxTag($html);
        $this->assertStringContainsString('checked', $checkbox);
        $this->assertStringNotContainsString('disabled', $checkbox);
    }

    public function test_viewer_without_payouts_manage_or_additional_value_sees_payout_status_column(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->assertFalse($actor->can('tbank.payouts.manage'));
        $this->assertFalse($actor->can('reports.additional.value.view'));

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="tpColPayoutStatus"', $html);
        $this->assertStringContainsString('<th>Статус выплаты</th>', $html);
        $this->assertStringNotContainsString("can('reports.additional.value.view')", $html);
        $this->assertStringNotContainsString("can('tbank.payouts.manage')", $html);

        $payment = $this->makePayment(['amount' => 12000]);
        $this->makePayout($payment, ['status' => 'REJECTED']);

        $row = $this->datatableRow($payment->id);
        $this->assertSame('REJECTED', $row['payout_status']);
        $this->assertNull($row['payout_amount']);
    }

    public function test_first_open_keeps_filters_collapsed_and_payout_status_in_payments_panel(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringNotContainsString('show', $collapseTag[0]);

        $paymentsStart = strpos($html, 'id="tp-columns-payments-panel"');
        $summaryStart = strpos($html, 'id="tp-columns-summary-panel"');
        $filtersStart = strpos($html, 'id="tbankPaymentsFiltersCollapse"');
        $this->assertNotFalse($paymentsStart);
        $this->assertNotFalse($summaryStart);
        $this->assertNotFalse($filtersStart);
        $this->assertGreaterThan($paymentsStart, $summaryStart);

        $paymentsPanel = substr($html, $paymentsStart, $summaryStart - $paymentsStart);
        $summaryPanel = substr($html, $summaryStart, $filtersStart - $summaryStart);
        $this->assertStringContainsString('id="tpColPayoutStatus"', $paymentsPanel);
        $this->assertStringNotContainsString('tpColPayoutStatus', $summaryPanel);
        $this->assertStringNotContainsString('Статус выплаты', $summaryPanel);
    }

    public function test_reopening_with_method_filter_does_not_uncheck_payout_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['method' => 'sbp']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertStringContainsString('value="sbp" selected', $html);
        $this->assertStringContainsString('<th>Статус выплаты</th>', $html);
        $this->assertStringContainsString('checked', $this->payoutStatusCheckboxTag($html));
        $this->assertStringContainsString('payout_status: true', $html);
    }

    public function test_reopening_with_status_filter_does_not_uncheck_payout_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['status' => 'CONFIRMED']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertStringContainsString('value="CONFIRMED" selected', $html);
        $this->assertStringContainsString('checked', $this->payoutStatusCheckboxTag($html));
        $this->assertStringNotContainsString('disabled', $this->payoutStatusCheckboxTag($html));
    }

    public function test_reopening_with_without_payout_keeps_payout_status_column_checked(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['without_payout' => 1]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<th>Выплата<\/th>\s*<th>Статус выплаты<\/th>\s*<th>Способ<\/th>/',
            $html
        );
        $this->assertStringContainsString('checked', $this->payoutStatusCheckboxTag($html));
        $this->assertStringContainsString('payout_status: true', $html);
    }

    public function test_datatable_payout_status_and_time_for_completed_initiated_rejected_and_retry(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $noPayout = $this->makePayment(['amount' => 10000]);
        $completed = $this->makePayment(['amount' => 20000]);
        $initiated = $this->makePayment(['amount' => 30000]);
        $rejectedOnly = $this->makePayment(['amount' => 40000]);
        $retryAfterReject = $this->makePayment(['amount' => 50000]);
        $creditChecking = $this->makePayment(['amount' => 60000]);
        $completedNoAt = $this->makePayment(['amount' => 70000]);

        $completedAt = Carbon::parse('2026-09-10 14:35:00');
        $this->makePayout($completed, [
            'status' => 'COMPLETED',
            'amount' => 18500,
            'net_amount' => 18500,
            'completed_at' => $completedAt,
        ]);

        $initiatedPayout = $this->makePayout($initiated, [
            'status' => 'INITIATED',
            'amount' => 27000,
            'net_amount' => 27000,
        ]);
        $initiatedAt = Carbon::parse('2026-09-11 09:00:00');
        TinkoffPayout::query()->whereKey($initiatedPayout->id)->update(['updated_at' => $initiatedAt]);

        $rejectedPayout = $this->makePayout($rejectedOnly, [
            'status' => 'REJECTED',
            'amount' => 36000,
            'net_amount' => 36000,
        ]);
        $rejectedAt = Carbon::parse('2026-09-08 18:20:00');
        TinkoffPayout::query()->whereKey($rejectedPayout->id)->update(['updated_at' => $rejectedAt]);

        $this->makePayout($retryAfterReject, [
            'status' => 'REJECTED',
            'amount' => 1000,
            'net_amount' => 1000,
        ]);
        $retryCompletedAt = Carbon::parse('2026-09-12 11:05:00');
        $this->makePayout($retryAfterReject, [
            'status' => 'COMPLETED',
            'amount' => 45000,
            'net_amount' => 45000,
            'completed_at' => $retryCompletedAt,
        ]);

        $this->makePayout($creditChecking, [
            'status' => 'CREDIT_CHECKING',
            'amount' => 54000,
            'net_amount' => 54000,
        ]);

        $completedNoAtPayout = $this->makePayout($completedNoAt, [
            'status' => 'COMPLETED',
            'amount' => 63000,
            'net_amount' => 63000,
            'completed_at' => null,
        ]);
        $fallbackAt = Carbon::parse('2026-09-09 07:15:00');
        TinkoffPayout::query()->whereKey($completedNoAtPayout->id)->update(['updated_at' => $fallbackAt]);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertNull($rows[$noPayout->id]['payout_status']);
        $this->assertNull($rows[$noPayout->id]['payout_status_at']);
        $this->assertArrayNotHasKey('payout_status_at_raw', $rows[$noPayout->id]);

        $this->assertSame('COMPLETED', $rows[$completed->id]['payout_status']);
        $this->assertSame('10.09.2026 14:35', $rows[$completed->id]['payout_status_at']);
        $this->assertEquals(185.0, (float) $rows[$completed->id]['payout_amount']);

        $this->assertSame('INITIATED', $rows[$initiated->id]['payout_status']);
        $this->assertSame('11.09.2026 09:00', $rows[$initiated->id]['payout_status_at']);

        $this->assertSame('REJECTED', $rows[$rejectedOnly->id]['payout_status']);
        $this->assertSame('08.09.2026 18:20', $rows[$rejectedOnly->id]['payout_status_at']);
        $this->assertNull($rows[$rejectedOnly->id]['payout_amount']);

        $this->assertSame('COMPLETED', $rows[$retryAfterReject->id]['payout_status']);
        $this->assertSame('12.09.2026 11:05', $rows[$retryAfterReject->id]['payout_status_at']);
        $this->assertEquals(450.0, (float) $rows[$retryAfterReject->id]['payout_amount']);

        $this->assertSame('CREDIT_CHECKING', $rows[$creditChecking->id]['payout_status']);

        $this->assertSame('COMPLETED', $rows[$completedNoAt->id]['payout_status']);
        $this->assertSame('09.09.2026 07:15', $rows[$completedNoAt->id]['payout_status_at']);
    }

    public function test_datatable_search_does_not_match_payout_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $needle = $this->makePayment([
            'order_id' => 'order-visible-needle',
            'deal_id' => 'deal-plain',
        ]);
        $hidden = $this->makePayment([
            'order_id' => 'order-hidden-status',
            'deal_id' => 'deal-hidden',
        ]);
        $this->makePayout($needle, ['status' => 'INITIATED']);
        $this->makePayout($hidden, [
            'status' => 'COMPLETED',
            'completed_at' => Carbon::parse('2026-09-10 14:35:00'),
        ]);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'search' => ['value' => 'COMPLETED'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains($hidden->id, $ids);
        $this->assertNotContains($needle->id, $ids);
    }

    public function test_non_superadmin_does_not_see_foreign_payout_status(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $own = $this->makePayment(['partner_id' => $this->partner->id]);
        $foreign = $this->makePayment(['partner_id' => $this->foreignPartner->id]);
        $this->makePayout($own, ['status' => 'COMPLETED', 'completed_at' => Carbon::parse('2026-09-10 10:00:00')]);
        $this->makePayout($foreign, ['status' => 'REJECTED']);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertArrayHasKey($own->id, $rows);
        $this->assertSame('COMPLETED', $rows[$own->id]['payout_status']);
        $this->assertArrayNotHasKey($foreign->id, $rows);
    }

    public function test_days_and_months_views_do_not_render_payout_status_column(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['amount' => 12000]);
        $payment->forceFill(['created_at' => Carbon::parse('2026-09-10 12:00:00')])->save();
        $this->makePayout($payment, [
            'status' => 'COMPLETED',
            'completed_at' => Carbon::parse('2026-09-10 13:00:00'),
        ]);

        foreach (['days', 'months'] as $view) {
            $html = $this->get(route('reports.tbank-payments.index', ['view' => $view]))
                ->assertOk()
                ->getContent();

            preg_match('/id="tbank-payments-table"[\s\S]*?<thead>([\s\S]*?)<\/thead>/', $html, $theadMatch);
            $thead = $theadMatch[1] ?? '';
            $this->assertStringNotContainsString('<th>Статус выплаты</th>', $thead);
            $this->assertStringContainsString('<th>Выплата</th>', $thead);

            $row = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1, 'view' => $view]))
                ->assertOk()
                ->json('data.0');

            if (is_array($row)) {
                $this->assertArrayNotHasKey('payout_status', $row);
                $this->assertArrayNotHasKey('payout_status_at', $row);
            }
        }
    }

    public function test_saved_hidden_payout_status_is_not_forced_visible_in_settings(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => [
                'payout_status' => false,
                'payout_amount' => true,
            ],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get('/admin/reports/tbank-payments/columns-settings')
            ->assertOk()
            ->assertJsonPath('payout_status', false)
            ->assertJsonPath('payout_amount', true);

        $html = $this->get(route('reports.tbank-payments.index'))->assertOk()->getContent();
        $this->assertStringContainsString('payout_status: true', $html);
    }

    public function test_legacy_saved_columns_without_payout_status_key_do_not_store_false(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->post('/admin/reports/tbank-payments/columns-settings', [
            'columns' => [
                'partner' => true,
                'deal_id' => false,
            ],
        ], ['HTTP_ACCEPT' => 'text/html'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $saved = $this->get('/admin/reports/tbank-payments/columns-settings')
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('payout_status', $saved);
        $this->assertSame(false, $saved['deal_id'] ?? null);
    }

    public function test_toolbar_total_does_not_include_payout_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['amount' => 150000]);
        $this->makePayout($payment, ['status' => 'COMPLETED', 'amount' => 140000, 'net_amount' => 140000]);

        $this->get(route('reports.tbank-payments.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(1500.0, 0, '', ' '),
                'total_raw' => 1500.0,
            ])
            ->assertJsonMissingPath('payout_status');
    }

    public function test_without_payout_filter_still_returns_rejected_status_when_amount_is_dash(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $noPayout = $this->makePayment(['amount' => 10000]);
        $rejectedOnly = $this->makePayment(['amount' => 20000]);
        $completed = $this->makePayment(['amount' => 30000]);
        $this->makePayout($rejectedOnly, ['status' => 'REJECTED']);
        $this->makePayout($completed, ['status' => 'COMPLETED', 'completed_at' => Carbon::parse('2026-09-10 10:00:00')]);

        $rows = collect($this->datatableRows(['without_payout' => 1]))->keyBy('id');

        $this->assertArrayHasKey($noPayout->id, $rows);
        $this->assertNull($rows[$noPayout->id]['payout_status']);
        $this->assertNull($rows[$noPayout->id]['payout_status_at']);
        $this->assertNull($rows[$noPayout->id]['payout_amount']);

        $this->assertArrayHasKey($rejectedOnly->id, $rows);
        $this->assertSame('REJECTED', $rows[$rejectedOnly->id]['payout_status']);
        $this->assertNull($rows[$rejectedOnly->id]['payout_amount']);

        $this->assertArrayNotHasKey($completed->id, $rows);
    }

    public function test_payment_status_filter_still_returns_payout_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $confirmedRejected = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 11000]);
        $formCompleted = $this->makePayment(['status' => 'FORM', 'amount' => 22000]);
        $this->makePayout($confirmedRejected, ['status' => 'REJECTED']);
        $this->makePayout($formCompleted, [
            'status' => 'COMPLETED',
            'completed_at' => Carbon::parse('2026-09-10 11:00:00'),
        ]);

        $rows = collect($this->datatableRows(['status' => 'CONFIRMED']))->keyBy('id');

        $this->assertArrayHasKey($confirmedRejected->id, $rows);
        $this->assertSame('REJECTED', $rows[$confirmedRejected->id]['payout_status']);
        $this->assertArrayNotHasKey($formCompleted->id, $rows);
    }

    public function test_datatable_search_does_not_match_payout_status_time(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $hidden = $this->makePayment([
            'order_id' => 'order-hidden-time',
            'deal_id' => 'deal-hidden-time',
        ]);
        $this->makePayout($hidden, [
            'status' => 'REJECTED',
            'completed_at' => null,
        ]);
        $payoutId = (int) TinkoffPayout::query()->where('payment_id', $hidden->id)->value('id');
        TinkoffPayout::query()->whereKey($payoutId)->update([
            'updated_at' => Carbon::parse('2026-08-03 14:35:00'),
        ]);

        foreach (['REJECTED', '03.08.2026', '14:35', '03.08.2026 14:35'] as $needle) {
            $ids = collect($this->datatableRows([
                'search' => ['value' => $needle],
            ]))->pluck('id')->map(fn ($id) => (int) $id)->all();

            $this->assertNotContains($hidden->id, $ids, "search={$needle}");
        }
    }

    public function test_sorting_by_payout_status_ascending_puts_completed_before_rejected(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        [$completed, $initiated, $rejected] = $this->makePaymentsForPayoutStatusSort();

        $asc = $this->datatableOrderedByPayoutStatus('asc');
        $this->assertNotSame('', trim((string) $asc->getContent()));

        $ascStatuses = collect($asc->json('data'))
            ->whereIn('id', [$completed->id, $initiated->id, $rejected->id])
            ->pluck('payout_status')
            ->values()
            ->all();
        $this->assertSame(['COMPLETED', 'INITIATED', 'REJECTED'], $ascStatuses);
    }

    public function test_sorting_by_payout_status_descending_puts_rejected_before_completed(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        [$completed, $initiated, $rejected] = $this->makePaymentsForPayoutStatusSort();

        $desc = $this->datatableOrderedByPayoutStatus('desc');
        $this->assertNotSame('', trim((string) $desc->getContent()));

        $descStatuses = collect($desc->json('data'))
            ->whereIn('id', [$completed->id, $initiated->id, $rejected->id])
            ->pluck('payout_status')
            ->values()
            ->all();
        $this->assertSame(['REJECTED', 'INITIATED', 'COMPLETED'], $descStatuses);
    }

    public function test_non_ajax_data_still_returns_payout_status_fields(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['amount' => 15000]);
        $this->makePayout($payment, [
            'status' => 'COMPLETED',
            'completed_at' => Carbon::parse('2026-09-10 14:35:00'),
        ]);

        $response = $this->get(route('reports.tbank-payments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), ['HTTP_ACCEPT' => 'text/html']);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $row = collect($response->json('data'))->firstWhere('id', $payment->id);
        $this->assertIsArray($row);
        $this->assertSame('COMPLETED', $row['payout_status']);
        $this->assertSame('10.09.2026 14:35', $row['payout_status_at']);
        $this->assertArrayNotHasKey('payout_status_at_raw', $row);
    }

    public function test_reopening_with_saved_page_length_keeps_payout_status_default(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'page_length' => 50,
            'columns' => [
                'deal_id' => false,
                'amount' => true,
            ],
        ])->assertOk();

        $html = $this->get(route('reports.tbank-payments.index', ['method' => 'sbp']))
            ->assertOk()
            ->assertViewHas('tbankPaymentsPageLength', 50)
            ->getContent();

        $this->assertStringContainsString('payout_status: true', $html);
        $this->assertStringContainsString('checked', $this->payoutStatusCheckboxTag($html));
        $this->assertStringContainsString('<th>Статус выплаты</th>', $html);
    }

    public function test_days_columns_settings_do_not_wipe_payments_payout_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => [
                'payout_status' => false,
                'amount' => true,
            ],
        ])->assertOk();

        $this->postJson('/admin/reports/tbank-payments/columns-settings?view=days', [
            'columns' => [
                'period' => true,
                'payout_amount' => false,
                'payout_status' => true,
            ],
        ])->assertOk();

        $payments = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments')
            ->first();
        $this->assertNotNull($payments);
        $this->assertSame(false, $payments->columns['payout_status'] ?? null);

        $this->get('/admin/reports/tbank-payments/columns-settings')
            ->assertOk()
            ->assertJsonPath('payout_status', false);

        $days = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments_by_day')
            ->first();
        $this->assertNotNull($days);
        $this->assertSame(false, $days->columns['payout_amount'] ?? null);
    }

    public function test_view_switch_js_thead_keeps_payout_status_on_payments_and_drops_it_on_summary(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))->assertOk()->getContent();

        $fnPos = strpos($html, 'function tbankPaymentsTheadHtml(view)');
        $this->assertNotFalse($fnPos);
        $fnChunk = substr($html, $fnPos, 1200);
        preg_match_all("/return '<tr>'[\\s\\S]*?<\\/tr>';/", $fnChunk, $returns);
        $this->assertCount(2, $returns[0], $fnChunk);
        $paymentsHtml = $returns[0][0];
        $summaryHtml = $returns[0][1];

        $this->assertStringContainsString('<th>Выплата</th><th>Статус выплаты</th><th>Способ</th>', $paymentsHtml);
        $this->assertStringNotContainsString('Статус выплаты', $summaryHtml);
        $this->assertStringContainsString('<th>Выплата</th>', $summaryHtml);

        $destroyPos = strpos($html, 'function destroyTbankPaymentsTable()');
        $this->assertNotFalse($destroyPos);
        $destroyChunk = substr($html, $destroyPos, 2200);
        $this->assertStringContainsString('tbankPaymentsTheadHtml(currentView)', $destroyChunk);

        $daysHtml = $this->get(route('reports.tbank-payments.index', ['view' => 'days']))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tp-columns-payments-panel"[^>]*>/', $daysHtml, $paymentsPanelTag));
        $this->assertStringContainsString('d-none', $paymentsPanelTag[0]);
        preg_match('/id="tbank-payments-table"[\s\S]*?<thead>([\s\S]*?)<\/thead>/', $daysHtml, $theadMatch);
        $this->assertStringNotContainsString('<th>Статус выплаты</th>', $theadMatch[1] ?? '');
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

    private function payoutStatusCheckboxTag(string $html): string
    {
        $this->assertSame(1, preg_match('/<input[^>]*id="tpColPayoutStatus"[^>]*>/', $html, $match));

        return $match[0];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function datatableRow(int $paymentId, array $params = []): array
    {
        $row = collect($this->datatableRows($params))->firstWhere('id', $paymentId);
        $this->assertIsArray($row);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function datatableRows(array $params = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.tbank-payments.data', array_merge([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
            ], $params)))
            ->assertOk()
            ->json('data');

        $this->assertIsArray($json);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function payoutStatusOrderParams(string $dir): array
    {
        $names = [
            'id',
            'created_at',
            'partner_id',
            'order_id',
            'amount',
            'platform_commission',
            'payout_amount',
            'payout_status',
            'method',
            'status',
            'deal_id',
            'receipt',
            'actions',
        ];
        $columns = [];
        foreach ($names as $name) {
            $columns[] = [
                'name' => $name,
                'orderable' => 'true',
            ];
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'columns' => $columns,
            'order' => [['column' => 7, 'dir' => $dir]],
        ];
    }

    /**
     * @return array{0: TinkoffPayment, 1: TinkoffPayment, 2: TinkoffPayment}
     */
    private function makePaymentsForPayoutStatusSort(): array
    {
        $rejected = $this->makePayment(['order_id' => 'sort-rejected']);
        $completed = $this->makePayment(['order_id' => 'sort-completed']);
        $initiated = $this->makePayment(['order_id' => 'sort-initiated']);
        $this->makePayout($rejected, ['status' => 'REJECTED']);
        $this->makePayout($completed, ['status' => 'COMPLETED', 'completed_at' => Carbon::parse('2026-09-10 10:00:00')]);
        $this->makePayout($initiated, ['status' => 'INITIATED']);

        return [$completed, $initiated, $rejected];
    }

    private function datatableOrderedByPayoutStatus(string $dir): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->json('GET', route('reports.tbank-payments.data'), $this->payoutStatusOrderParams($dir))
            ->assertOk();
    }
}
