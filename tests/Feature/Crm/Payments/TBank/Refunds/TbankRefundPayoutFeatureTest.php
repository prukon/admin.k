<?php

namespace Tests\Feature\Crm\Payments\TBank\Refunds;

use App\Jobs\TinkoffProcessRefundJob;
use App\Jobs\TinkoffRunScheduledPayoutsJob;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к отчёту/возвратам, согласованность отмены выплат только через tinkoff_payments,
 * отсутствие одновременно «живой» выплаты в банк + успешного возврата по тем же данным.
 */
class TbankRefundPayoutFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    /**
     * Базовый набор: CRM-платёж T-Bank + intent + строка tinkoff_payments.
     *
     * @return array{payable: Payable, payment: Payment, intent: PaymentIntent, tinkoffPayment: TinkoffPayment}
     */
    private function seedTbankPaymentLine(
        string $suffix,
        int $tbankPid,
        ?string $orderId = null,
        ?string $tinkoffPaymentIdOverride = null
    ): array {
        $orderId ??= 'order-ft-' . $suffix . '-' . $tbankPid;
        $tpIdStr = $tinkoffPaymentIdOverride ?? (string) $tbankPid;

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '100.00',
            'currency' => 'RUB',
            'status' => 'paid',
        ]);

        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 100.00,
            'deal_id' => 'deal-ft-' . $suffix,
            'payment_id' => (string) $tbankPid,
            'payment_number' => (string) $tbankPid,
            'payment_status' => 'CONFIRMED',
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => $tbankPid,
            'tbank_payment_id' => $tbankPid,
            'tbank_order_id' => $orderId,
        ]);

        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => $orderId,
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => $tpIdStr,
            'deal_id' => 'deal-ft-' . $suffix,
            'confirmed_at' => now(),
        ]);

        return [
            'payable' => $payable,
            'payment' => $payment,
            'intent' => $intent,
            'tinkoffPayment' => $tinkoffPayment,
        ];
    }

    public function test_payments_report_page_and_ajax_and_refund_return_200_for_user_with_reports_view(): void
    {
        Queue::fake([TinkoffProcessRefundJob::class]);

        $this->get(route('payments'))->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'))
            ->assertOk();

        $this->get('/admin/reports/payments/columns-settings')->assertOk();
        $this->postJson('/admin/reports/payments/columns-settings', [
            'columns' => ['user_name' => true, 'refund_action' => true],
        ])->assertOk();

        $line = $this->seedTbankPaymentLine('access', 880001);
        TinkoffPayout::create([
            'payment_id' => (int) $line['tinkoffPayment']->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertOk()
            ->assertJsonFragment(['message' => 'refund_created']);

        Queue::assertPushed(TinkoffProcessRefundJob::class);
    }

    public function test_refund_endpoint_forbidden_without_reports_view(): void
    {
        Queue::fake();
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $line = $this->seedTbankPaymentLine('no-perm', 880002);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_refund_forbidden_when_payment_belongs_to_other_partner(): void
    {
        Queue::fake();

        $line = $this->seedTbankPaymentLine('foreign-pay', 880003);
        // Платёж числится за текущим партнёром в данных, но сессия — чужой партнёр
        $this->asForeignUser();

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_refund_blocks_when_bank_payout_payment_id_set_and_getPayments_shows_disabled_button(): void
    {
        Queue::fake();

        $line = $this->seedTbankPaymentLine('blocked', 880004);
        TinkoffPayout::create([
            'payment_id' => (int) $line['tinkoffPayment']->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'CREDIT_CHECKING',
            'tinkoff_payout_payment_id' => '777888',
        ]);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertStatus(422);

        Queue::assertNothingPushed();

        $resp = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));
        $resp->assertOk();
        $row = collect($resp->json('data'))->firstWhere('id', $line['payment']->id);
        $this->assertNotNull($row);
        $html = (string) ($row['refund_action'] ?? '');
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('Возврат запрещён', $html);
    }

    public function test_getPayments_refund_button_not_disabled_when_only_initiated_payout_without_bank_id(): void
    {
        $line = $this->seedTbankPaymentLine('btn-ok', 880005);
        TinkoffPayout::create([
            'payment_id' => (int) $line['tinkoffPayment']->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);

        $resp = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.getPayments'));
        $resp->assertOk();
        $row = collect($resp->json('data'))->firstWhere('id', $line['payment']->id);
        $this->assertNotNull($row);
        $html = (string) ($row['refund_action'] ?? '');
        $this->assertStringNotContainsString('disabled', $html);
    }

    public function test_refund_cancels_all_initiated_payout_rows_for_same_tinkoff_payment(): void
    {
        Queue::fake([TinkoffProcessRefundJob::class]);

        $line = $this->seedTbankPaymentLine('multi', 880006);
        $tpId = (int) $line['tinkoffPayment']->id;

        TinkoffPayout::create([
            'payment_id' => $tpId,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 8000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);
        TinkoffPayout::create([
            'payment_id' => $tpId,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 8000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertOk();

        $active = TinkoffPayout::query()
            ->where('payment_id', $tpId)
            ->where('status', 'INITIATED')
            ->count();
        $this->assertSame(0, $active);

        $withoutBankPid = TinkoffPayout::query()
            ->where('payment_id', $tpId)
            ->where('status', '!=', 'REJECTED')
            ->where(function ($q) {
                $q->whereNull('tinkoff_payout_payment_id')->orWhere('tinkoff_payout_payment_id', '');
            })
            ->count();
        $this->assertSame(0, $withoutBankPid, 'Не должно остаться неотменённых выплат без банковского PaymentId');
    }

    public function test_orphan_payout_not_linked_to_tinkoff_payment_is_not_cancelled_on_refund(): void
    {
        Queue::fake([TinkoffProcessRefundJob::class]);

        $line = $this->seedTbankPaymentLine('orphan', 880007);
        // Выплата с тем же deal_id в CRM, но payment_id не указывает на наш tinkoff_payments.id
        TinkoffPayout::create([
            'payment_id' => 999999999,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['payment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertOk();

        $orphan = TinkoffPayout::query()->where('payment_id', 999999999)->first();
        $this->assertNotNull($orphan);
        $this->assertSame('INITIATED', (string) $orphan->status);

        Queue::assertPushed(TinkoffProcessRefundJob::class);
    }

    public function test_payout_resolved_by_order_id_when_tinkoff_payment_id_differs_from_crm_payment_id(): void
    {
        Queue::fake([TinkoffProcessRefundJob::class]);

        $orderId = 'order-ft-order-only-880008';
        $line = $this->seedTbankPaymentLine('order-fallback', 880008, $orderId, '999888777');
        // CRM и intent ссылаются на 880008, в tinkoff_payments другой tinkoff_payment_id, но тот же order_id
        $line['tinkoffPayment']->tinkoff_payment_id = '999888777';
        $line['tinkoffPayment']->save();

        TinkoffPayout::create([
            'payment_id' => (int) $line['tinkoffPayment']->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addHour(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertOk();

        $p = TinkoffPayout::query()->where('payment_id', (int) $line['tinkoffPayment']->id)->first();
        $this->assertSame('REJECTED', (string) $p->status);
    }

    public function test_after_refund_scheduled_payout_job_does_not_send_init_to_bank(): void
    {
        Queue::fake([TinkoffProcessRefundJob::class]);

        $line = $this->seedTbankPaymentLine('job-safe', 880009);
        $payout = TinkoffPayout::create([
            'payment_id' => (int) $line['tinkoffPayment']->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subMinute(),
        ]);

        Http::fake();

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertOk();

        $payout->refresh();
        $this->assertSame('REJECTED', (string) $payout->status);

        (new TinkoffRunScheduledPayoutsJob())->handle(app(\App\Services\Tinkoff\TinkoffPayoutsService::class));

        $payout->refresh();
        $this->assertSame('REJECTED', (string) $payout->status);
        $this->assertNull($payout->tinkoff_payout_payment_id);

        Http::assertNothingSent();
    }

    public function test_second_refund_request_is_idempotent_and_does_not_resurrect_payout(): void
    {
        Queue::fake([TinkoffProcessRefundJob::class]);

        $line = $this->seedTbankPaymentLine('idemp', 880010);
        TinkoffPayout::create([
            'payment_id' => (int) $line['tinkoffPayment']->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertOk()
            ->assertJsonFragment(['message' => 'refund_created']);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertOk()
            ->assertJsonFragment(['message' => 'refund_already_exists']);

        $initiated = TinkoffPayout::query()
            ->where('payment_id', (int) $line['tinkoffPayment']->id)
            ->where('status', 'INITIATED')
            ->count();
        $this->assertSame(0, $initiated);
    }

    public function test_cannot_refund_while_active_payout_has_bank_id_alongside_initiated_without_bank_id(): void
    {
        Queue::fake();

        $line = $this->seedTbankPaymentLine('block-mix', 880011);
        $tpId = (int) $line['tinkoffPayment']->id;

        TinkoffPayout::create([
            'payment_id' => $tpId,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);
        // Активная выплата в банке (не REJECTED) — запрещает возврат, даже если есть вторая INITIATED
        TinkoffPayout::create([
            'payment_id' => $tpId,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $line['tinkoffPayment']->deal_id,
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'CREDIT_CHECKING',
            'tinkoff_payout_payment_id' => '111222',
        ]);

        $this->postJson(route('payments.refund', ['payment' => $line['payment']->id]), [])
            ->assertStatus(422);

        Queue::assertNothingPushed();

        $stillInit = TinkoffPayout::query()
            ->where('payment_id', $tpId)
            ->where('status', 'INITIATED')
            ->exists();
        $this->assertTrue($stillInit, 'При активной выплате с банковским PaymentId отложенная INITIATED не должна отменяться');
    }
}
