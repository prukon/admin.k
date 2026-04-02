<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankPayoutDuplicatePreventionTest extends CrmTestCase
{
    private function createPaymentWithPartner(): TinkoffPayment
    {
        $this->partner->tinkoff_partner_id = 'SHOP-DUP';
        $this->partner->save();

        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                'e2c_terminal_key' => 'TERM_E2C',
                'e2c_token_password' => 'PWD_E2C',
            ],
        ]);

        return TinkoffPayment::create([
            'order_id' => 'order-dup',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-dup',
            'confirmed_at' => now(),
        ]);
    }

    public function test_createAndRunPayout_does_not_create_second_when_one_exists_and_not_rejected(): void
    {
        $payment = $this->createPaymentWithPartner();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/e2c/v2/Init')) {
                return Http::response(['Success' => true, 'PaymentId' => 8001], 200);
            }
            if (str_contains($url, '/e2c/v2/Payment')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            if (str_contains($url, '/e2c/v2/GetState')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            return Http::response([], 200);
        });

        $svc = app(TinkoffPayoutsService::class);

        $payout1 = $svc->createAndRunPayout($payment, true, null, 'manual', null);
        $this->assertNotNull($payout1->id);

        $countBefore = TinkoffPayout::where('payment_id', $payment->id)->count();

        $payout2 = $svc->createAndRunPayout($payment, true, null, 'manual', null);

        $this->assertSame($payout1->id, $payout2->id);
        $this->assertSame($countBefore, TinkoffPayout::where('payment_id', $payment->id)->count());
    }

    public function test_createAndRunPayout_creates_new_when_last_is_rejected(): void
    {
        $payment = $this->createPaymentWithPartner();

        TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $payment->deal_id,
            'amount' => 9500,
            'status' => 'REJECTED',
            'source' => 'auto',
            'completed_at' => now(),
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/e2c/v2/Init')) {
                return Http::response(['Success' => true, 'PaymentId' => 8002], 200);
            }
            if (str_contains($url, '/e2c/v2/Payment')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            if (str_contains($url, '/e2c/v2/GetState')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            return Http::response([], 200);
        });

        $svc = app(TinkoffPayoutsService::class);
        $payout = $svc->createAndRunPayout($payment, true, null, 'manual', null);

        $this->assertNotNull($payout->id);
        $this->assertSame(2, TinkoffPayout::where('payment_id', $payment->id)->count());
    }

    public function test_createAndRunPayout_auto_second_call_returns_same_row_without_duplicate(): void
    {
        $payment = $this->createPaymentWithPartner();

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/e2c/v2/Init')) {
                return Http::response(['Success' => true, 'PaymentId' => 8101], 200);
            }
            if (str_contains($url, '/e2c/v2/Payment')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            if (str_contains($url, '/e2c/v2/GetState')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            return Http::response([], 200);
        });

        $svc = app(TinkoffPayoutsService::class);

        $first = $svc->createAndRunPayout($payment, true, null, 'auto', null);
        $second = $svc->createAndRunPayout($payment, true, null, 'auto', null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TinkoffPayout::where('payment_id', $payment->id)->count());
    }

    public function test_createAndRunPayout_auto_does_not_create_new_when_last_is_rejected(): void
    {
        $payment = $this->createPaymentWithPartner();

        $rejected = TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $payment->deal_id,
            'amount' => 9500,
            'status' => 'REJECTED',
            'source' => 'auto',
            'completed_at' => now(),
        ]);

        Http::fake();

        $svc = app(TinkoffPayoutsService::class);
        $result = $svc->createAndRunPayout($payment, true, null, 'auto', null);

        $this->assertSame($rejected->id, $result->id);
        $this->assertSame(1, TinkoffPayout::where('payment_id', $payment->id)->count());
        Http::assertNothingSent();
    }
}
