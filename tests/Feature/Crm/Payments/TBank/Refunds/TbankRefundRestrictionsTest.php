<?php

namespace Tests\Feature\Crm\Payments\TBank\Refunds;

use App\Jobs\TinkoffProcessRefundJob;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Payable;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

class TbankRefundRestrictionsTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin(); // refund route находится под reports.view
        session(['current_partner' => $this->partner->id]);
    }

    public function test_refund_is_blocked_when_payout_exists_and_not_rejected(): void
    {
        Queue::fake();

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
            'deal_id' => 'deal-r1',
            'payment_id' => '12345',
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => 12345,
            'tbank_payment_id' => 12345,
        ]);

        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => 'order-r1-block',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => '12345',
            'deal_id' => 'deal-r1',
            'confirmed_at' => now(),
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $tinkoffPayment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-r1',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'COMPLETED',
            'tinkoff_payout_payment_id' => '999001',
            'completed_at' => now(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $payment->id]), [])
            ->assertStatus(422)
            ->assertJson([
                'error' => true,
                'message' => 'Возврат запрещён: выплата уже отправлена в банк (есть PaymentId выплаты).',
            ]);

        Queue::assertNothingPushed();
    }

    public function test_refund_is_allowed_when_payout_rejected(): void
    {
        Queue::fake();

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
            'deal_id' => 'deal-r2',
            'payment_id' => '12346',
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => 12346,
            'tbank_payment_id' => 12346,
        ]);

        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => 'order-r2-ok',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => '12346',
            'deal_id' => 'deal-r2',
            'confirmed_at' => now(),
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $tinkoffPayment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-r2',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'REJECTED',
        ]);

        $this->postJson(route('payments.refund', ['payment' => $payment->id]), [])
            ->assertOk()
            ->assertJson([
                'message' => 'refund_created',
            ]);

        Queue::assertPushed(TinkoffProcessRefundJob::class);
    }

    public function test_refund_allowed_when_payout_is_due_initiated_without_bank_payment_id(): void
    {
        Queue::fake();

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
            'deal_id' => 'deal-r-overdue',
            'payment_id' => '12347',
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => 12347,
            'tbank_payment_id' => 12347,
        ]);

        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => 'order-r-overdue',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => '12347',
            'deal_id' => 'deal-r-overdue',
            'confirmed_at' => now(),
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $tinkoffPayment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-r-overdue',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->subHour(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $payment->id]), [])
            ->assertOk()
            ->assertJson([
                'message' => 'refund_created',
            ]);

        Queue::assertPushed(TinkoffProcessRefundJob::class);

        $payout = TinkoffPayout::query()->where('payment_id', (int) $tinkoffPayment->id)->orderByDesc('id')->first();
        $this->assertNotNull($payout);
        $this->assertSame('REJECTED', (string) $payout->status);
        $this->assertArrayHasKey('cancelled_by_refund', $payout->payload_state ?? []);
    }

    public function test_refund_cancels_payout_by_tinkoff_payment_when_crm_payment_has_no_deal_id(): void
    {
        Queue::fake();

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '100.00',
            'currency' => 'RUB',
            'status' => 'paid',
        ]);

        $tbankPid = 12348;
        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => 'order-refund-no-deal',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => $tbankPid,
            'deal_id' => 'deal-only-tinkoff',
            'confirmed_at' => now(),
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $tinkoffPayment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-only-tinkoff',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);

        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'summ' => 100.00,
            'deal_id' => null,
            'payment_id' => (string) $tbankPid,
            'payment_number' => (string) $tbankPid,
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => $tbankPid,
            'tbank_payment_id' => $tbankPid,
        ]);

        $this->postJson(route('payments.refund', ['payment' => $payment->id]), [])
            ->assertOk()
            ->assertJson([
                'message' => 'refund_created',
            ]);

        Queue::assertPushed(TinkoffProcessRefundJob::class);

        $payout = TinkoffPayout::query()->where('payment_id', (int) $tinkoffPayment->id)->orderByDesc('id')->first();
        $this->assertNotNull($payout);
        $this->assertSame('REJECTED', (string) $payout->status);
        $this->assertNull($payout->when_to_run);
        $this->assertArrayHasKey('cancelled_by_refund', $payout->payload_state ?? []);
    }

    public function test_refund_cancels_initiated_when_latest_payout_by_id_is_rejected(): void
    {
        Queue::fake();

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
            'deal_id' => 'deal-r-mixed-rows',
            'payment_id' => '12349',
            'payment_status' => 'CONFIRMED',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'provider_inv_id' => 12349,
            'tbank_payment_id' => 12349,
        ]);

        $tinkoffPayment = TinkoffPayment::create([
            'order_id' => 'order-r-mixed',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => '12349',
            'deal_id' => 'deal-r-mixed-rows',
            'confirmed_at' => now(),
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $tinkoffPayment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-r-mixed-rows',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
        ]);

        TinkoffPayout::create([
            'payment_id' => (int) $tinkoffPayment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-r-mixed-rows',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'REJECTED',
            'completed_at' => now(),
        ]);

        $this->postJson(route('payments.refund', ['payment' => $payment->id]), [])
            ->assertOk()
            ->assertJson(['message' => 'refund_created']);

        $payouts = TinkoffPayout::query()
            ->where('payment_id', (int) $tinkoffPayment->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $payouts);
        $this->assertSame('REJECTED', (string) $payouts[0]->status);
        $this->assertNull($payouts[0]->when_to_run);
        $this->assertArrayHasKey('cancelled_by_refund', $payouts[0]->payload_state ?? []);
        $this->assertSame('REJECTED', (string) $payouts[1]->status);
    }
}

