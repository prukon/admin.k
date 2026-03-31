<?php

namespace Tests\Feature\Crm\Payments\TBank\Deals;

use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankDealCloseTest extends CrmTestCase
{
    private function seedE2cKeysForPartner(): void
    {
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                // чтобы $ps->is_connected === true
                'terminal_key' => 'TERM_PAY',
                'token_password' => 'PWD_PAY',
                'e2c_terminal_key' => 'TERM_E2C',
                'e2c_token_password' => 'PWD_E2C',
            ],
        ]);
    }

    public function test_close_deal_success_writes_payload_to_all_payments_with_same_deal(): void
    {
        $this->asSuperadmin();

        $this->seedE2cKeysForPartner();

        TinkoffPayment::create([
            'order_id' => 'order-a',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-x',
        ]);
        TinkoffPayment::create([
            'order_id' => 'order-b',
            'partner_id' => $this->partner->id,
            'amount' => 2000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-x',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/e2c/v2/CloseSpDeal')) {
                return Http::response(['Success' => true], 200);
            }
            return Http::response(['Success' => true], 200);
        });

        $this->post('/tinkoff/deals/deal-x/close')
            ->assertStatus(302)
            ->assertSessionHas('status', 'Сделка закрыта (в банк отправлено)');

        $payments = TinkoffPayment::where('deal_id', 'deal-x')->get();
        $this->assertCount(2, $payments);
        foreach ($payments as $p) {
            $pl = $p->payload ?? [];
            $this->assertArrayHasKey('deal_close', $pl);
            $this->assertTrue((bool) data_get($pl, 'deal_close.response.Success'));
        }
    }

    public function test_close_deal_failure_returns_error_and_still_writes_payload(): void
    {
        $this->asSuperadmin();

        $this->seedE2cKeysForPartner();

        TinkoffPayment::create([
            'order_id' => 'order-a',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-y',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/e2c/v2/CloseSpDeal')) {
                return Http::response(['Success' => false, 'Message' => 'nope'], 200);
            }
            return Http::response(['Success' => true], 200);
        });

        $this->post('/tinkoff/deals/deal-y/close')
            ->assertStatus(302)
            ->assertSessionHasErrors(['tinkoff']);

        $p = TinkoffPayment::where('deal_id', 'deal-y')->firstOrFail();
        $pl = $p->payload ?? [];
        $this->assertArrayHasKey('deal_close', $pl);
        $this->assertFalse((bool) data_get($pl, 'deal_close.response.Success'));
    }

    public function test_close_deal_returns_error_when_payment_not_found(): void
    {
        $this->asSuperadmin();

        $this->post('/tinkoff/deals/deal-nope/close')
            ->assertStatus(302)
            ->assertSessionHasErrors(['tinkoff']);
    }
}

