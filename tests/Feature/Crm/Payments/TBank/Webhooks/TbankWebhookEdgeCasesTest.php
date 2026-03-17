<?php

namespace Tests\Feature\Crm\Payments\TBank\Webhooks;

use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Payable;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffSignature;
use Tests\Feature\Crm\CrmTestCase;

class TbankWebhookEdgeCasesTest extends CrmTestCase
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
                'e2c_terminal_key' => 'E2C_TERM',
                'e2c_token_password' => 'E2C_PWD',
            ],
        ]);
    }

    public function test_webhook_missing_order_id_is_ignored_but_returns_200(): void
    {
        $this->setupTbankKeysForPartner();

        $payload = [
            'TerminalKey' => 'TERM',
            'PaymentId' => 1,
            'Status' => 'CONFIRMED',
            'Success' => true,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();
    }

    public function test_webhook_non_final_status_does_not_apply_domain_effects(): void
    {
        $this->setupTbankKeysForPartner();

        TinkoffPayment::create([
            'order_id' => 'order-nonfinal',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '10.00',
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '10.00',
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-nonfinal',
        ]);

        $payload = [
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-nonfinal',
            'PaymentId' => 10,
            'Status' => 'AUTHORIZED', // не финальный
            'Success' => true,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $intent->refresh();
        $payable->refresh();
        $this->assertSame('pending', (string) $intent->status);
        $this->assertSame('pending', (string) $payable->status);
    }
}

