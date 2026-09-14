<?php

namespace Tests\Feature\Crm\Payments\TBank\Deals;

use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankDealCloseTest extends CrmTestCase
{
    private function seedE2cKeysForPartner(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_PAY',
            'token_password' => 'PWD_PAY',
            'e2c_terminal_key' => 'TERM_E2C',
            'e2c_token_password' => 'PWD_E2C',
        ]);
    }

    private function makePayment(string $dealId, string $orderId = 'order-a'): TinkoffPayment
    {
        return TinkoffPayment::create([
            'order_id' => $orderId,
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => $dealId,
        ]);
    }

    public function test_close_deal_success_writes_payload_to_all_payments_with_same_deal(): void
    {
        $this->asSuperadmin();

        $this->seedE2cKeysForPartner();

        $this->makePayment('deal-x', 'order-a');
        $this->makePayment('deal-x', 'order-b');

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

    public function test_close_deal_failure_returns_bank_message_and_still_writes_payload(): void
    {
        $this->asSuperadmin();

        $this->seedE2cKeysForPartner();

        $payment = $this->makePayment('deal-y');
        $cardUrl = '/admin/tinkoff/payments/'.$payment->id;

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/e2c/v2/CloseSpDeal')) {
                return Http::response([
                    'Success' => false,
                    'Message' => 'Неверные параметры.',
                    'Details' => 'Сделка не найдена',
                ], 200);
            }

            return Http::response(['Success' => true], 200);
        });

        $this->from($cardUrl)
            ->post('/tinkoff/deals/deal-y/close')
            ->assertStatus(302)
            ->assertRedirect($cardUrl)
            ->assertSessionHasErrors(['tinkoff']);

        $p = TinkoffPayment::where('deal_id', 'deal-y')->firstOrFail();
        $pl = $p->payload ?? [];
        $this->assertArrayHasKey('deal_close', $pl);
        $this->assertFalse((bool) data_get($pl, 'deal_close.response.Success'));

        $this->get($cardUrl)
            ->assertOk()
            ->assertSee('Неверные параметры.', false)
            ->assertSee('Сделка не найдена', false)
            ->assertSee('data-error-for="tinkoff"', false);
    }

    public function test_close_deal_http_404_returns_error_without_500(): void
    {
        $this->asSuperadmin();

        $this->seedE2cKeysForPartner();

        $payment = $this->makePayment('deal-z');
        $cardUrl = '/admin/tinkoff/payments/'.$payment->id;

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/e2c/v2/CloseSpDeal')) {
                return Http::response('Not Found', 404);
            }

            return Http::response(['Success' => true], 200);
        });

        $this->from($cardUrl)
            ->post('/tinkoff/deals/deal-z/close')
            ->assertStatus(302)
            ->assertRedirect($cardUrl)
            ->assertSessionHasErrors([
                'tinkoff' => 'Банк вернул ошибку при закрытии сделки (HTTP 404)',
            ]);

        $p = TinkoffPayment::where('deal_id', 'deal-z')->firstOrFail();
        $this->assertSame(404, (int) data_get($p->payload, 'deal_close.response.http_status'));
        $this->assertFalse((bool) data_get($p->payload, 'deal_close.response.Success'));

        Http::assertSentCount(1);

        $this->get($cardUrl)
            ->assertOk()
            ->assertSee('Банк вернул ошибку при закрытии сделки (HTTP 404)', false)
            ->assertSee('data-error-for="tinkoff"', false);
    }

    public function test_close_deal_returns_error_when_payment_not_found(): void
    {
        $this->asSuperadmin();

        $this->post('/tinkoff/deals/deal-nope/close')
            ->assertStatus(302)
            ->assertSessionHasErrors(['tinkoff']);
    }

    public function test_close_deal_ajax_payment_not_found_returns_422_under_tinkoff(): void
    {
        $this->asSuperadmin();

        $this->postJson('/tinkoff/deals/deal-nope/close')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tinkoff']);
    }
}
