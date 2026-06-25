<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\TinkoffPayment;
use Tests\Feature\Crm\CrmTestCase;

class TbankPaymentShowRefundWindowTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    public function test_show_displays_refund_window_when_auto_payout_enabled_with_delay(): void
    {
        $confirmedAt = now()->subHours(2);
        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]);

        $payment = TinkoffPayment::create([
            'order_id' => 'order-refund-window-on',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-refund-window-on',
            'confirmed_at' => $confirmedAt,
        ]);

        $expectedUntil = $confirmedAt->clone()->addHours(48)->format('d.m.Y H:i');

        $this->get('/admin/tinkoff/payments/' . $payment->id)
            ->assertOk()
            ->assertSee('Окно возврата до: ' . $expectedUntil, false);
    }

    public function test_show_hides_refund_window_when_auto_payout_disabled_even_with_delay(): void
    {
        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 48,
        ]);

        $payment = TinkoffPayment::create([
            'order_id' => 'order-refund-window-off',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-refund-window-off',
            'confirmed_at' => now(),
        ]);

        $this->get('/admin/tinkoff/payments/' . $payment->id)
            ->assertOk()
            ->assertDontSee('Окно возврата до:', false);
    }

    public function test_show_hides_refund_window_when_auto_payout_enabled_but_delay_zero(): void
    {
        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 0,
        ]);

        $payment = TinkoffPayment::create([
            'order_id' => 'order-refund-window-zero',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-refund-window-zero',
            'confirmed_at' => now(),
        ]);

        $this->get('/admin/tinkoff/payments/' . $payment->id)
            ->assertOk()
            ->assertDontSee('Окно возврата до:', false);
    }
}
