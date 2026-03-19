<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Tests\Feature\Crm\CrmTestCase;

class TbankPaymentShowPayoutActionsTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    private function createPaymentWithDeal(): TinkoffPayment
    {
        return TinkoffPayment::create([
            'order_id' => 'order-payout-actions',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-payout-actions',
            'confirmed_at' => now(),
        ]);
    }

    public function test_show_displays_payout_buttons_when_no_payout_exists(): void
    {
        $payment = $this->createPaymentWithDeal();

        $resp = $this->get('/admin/tinkoff/payments/' . $payment->id);

        $resp->assertOk();
        $resp->assertSee('Выплатить сейчас');
        $resp->assertSee('Отложить до…');
        $resp->assertDontSee('Выплата уже создана или в процессе');
    }

    public function test_show_displays_payout_buttons_when_last_payout_is_rejected(): void
    {
        $payment = $this->createPaymentWithDeal();
        TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $payment->deal_id,
            'amount' => 9500,
            'status' => 'REJECTED',
            'source' => 'auto',
        ]);

        $resp = $this->get('/admin/tinkoff/payments/' . $payment->id);

        $resp->assertOk();
        $resp->assertSee('Выплатить сейчас');
        $resp->assertSee('Отложить до…');
    }

    public function test_show_hides_payout_buttons_when_payout_completed(): void
    {
        $payment = $this->createPaymentWithDeal();
        TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $payment->deal_id,
            'amount' => 9500,
            'status' => 'COMPLETED',
            'source' => 'manual',
            'completed_at' => now(),
        ]);

        $resp = $this->get('/admin/tinkoff/payments/' . $payment->id);

        $resp->assertOk();
        $resp->assertDontSee('Выплатить сейчас');
        $resp->assertSee('Выплата уже создана или в процессе');
    }

    public function test_show_hides_payout_buttons_when_payout_initiated(): void
    {
        $payment = $this->createPaymentWithDeal();
        TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $payment->deal_id,
            'amount' => 9500,
            'status' => 'INITIATED',
            'source' => 'auto',
        ]);

        $resp = $this->get('/admin/tinkoff/payments/' . $payment->id);

        $resp->assertOk();
        $resp->assertDontSee('Выплатить сейчас');
        $resp->assertSee('Выплата уже создана или в процессе');
    }
}
