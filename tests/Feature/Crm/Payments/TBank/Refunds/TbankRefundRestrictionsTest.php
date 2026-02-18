<?php

namespace Tests\Feature\Crm\Payments\TBank\Refunds;

use App\Jobs\TinkoffProcessRefundJob;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Payable;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

class TbankRefundRestrictionsTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin(); // refund route находится под reports-view
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

        TinkoffPayout::create([
            'payment_id' => 1,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-r1',
            'amount' => 9000,
            'is_final' => 1,
            'status' => 'COMPLETED',
        ]);

        $this->postJson(route('payments.refund', ['payment' => $payment->id]), [])
            ->assertStatus(422)
            ->assertJson([
                'error' => true,
                'message' => 'Возврат запрещён: по этому платежу уже есть выплата партнёру.',
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

        TinkoffPayout::create([
            'payment_id' => 1,
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
}

