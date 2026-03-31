<?php

namespace Tests\Feature\Crm\Payments\TBank\Logs;

use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankWebhookLogsTest extends CrmTestCase
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

    private function signedWebhook(array $payload, string $secret): array
    {
        $payload['Token'] = TinkoffSignature::makeToken($payload, $secret);
        return $payload;
    }

    public function test_valid_webhook_creates_status_log_row_with_payload(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        TinkoffPayment::create([
            'order_id' => 'order-log-1',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payload = $this->signedWebhook([
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-log-1',
            'PaymentId' => 777,
            'Status' => 'AUTHORIZED', // не финальный — всё равно должны логировать
            'Success' => true,
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $row = DB::table('tinkoff_payment_status_logs')
            ->where('order_id', 'order-log-1')
            ->latest('id')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($this->partner->id, (int) $row->partner_id);
        $this->assertSame('webhook', (string) $row->event_source);
        $this->assertSame('FORM', (string) $row->from_status);
        $this->assertSame('FORM', (string) $row->to_status);
        $this->assertSame('AUTHORIZED', (string) $row->bank_status);
        $this->assertSame('777', (string) $row->bank_payment_id);

        $decoded = json_decode((string) $row->payload, true);
        $this->assertIsArray($decoded);
        $this->assertSame('order-log-1', $decoded['OrderId'] ?? null);
        $this->assertSame(777, (int) ($decoded['PaymentId'] ?? 0));
        $this->assertSame('AUTHORIZED', $decoded['Status'] ?? null);
    }

    public function test_repeated_webhooks_are_logged_twice(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        TinkoffPayment::create([
            'order_id' => 'order-log-2',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payload = $this->signedWebhook([
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-log-2',
            'PaymentId' => 888,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'SpAccumulationId' => 'deal-x',
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();
        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $this->assertSame(
            2,
            DB::table('tinkoff_payment_status_logs')->where('order_id', 'order-log-2')->count()
        );
    }

    public function test_invalid_signature_does_not_create_status_log_row(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        TinkoffPayment::create([
            'order_id' => 'order-log-3',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payload = [
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-log-3',
            'PaymentId' => 999,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'Token' => 'bad-token',
        ];

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $this->assertSame(
            0,
            DB::table('tinkoff_payment_status_logs')->where('order_id', 'order-log-3')->count()
        );
    }
}

