<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\FiscalReceipt;
use App\Models\Payment;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Колонки «Чек» и «Комиссия платформы» на вкладке «Платежи T‑Bank».
 */
final class TbankPaymentsReceiptAndCommissionFeatureTest extends CrmTestCase
{
    public function test_guest_cannot_open_report_or_read_receipt_and_commission_json(): void
    {
        Auth::logout();

        $this->get(route('reports.tbank-payments.index'), ['HTTP_ACCEPT' => 'text/html'])
            ->assertRedirect();

        $json = $this->getJson(route('reports.tbank-payments.data', ['draw' => 1]));
        $this->assertContains($json->getStatusCode(), [401, 403]);

        $total = $this->getJson(route('reports.tbank-payments.total'));
        $this->assertContains($total->getStatusCode(), [401, 403]);
    }

    public function test_user_without_report_permission_gets_403_on_data_and_index(): void
    {
        $denied = $this->createUserWithoutPermission('reports.tbank.payments.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.tbank-payments.index'))->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.tbank-payments.data', ['draw' => 1]))
            ->assertForbidden();
        $this->get(route('reports.tbank-payments.total'))->assertForbidden();
    }

    public function test_authorized_viewer_without_additional_value_permission_sees_both_columns_in_markup(): void
    {
        $actor = $this->grantTbankPaymentsViewToAdmin();
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->assertFalse($actor->can('reports.additional.value.view'));

        $html = $this->get(route('reports.tbank-payments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-column-key="platform_commission"', $html);
        $this->assertStringContainsString('data-column-key="receipt"', $html);
        $this->assertStringContainsString('<th>Комиссия платформы</th>', $html);
        $this->assertStringContainsString('<th>Чек</th>', $html);
        $this->assertStringNotContainsString("can('reports.additional.value.view')", $html);
    }

    public function test_first_open_renders_commission_after_amount_and_receipt_before_actions(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<th>Сумма<\/th>\s*<th>Комиссия платформы<\/th>\s*<th>Выплата<\/th>\s*<th>Статус выплаты<\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<th>Deal<\/th>\s*<th>Чек<\/th>\s*<th><\/th>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-column-key="amount"[^>]*>[\s\S]*data-column-key="platform_commission"[^>]*>[\s\S]*data-column-key="payout_amount"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-column-key="deal_id"[^>]*>[\s\S]*data-column-key="receipt"[^>]*>[\s\S]*data-column-key="actions"/',
            $html
        );
        foreach (['platform_commission', 'receipt'] as $key) {
            $this->assertMatchesRegularExpression(
                '/class="form-check-input tbank-payments-column-toggle"[^>]*data-column-key="'.$key.'"[^>]*checked/',
                $html
            );
        }
    }

    public function test_reopening_with_method_filter_does_not_drop_new_column_checkboxes(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('reports.tbank-payments.index', ['method' => 'sbp']))
            ->assertOk()
            ->assertViewHas('tpHasActiveFilters', true)
            ->getContent();

        $this->assertStringContainsString('value="sbp" selected', $html);
        $this->assertStringContainsString('<th>Комиссия платформы</th>', $html);
        $this->assertStringContainsString('<th>Чек</th>', $html);
        foreach (['platform_commission', 'receipt'] as $key) {
            $this->assertMatchesRegularExpression(
                '/class="form-check-input tbank-payments-column-toggle"[^>]*data-column-key="'.$key.'"[^>]*checked/',
                $html
            );
        }
    }

    public function test_saved_hidden_receipt_and_commission_are_not_forced_visible_on_reload(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->postJson('/admin/reports/tbank-payments/columns-settings', [
            'columns' => [
                'platform_commission' => false,
                'receipt' => false,
                'amount' => true,
            ],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->get('/admin/reports/tbank-payments/columns-settings')
            ->assertOk()
            ->assertJsonPath('platform_commission', false)
            ->assertJsonPath('receipt', false)
            ->assertJsonPath('amount', true);

        $html = $this->get(route('reports.tbank-payments.index'))->assertOk()->getContent();
        $this->assertStringContainsString('platform_commission: true', $html);
        $this->assertStringContainsString('receipt: true', $html);
    }

    public function test_legacy_saved_columns_without_new_keys_do_not_store_false_for_them(): void
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

        $this->assertArrayNotHasKey('platform_commission', $saved);
        $this->assertArrayNotHasKey('receipt', $saved);
        $this->assertSame(false, $saved['deal_id'] ?? null);
    }

    public function test_datatable_without_ajax_header_still_returns_receipt_and_commission_fields(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['amount' => 150000]);

        $response = $this->get(route('reports.tbank-payments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]), ['HTTP_ACCEPT' => 'text/html']);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $row = collect($response->json('data'))->firstWhere('id', $payment->id);
        $this->assertIsArray($row);
        $this->assertArrayHasKey('platform_commission', $row);
        $this->assertArrayHasKey('has_receipt', $row);
        $this->assertArrayHasKey('receipt_url', $row);
        $this->assertArrayHasKey('receipt_hint', $row);
        $this->assertArrayHasKey('return_receipt_url', $row);
    }

    public function test_toolbar_total_stays_amount_sum_when_commission_is_nonzero(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0,
        ]);
        $this->makePayment(['amount' => 150000, 'method' => 'card']);
        $this->makePayment(['amount' => 50000, 'method' => 'card']);

        $json = $this->get(route('reports.tbank-payments.total'))
            ->assertOk()
            ->json();

        $this->assertEquals(2000.0, (float) $json['total_raw']);
        $this->assertArrayNotHasKey('platform_commission_formatted', $json);
        $this->assertArrayNotHasKey('platform_commission_raw', $json);
        $this->assertSame(number_format(2000.0, 0, '', ' '), $json['total_formatted']);
    }

    public function test_commission_does_not_use_payout_snapshot(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0,
        ]);

        $payment = $this->makePayment([
            'amount' => 150000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);
        $this->makePayout($payment, [
            'amount' => 140000,
            'net_amount' => 140000,
            'platform_fee' => 99999,
            'status' => 'COMPLETED',
        ]);

        $row = $this->datatableRow($payment->id);

        $this->assertEquals(30.00, (float) $row['platform_commission']);
        $this->assertEquals(1400.0, (float) $row['payout_amount']);
        $this->assertNotEquals(999.99, (float) $row['platform_commission']);
    }

    public function test_commission_without_rules_is_zero_not_pick_for_partner_fallback(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment(['amount' => 150000, 'method' => 'card']);

        $row = $this->datatableRow($payment->id);

        $this->assertEquals(0.0, (float) $row['platform_commission']);
        $this->assertNotEquals(30.00, (float) $row['platform_commission']);
    }

    public function test_commission_uses_minimum_fixed_when_it_exceeds_percent(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 1.00,
            'platform_min_fixed' => 10.00,
        ]);

        $payment = $this->makePayment(['amount' => 10000, 'method' => 'card']);

        $row = $this->datatableRow($payment->id);

        $this->assertEquals(10.00, (float) $row['platform_commission']);
    }

    public function test_disabled_commission_rule_is_ignored(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 50.00,
            'platform_min_fixed' => 0,
            'is_enabled' => false,
        ]);

        $payment = $this->makePayment(['amount' => 150000, 'method' => 'card']);

        $row = $this->datatableRow($payment->id);

        $this->assertEquals(0.0, (float) $row['platform_commission']);
    }

    public function test_partner_method_rule_wins_over_global_rule(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'partner_id' => null,
            'method' => null,
            'platform_percent' => 10.00,
            'platform_min_fixed' => 0,
        ]);
        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0,
        ]);

        $payment = $this->makePayment([
            'partner_id' => $this->partner->id,
            'amount' => 150000,
            'method' => 'card',
        ]);

        $row = $this->datatableRow($payment->id);

        $this->assertEquals(30.00, (float) $row['platform_commission']);
    }

    public function test_sbp_payment_does_not_use_card_commission_rule(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 10.00,
            'platform_min_fixed' => 0,
        ]);
        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'sbp',
            'platform_percent' => 1.00,
            'platform_min_fixed' => 0,
        ]);

        $sbp = $this->makePayment(['amount' => 20000, 'method' => 'sbp']);
        $card = $this->makePayment(['amount' => 20000, 'method' => 'card']);

        $this->assertEquals(2.00, (float) $this->datatableRow($sbp->id)['platform_commission']);
        $this->assertEquals(20.00, (float) $this->datatableRow($card->id)['platform_commission']);
    }

    public function test_search_does_not_match_receipt_url_or_commission_amount(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0,
        ]);

        $withReceipt = $this->makePayment([
            'order_id' => 'order-search-miss-1',
            'amount' => 150000,
            'tinkoff_payment_id' => 912345101,
        ]);
        $ledger = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '912345101',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledger->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount_cents' => 150000,
            'receipt_url' => 'https://receipts.ru/tbank-search-needle-xyz',
        ]);

        $hit = $this->makePayment([
            'order_id' => 'uniq-visible-order-77',
            'amount' => 1000,
        ]);

        $byReceipt = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'search' => ['value' => 'tbank-search-needle-xyz'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains($withReceipt->id, $byReceipt);
        $this->assertNotContains($hit->id, $byReceipt);

        $byCommission = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'search' => ['value' => '30.00'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains($withReceipt->id, $byCommission);

        $byOrder = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', [
                    'draw' => 1,
                    'search' => ['value' => 'uniq-visible-order-77'],
                ]))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($hit->id, $byOrder);
        $this->assertNotContains($withReceipt->id, $byOrder);
    }

    public function test_non_receipts_ru_url_is_treated_as_missing_receipt(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment([
            'amount' => 50000,
            'tinkoff_payment_id' => 912345102,
        ]);
        $ledger = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '912345102',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledger->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount_cents' => 50000,
            'receipt_url' => 'https://example.com/not-a-receipt',
        ]);

        $row = $this->datatableRow($payment->id);

        $this->assertFalse((bool) ($row['has_receipt'] ?? true));
        $this->assertNull($row['receipt_url']);
        $this->assertSame('Чек не сформирован', (string) ($row['receipt_hint'] ?? ''));
    }

    public function test_pending_cloudkassir_receipt_shows_hint_without_url(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment([
            'amount' => 50000,
            'tinkoff_payment_id' => 912345103,
        ]);
        $ledger = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '912345103',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledger->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_QUEUED,
            'amount_cents' => 50000,
            'receipt_url' => null,
        ]);

        $row = $this->datatableRow($payment->id);

        $this->assertFalse((bool) ($row['has_receipt'] ?? true));
        $this->assertNull($row['receipt_url']);
        $this->assertSame('Чек формируется (CloudKassir)', (string) ($row['receipt_hint'] ?? ''));
    }

    public function test_latest_income_receipt_url_wins_over_older_one(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payment = $this->makePayment([
            'amount' => 50000,
            'tinkoff_payment_id' => 912345104,
        ]);
        $ledger = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payment_number' => '912345104',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledger->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount_cents' => 50000,
            'receipt_url' => 'https://receipts.ru/tbank-older-income',
        ]);
        FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_id' => $ledger->id,
            'type' => FiscalReceipt::TYPE_INCOME,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount_cents' => 50000,
            'receipt_url' => 'https://receipts.ru/tbank-latest-income',
        ]);

        $row = $this->datatableRow($payment->id);

        $this->assertTrue((bool) ($row['has_receipt'] ?? false));
        $this->assertSame('https://receipts.ru/tbank-latest-income', $row['receipt_url']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datatableRow(int $paymentId): array
    {
        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('reports.tbank-payments.data', ['draw' => 1]))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $paymentId);

        $this->assertIsArray($row);

        return $row;
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
