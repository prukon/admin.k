<?php

namespace Tests\Feature\Crm\Payments;

use App\Jobs\SendCloudKassirReceiptJob;
use App\Models\FiscalReceipt;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

class TinkoffFiscalReceiptIdempotencyTest extends CrmTestCase
{
    public function test_repeated_confirmed_webhooks_do_not_create_duplicate_fiscal_receipts(): void
    {
        Queue::fake();

        PaymentSystem::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'settings' => [
                'terminal_key' => 'TerminalKey',
                'token_password' => 'Password',
                'e2c_terminal_key' => 'E2C',
                'e2c_token_password' => 'E2CPass',
            ],
            'test_mode' => true,
            'is_enabled' => true,
        ]);

        $this->partner->update([
            'tax_id' => '7701234567',
            'taxation_system' => 1,
        ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '3500.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2026-03-01',
            'meta' => ['month' => '2026-03-01'],
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '3500.00',
            'payment_date' => '2026-03-01',
            'meta' => json_encode(['user_name' => $this->user->name], JSON_UNESCAPED_UNICODE),
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-123',
            'partner_id' => $this->partner->id,
            'amount' => 350000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $intent->update([
            'tbank_order_id' => 'order-123',
            'tbank_payment_id' => 555111,
            'provider_inv_id' => 555111,
        ]);

        $payload = [
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'order-123',
            'Success' => true,
            'Status' => 'CONFIRMED',
            'PaymentId' => 555111,
            'DATA' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
            ],
            'Token' => 'skip-in-test',
        ];

        $service = app(\App\Services\Tinkoff\TinkoffPaymentsService::class);

        $service->handleWebhook($payload, true);
        $service->handleWebhook($payload, true);

        $this->assertSame(
            1,
            FiscalReceipt::query()
                ->where('partner_id', $this->partner->id)
                ->where('payment_intent_id', $intent->id)
                ->where('payable_id', $payable->id)
                ->where('type', FiscalReceipt::TYPE_INCOME)
                ->count()
        );
    }
}