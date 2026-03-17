<?php

namespace Tests\Feature\Crm\Payments\TBank\Logs;

use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankAdminPaymentCardHistoryTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asSuperadmin();
    }

    public function test_admin_tbank_payment_card_renders_history_from_logs(): void
    {
        $payment = TinkoffPayment::create([
            'order_id' => 'order-1',
            'partner_id' => (int) $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => '123',
            'deal_id' => 'deal-1',
            'payment_url' => null,
            'payload' => ['Init' => true],
            'confirmed_at' => now(),
        ]);

        DB::table('tinkoff_payment_status_logs')->insert([
            'tinkoff_payment_id' => (int) $payment->id,
            'partner_id' => (int) $this->partner->id,
            'event_source' => 'webhook',
            'from_status' => 'NEW',
            'to_status' => 'CONFIRMED',
            'bank_status' => 'CONFIRMED',
            'bank_payment_id' => '123',
            'order_id' => 'order-1',
            'payload' => json_encode(['Status' => 'CONFIRMED'], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $resp = $this->get('/admin/tinkoff/payments/' . $payment->id);

        $resp->assertOk();
        $resp->assertSee('История статусов');
        $resp->assertSee('CONFIRMED');
        $resp->assertSee('PaymentId');
        $resp->assertSee('OrderId');
    }
}

