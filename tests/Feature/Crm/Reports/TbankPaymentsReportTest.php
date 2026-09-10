<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\FiscalReceipt;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\UserTableSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankPaymentsReportTest extends CrmTestCase
{
    public function test_tbank_payments_routes_require_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.tbank-payments.index'))->assertForbidden();
        $this->get(route('reports.tbank-payments.total'))->assertForbidden();
        $this->get(route('reports.tbank-payments.partners.search', ['q' => 'x']))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.tbank-payments.data', ['draw' => 1]))
            ->assertForbidden();

        $this->get('/admin/reports/tbank-payments/columns-settings')->assertForbidden();
        $this->postJson('/admin/reports/tbank-payments/columns-settings', ['columns' => ['partner' => true]])->assertForbidden();
    }

    public function test_old_tinkoff_payments_list_url_is_gone(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get('/admin/tinkoff/payments')->assertNotFound();
    }

    public function test_total_endpoint_returns_sum_for_superadmin_and_partner_filter(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->makePayment(['partner_id' => $this->partner->id, 'amount' => 10000]);
        $this->makePayment(['partner_id' => $this->partner->id, 'amount' => 20000]);
        $this->makePayment(['partner_id' => $this->foreignPartner->id, 'amount' => 99900]);

        $this->get(route('reports.tbank-payments.total'))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(1299.0, 0, '', ' '),
                'total_raw' => 1299.0,
            ]);

        $this->get(route('reports.tbank-payments.total', ['partner_id' => $this->partner->id]))
            ->assertOk()
            ->assertJson([
                'total_formatted' => number_format(300.0, 0, '', ' '),
                'total_raw' => 300.0,
            ]);
    }

    public function test_non_superadmin_ignores_foreign_partner_id_and_does_not_see_foreign_rows(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $own = $this->makePayment(['partner_id' => $this->partner->id, 'amount' => 15000]);
        $foreign = $this->makePayment(['partner_id' => $this->foreignPartner->id, 'amount' => 88000]);

        $this->get(route('reports.tbank-payments.total', ['partner_id' => $this->foreignPartner->id]))
            ->assertOk()
            ->assertJsonPath('total_raw', 150);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'partner_id' => $this->foreignPartner->id,
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_columns_settings_saved_and_loaded(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payload = [
            'columns' => [
                'partner' => true,
                'deal_id' => false,
            ],
        ];

        $this->postJson('/admin/reports/tbank-payments/columns-settings', $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_tbank_payments')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);

        $this->get('/admin/reports/tbank-payments/columns-settings')
            ->assertOk()
            ->assertExactJson($payload['columns']);
    }

    public function test_partners_search_returns_results(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $p = Partner::factory()->create(['title' => 'TBANK Partner Search']);

        $json = $this->get(route('reports.tbank-payments.partners.search', ['q' => 'TBANK']))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('results', $json);
        $ids = collect($json['results'])->pluck('id')->all();
        $this->assertContains($p->id, $ids);
    }

    public function test_datatable_returns_expected_fields(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->partner->update(['title' => 'Tbank Partner Test']);
        $createdAt = Carbon::parse('2026-09-07 11:30:00');

        $payment = $this->makePayment([
            'partner_id' => $this->partner->id,
            'order_id' => 'order-tbank-report-1',
            'deal_id' => 'deal-tbank-report-1',
            'amount' => 150000,
            'status' => 'CONFIRMED',
        ]);
        $payment->forceFill(['created_at' => $createdAt])->save();

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $payment->id);

        $this->assertIsArray($row);
        $this->assertSame('Tbank Partner Test', $row['partner_title']);
        $this->assertSame('order-tbank-report-1', $row['order_id']);
        $this->assertSame('deal-tbank-report-1', $row['deal_id']);
        $this->assertSame('CONFIRMED', $row['status']);
        $this->assertSame('Карта', $row['method_label']);
        $this->assertEquals(1500.0, (float) $row['amount']);
        $this->assertEquals(0.0, (float) $row['platform_commission']);
        $this->assertNull($row['payout_amount']);
        $this->assertFalse((bool) ($row['has_receipt'] ?? true));
        $this->assertNull($row['receipt_url']);
        $this->assertSame('Чек не сформирован', (string) ($row['receipt_hint'] ?? ''));
        $this->assertFalse((bool) ($row['has_return_receipt'] ?? true));
        $this->assertNull($row['return_receipt_url']);
        $this->assertSame('2026-09-07 11:30:00', $row['created_at']);
        $this->assertStringContainsString('/admin/tinkoff/payments/'.$payment->id, (string) $row['show_url']);
    }

    public function test_datatable_platform_commission_matches_payments_report_current_rules(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0,
        ]);

        $confirmed = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 150000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
        $form = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 150000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        );

        $confirmedRow = $rows->firstWhere('id', $confirmed->id);
        $formRow = $rows->firstWhere('id', $form->id);

        $this->assertIsArray($confirmedRow);
        $this->assertEquals(30.00, (float) $confirmedRow['platform_commission']);
        $this->assertIsArray($formRow);
        $this->assertEquals(30.00, (float) $formRow['platform_commission']);

        $totalJson = $this->get(route('reports.tbank-payments.total'))
            ->assertOk()
            ->json();
        $this->assertArrayNotHasKey('platform_commission_formatted', $totalJson);
        $this->assertArrayNotHasKey('platform_commission_raw', $totalJson);
    }

    public function test_non_superadmin_sees_platform_commission_without_additional_value_permission(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'sbp',
            'platform_percent' => 1.00,
            'platform_min_fixed' => 0,
        ]);

        $payment = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 20000,
            'method' => 'sbp',
            'status' => 'CONFIRMED',
        ]);

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $payment->id);

        $this->assertIsArray($row);
        $this->assertEquals(2.00, (float) $row['platform_commission']);
        $this->assertFalse($actor->can('reports.additional.value.view'));
    }

    public function test_datatable_receipt_fields_from_fiscal_resolver(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 50000,
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => 912345001,
        ]);

        $ledgerPayment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '912345001',
            'deal_id' => $payment->deal_id,
        ]);

        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount_cents' => 50000,
            'receipt_url' => 'https://receipts.ru/tbank-report-income',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledgerPayment->id,
            'type' => FiscalReceipt::TYPE_INCOME_RETURN,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount_cents' => 50000,
            'receipt_url' => 'https://receipts.ru/tbank-report-return',
        ]);

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $payment->id);

        $this->assertIsArray($row);
        $this->assertTrue((bool) ($row['has_receipt'] ?? false));
        $this->assertSame('https://receipts.ru/tbank-report-income', $row['receipt_url']);
        $this->assertSame('Чек сформирован', (string) ($row['receipt_hint'] ?? ''));
        $this->assertTrue((bool) ($row['has_return_receipt'] ?? false));
        $this->assertSame('https://receipts.ru/tbank-report-return', $row['return_receipt_url']);
        $this->assertSame('Чек возврата сформирован', (string) ($row['return_receipt_hint'] ?? ''));
    }

    public function test_datatable_search_matches_order_and_partner(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $hit = $this->makePayment([
            'partner_id' => $this->partner->id,
            'order_id' => 'uniq-order-needle-42',
            'deal_id' => 'other-deal',
            'amount' => 1000,
        ]);
        $miss = $this->makePayment([
            'partner_id' => $this->partner->id,
            'order_id' => 'unrelated-order',
            'deal_id' => 'unrelated-deal',
            'amount' => 2000,
        ]);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'search' => ['value' => 'uniq-order-needle'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($hit->id, $ids);
        $this->assertNotContains($miss->id, $ids);
    }

    public function test_datatable_payout_amount_for_completed_initiated_and_rejected(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $noPayout = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'status' => 'CONFIRMED',
        ]);
        $completed = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 20000,
            'status' => 'CONFIRMED',
        ]);
        $deferred = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 30000,
            'status' => 'CONFIRMED',
        ]);
        $rejectedOnly = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 40000,
            'status' => 'CONFIRMED',
        ]);
        $retryAfterReject = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 50000,
            'status' => 'CONFIRMED',
        ]);

        $this->makePayout($completed, [
            'amount' => 18500,
            'net_amount' => 18500,
            'status' => 'COMPLETED',
        ]);
        $this->makePayout($deferred, [
            'amount' => 27000,
            'net_amount' => 27000,
            'status' => 'INITIATED',
            'when_to_run' => Carbon::parse('2026-09-10 12:00:00'),
        ]);
        $this->makePayout($rejectedOnly, [
            'amount' => 36000,
            'net_amount' => 36000,
            'status' => 'REJECTED',
        ]);
        $this->makePayout($retryAfterReject, [
            'amount' => 1000,
            'net_amount' => 1000,
            'status' => 'REJECTED',
        ]);
        $this->makePayout($retryAfterReject, [
            'amount' => 45000,
            'net_amount' => 45000,
            'status' => 'COMPLETED',
        ]);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertNull($rows[$noPayout->id]['payout_amount']);
        $this->assertEquals(185.0, (float) $rows[$completed->id]['payout_amount']);
        $this->assertEquals(270.0, (float) $rows[$deferred->id]['payout_amount']);
        $this->assertNull($rows[$rejectedOnly->id]['payout_amount']);
        $this->assertEquals(450.0, (float) $rows[$retryAfterReject->id]['payout_amount']);
        $this->assertArrayNotHasKey('payout_amount_cents', $rows[$completed->id]);
    }

    public function test_user_with_report_permission_can_open_own_card_and_not_foreign(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $own = $this->makePayment(['partner_id' => $this->partner->id]);
        $foreign = $this->makePayment(['partner_id' => $this->foreignPartner->id]);

        $this->get('/admin/tinkoff/payments/'.$own->id)
            ->assertOk()
            ->assertDontSee('Выплатить сейчас', false);

        $this->get('/admin/tinkoff/payments/'.$foreign->id)->assertForbidden();
    }

    public function test_invalid_status_filter_returns_422_with_field_error(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('reports.tbank-payments.total', ['status' => 'NOT_A_STATUS']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->get(route('reports.tbank-payments.index', ['status' => 'all', 'user_id' => 1]))
            ->assertOk();
    }

    public function test_status_all_does_not_open_filters_or_select_a_real_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', [
            'status' => 'all',
            'user_id' => 1,
        ]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $this->assertStringContainsString('id="tbankPaymentsFiltersCollapse"', $html);
        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringNotContainsString('show', $collapseTag[0]);
        $this->assertStringContainsString(
            '<option value="" selected>Все статусы</option>',
            $html
        );
        $this->assertStringNotContainsString('value="CONFIRMED" selected', $html);
        $this->assertStringNotContainsString('value="all" selected', $html);
    }

    public function test_first_open_keeps_filters_collapsed_and_all_columns_visible(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->assertViewHas('tbankPaymentsPageLength', 10)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringNotContainsString('show', $collapseTag[0]);
        $this->assertStringContainsString('<option value="" selected>Все статусы</option>', $html);
        $this->assertStringContainsString('<option value="" selected>Все способы</option>', $html);
        $this->assertSame(1, preg_match('/<input\b[^>]*\bid="tp-filter-without-payout"[^>]*>/', $html, $checkboxTag));
        $this->assertStringNotContainsString('checked', $checkboxTag[0]);
        foreach (['created_at', 'partner', 'order_id', 'amount', 'platform_commission', 'payout_amount', 'method', 'status', 'deal_id', 'receipt', 'actions'] as $key) {
            $this->assertMatchesRegularExpression(
                '/class="form-check-input tbank-payments-column-toggle"[^>]*data-column-key="'.$key.'"[^>]*checked/',
                $html
            );
        }
        $this->assertStringContainsString('pageLength: 10', $html);
        $this->assertStringContainsString('persistPageLength: true', $html);
    }

    public function test_confirmed_status_filter_opens_panel_and_selects_option(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['status' => 'CONFIRMED']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringContainsString('show', $collapseTag[0]);
        $this->assertStringContainsString('value="CONFIRMED" selected', $html);
    }

    public function test_status_filter_limits_total_and_datatable_rows(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $confirmed = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 10000]);
        $rejected = $this->makePayment(['status' => 'REJECTED', 'amount' => 50000]);

        $this->get(route('reports.tbank-payments.total', ['status' => 'CONFIRMED']))
            ->assertOk()
            ->assertJsonPath('total_raw', 100);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'status' => 'CONFIRMED',
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($confirmed->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
    }

    public function test_without_payout_filter_matches_column_dash_across_all_payment_statuses(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $noPayoutConfirmed = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 10000]);
        $noPayoutNew = $this->makePayment(['status' => 'NEW', 'amount' => 20000]);
        $noPayoutForm = $this->makePayment(['status' => 'FORM', 'amount' => 30000]);
        $noPayoutCanceled = $this->makePayment(['status' => 'CANCELED', 'amount' => 40000]);
        $rejectedOnly = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 50000]);
        $completed = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 60000]);
        $initiated = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 70000]);
        $retryAfterReject = $this->makePayment(['status' => 'CONFIRMED', 'amount' => 80000]);

        $this->makePayout($rejectedOnly, ['status' => 'REJECTED', 'amount' => 45000, 'net_amount' => 45000]);
        $this->makePayout($completed, ['status' => 'COMPLETED', 'amount' => 54000, 'net_amount' => 54000]);
        $this->makePayout($initiated, [
            'status' => 'INITIATED',
            'amount' => 63000,
            'net_amount' => 63000,
            'when_to_run' => Carbon::parse('2026-09-10 12:00:00'),
        ]);
        $this->makePayout($retryAfterReject, ['status' => 'REJECTED', 'amount' => 1000, 'net_amount' => 1000]);
        $this->makePayout($retryAfterReject, ['status' => 'COMPLETED', 'amount' => 72000, 'net_amount' => 72000]);

        $this->get(route('reports.tbank-payments.total', ['without_payout' => 1]))
            ->assertOk()
            ->assertJsonPath('total_raw', 1500);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'without_payout' => 1,
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($noPayoutConfirmed->id, $ids);
        $this->assertContains($noPayoutNew->id, $ids);
        $this->assertContains($noPayoutForm->id, $ids);
        $this->assertContains($noPayoutCanceled->id, $ids);
        $this->assertContains($rejectedOnly->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($initiated->id, $ids);
        $this->assertNotContains($retryAfterReject->id, $ids);
    }

    public function test_without_payout_filter_opens_panel_and_checks_checkbox(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['without_payout' => 1]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringContainsString('show', $collapseTag[0]);
        $this->assertSame(1, preg_match('/<input\b[^>]*\bid="tp-filter-without-payout"[^>]*>/', $html, $checkboxTag));
        $this->assertStringContainsString('checked', $checkboxTag[0]);
        $this->assertStringContainsString('name="without_payout"', $checkboxTag[0]);
        $this->assertStringContainsString('Не было выплаты', $html);
    }

    public function test_without_payout_all_or_zero_does_not_open_filters(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        foreach (['all', '0', ''] as $value) {
            $html = $this->get(route('reports.tbank-payments.index', ['without_payout' => $value]))
                ->assertOk()
                ->assertViewHas('tpHasActiveFilters', false)
                ->getContent();

            $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
            $this->assertStringNotContainsString('show', $collapseTag[0]);
            $this->assertSame(1, preg_match('/<input\b[^>]*\bid="tp-filter-without-payout"[^>]*>/', $html, $checkboxTag));
            $this->assertStringNotContainsString('checked', $checkboxTag[0]);
        }
    }

    public function test_invalid_without_payout_filter_returns_422_with_field_error(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('reports.tbank-payments.total', ['without_payout' => 'maybe']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['without_payout']);
    }

    public function test_invalid_method_filter_returns_422_with_field_error(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('reports.tbank-payments.total', ['method' => 'cash']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['method']);
    }

    public function test_method_all_does_not_open_filters_or_select_a_real_method(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', [
            'method' => 'all',
        ]))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', false)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringNotContainsString('show', $collapseTag[0]);
        $this->assertStringContainsString('<option value="" selected>Все способы</option>', $html);
        $this->assertStringNotContainsString('value="card" selected', $html);
        $this->assertStringNotContainsString('value="sbp" selected', $html);
        $this->assertStringNotContainsString('value="tpay" selected', $html);
        $this->assertStringNotContainsString('value="all" selected', $html);
    }

    public function test_method_filter_opens_panel_and_selects_option(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['method' => 'sbp']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="tbankPaymentsFiltersCollapse"[^>]*>/', $html, $collapseTag));
        $this->assertStringContainsString('show', $collapseTag[0]);
        $this->assertStringContainsString('value="sbp" selected', $html);
        $this->assertStringContainsString('id="tp-filter-method"', $html);
        $this->assertStringContainsString('СБП', $html);
        $this->assertStringContainsString('T‑Pay', $html);
    }

    public function test_method_filter_limits_total_and_datatable_rows(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $card = $this->makePayment(['method' => 'card', 'amount' => 10000]);
        $sbp = $this->makePayment(['method' => 'sbp', 'amount' => 50000]);
        $tpay = $this->makePayment(['method' => 'tpay', 'amount' => 70000]);

        $this->get(route('reports.tbank-payments.total', ['method' => 'sbp']))
            ->assertOk()
            ->assertJsonPath('total_raw', 500);

        $rows = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'method' => 'sbp',
                ]))
                ->assertOk()
                ->json('data')
        );
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($sbp->id, $ids);
        $this->assertNotContains($card->id, $ids);
        $this->assertNotContains($tpay->id, $ids);
        $this->assertSame('СБП', $rows->firstWhere('id', $sbp->id)['method_label']);
    }

    public function test_datatable_empty_method_label_is_dash(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['method' => null]);

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $payment->id);

        $this->assertIsArray($row);
        $this->assertSame('—', $row['method_label']);
    }

    public function test_date_filter_limits_total(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $inRange = $this->makePayment(['amount' => 20000]);
        $inRange->forceFill(['created_at' => Carbon::parse('2026-09-05 12:00:00')])->save();
        $outOfRange = $this->makePayment(['amount' => 80000]);
        $outOfRange->forceFill(['created_at' => Carbon::parse('2026-08-01 12:00:00')])->save();

        $this->get(route('reports.tbank-payments.total', [
            'created_from' => '2026-09-01',
            'created_to' => '2026-09-07',
        ]))
            ->assertOk()
            ->assertJsonPath('total_raw', 200);
    }

    public function test_reports_tab_is_hidden_without_permission_and_visible_with_it(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('payments'))
            ->assertOk()
            ->assertDontSee('Платежи T‑Bank', false)
            ->assertDontSee(route('reports.tbank-payments.index'), false);

        $this->grantPermissionToActor($this->user, 'reports.tbank.payments.view');
        $this->user->unsetRelation('role');
        $this->actingAs($this->user->fresh());

        $this->get(route('payments'))
            ->assertOk()
            ->assertSee('Платежи T‑Bank', false)
            ->assertSee(route('reports.tbank-payments.index'), false);
    }

    public function test_commissions_toolbar_is_real_link_not_js_handler(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            preg_match('/<a\b[^>]*\bid="tp-toolbar-commissions"[^>]*>/', $html, $linkTag)
        );
        $this->assertStringContainsString('tbank-commissions', $linkTag[0]);
        $this->assertStringContainsString('href=', $linkTag[0]);
    }

    public function test_guest_on_old_list_url_gets_404_not_login_redirect(): void
    {
        $this->get('/admin/tinkoff/payments')->assertNotFound();
    }

    public function test_unknown_payment_card_id_is_not_found(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get('/admin/tinkoff/payments/abc')->assertNotFound();
        $this->get('/admin/tinkoff/payments/999999991')->assertNotFound();
    }

    public function test_manage_tbank_without_report_permission_can_open_own_card_with_payout_buttons(): void
    {
        $actor = $this->createUserWithoutPermission('manage.payment.method.tbank', $this->partner);
        $this->grantPermissionToActor($actor, 'manage.payment.method.tbank');
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $own = $this->makePayment([
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-manage-only-1',
            'status' => 'CONFIRMED',
        ]);
        $foreign = $this->makePayment([
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'deal-manage-only-foreign',
            'status' => 'CONFIRMED',
        ]);

        $this->get('/admin/tinkoff/payments/'.$own->id)
            ->assertOk()
            ->assertSee('Выплатить сейчас', false);

        $this->get('/admin/tinkoff/payments/'.$foreign->id)->assertForbidden();
    }

    public function test_datatable_search_matches_deal_id(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $hit = $this->makePayment([
            'order_id' => 'unrelated-order-a',
            'deal_id' => 'uniq-deal-needle-99',
        ]);
        $miss = $this->makePayment([
            'order_id' => 'unrelated-order-b',
            'deal_id' => 'other-deal',
        ]);

        $ids = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'search' => ['value' => 'uniq-deal-needle'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($hit->id, $ids);
        $this->assertNotContains($miss->id, $ids);
    }

    public function test_commissions_toolbar_link_requires_settings_commission(): void
    {
        $onlyReport = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($onlyReport);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertDontSee('id="tp-toolbar-commissions"', false);

        $withCommissions = $this->grantTbankPaymentsViewToAdmin();
        $this->grantPermissionToActor($withCommissions, 'settings.commission');
        $this->actingAs($withCommissions);

        $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertSee('id="tp-toolbar-commissions"', false)
            ->assertSee(route('admin.setting.tbankCommissions'), false);
    }

    public function test_superadmin_sees_commissions_toolbar_link(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->assertSee('id="tp-toolbar-commissions"', false)
            ->assertSee(route('admin.setting.tbankCommissions'), false);
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

    private function grantPermissionToActor(\App\Models\User $actor, string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => (int) $actor->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
