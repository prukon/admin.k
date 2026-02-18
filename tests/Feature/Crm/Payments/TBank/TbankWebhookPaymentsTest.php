<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankWebhookPaymentsTest extends CrmTestCase
{
    private function setupTbankKeysForPartner(string $terminalKey = 'TERM', string $password = 'PWD'): void
    {
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                'terminal_key' => $terminalKey,
                'token_password' => $password,
                // чтобы $ps->is_connected === true и service брал ключи из БД
                'e2c_terminal_key' => 'E2C_TERM',
                'e2c_token_password' => 'E2C_PWD',
            ],
        ]);
    }

    private function makeWebhookPayload(array $overrides = [], string $secret = 'PWD'): array
    {
        $payload = array_merge([
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-1',
            'PaymentId' => 12345,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'SpAccumulationId' => 'deal-abc',
        ], $overrides);

        $payload['Token'] = TinkoffSignature::makeToken($payload, $secret);

        return $payload;
    }

    public function test_webhook_invalid_signature_returns_200_and_does_not_change_payment(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        $tp = TinkoffPayment::create([
            'order_id' => 'order-1',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payload = $this->makeWebhookPayload([], 'PWD');
        $payload['Token'] = 'bad-token';

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $tp->refresh();
        $this->assertSame('FORM', (string) $tp->status);
        $this->assertNull($tp->deal_id);
        $this->assertNull($tp->confirmed_at);
    }

    public function test_webhook_confirmed_updates_tinkoff_payment_and_applies_domain_effects(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        $tp = TinkoffPayment::create([
            'order_id' => 'order-1',
            'partner_id' => $this->partner->id,
            'amount' => 1500,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '15.00',
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '15.00',
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-1',
        ]);

        $payload = $this->makeWebhookPayload([
            'OrderId' => 'order-1',
            'PaymentId' => 12345,
            'Status' => 'CONFIRMED',
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $tp->refresh();
        $this->assertSame('CONFIRMED', (string) $tp->status);
        $this->assertSame('deal-abc', (string) $tp->deal_id);
        $this->assertNotNull($tp->confirmed_at);

        $intent->refresh();
        $payable->refresh();
        $this->assertSame('paid', (string) $intent->status);
        $this->assertSame('paid', (string) $payable->status);

        $this->assertDatabaseHas('payments', [
            'partner_id' => $this->partner->id,
            'payment_number' => '12345',
        ]);
    }

    public function test_webhook_is_idempotent_second_call_does_not_duplicate_domain_records(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        TinkoffPayment::create([
            'order_id' => 'order-1',
            'partner_id' => $this->partner->id,
            'amount' => 1500,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '15.00',
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '15.00',
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-1',
        ]);

        $payload = $this->makeWebhookPayload([
            'OrderId' => 'order-1',
            'PaymentId' => 12345,
            'Status' => 'CONFIRMED',
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();
        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $intent->refresh();
        $this->assertSame('paid', (string) $intent->status);

        $this->assertSame(
            1,
            Payment::query()
                ->where('partner_id', $this->partner->id)
                ->where('payment_number', '12345')
                ->count()
        );

        // По повторному webhook intent уже paid → эффект не применяется повторно
        $this->assertSame(
            1,
            DB::table('my_logs')
                ->where('partner_id', $this->partner->id)
                ->where('type', 5)
                ->where('action', 50)
                ->count()
        );
    }

    public function test_webhook_failed_marks_intent_failed_and_does_not_create_payment_record(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        TinkoffPayment::create([
            'order_id' => 'order-1',
            'partner_id' => $this->partner->id,
            'amount' => 1500,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '15.00',
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '15.00',
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-1',
        ]);

        $payload = $this->makeWebhookPayload([
            'Status' => 'REJECTED',
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $intent->refresh();
        $this->assertSame('failed', (string) $intent->status);

        $this->assertDatabaseMissing('payments', [
            'partner_id' => $this->partner->id,
            'payment_number' => '12345',
        ]);
    }
}

