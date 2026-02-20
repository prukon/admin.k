<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\SmRegisterClient;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankAutoPayoutOnWebhookTest extends CrmTestCase
{
    public function test_confirmed_webhook_schedules_payout_after_48_hours_when_auto_payout_enabled(): void
    {
        // Важно: в проде SmRegisterClient требует сертификаты. В тестах подменяем на заглушку.
        $this->app->instance(SmRegisterClient::class, new class {
            public function patch(string $partnerId, array $payload): array
            {
                return ['ok' => true];
            }
        });

        // Partner должен быть зарегистрирован в банке для e2c
        $this->partner->tinkoff_partner_id = 'SHOPCODE-1';
        $this->partner->save();

        // Настроим tbank payment + e2c ключи и включим авто-выплату
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                // для webhook подписи (Init оплаты)
                'terminal_key' => 'TERM_PAY',
                'token_password' => 'PWD_PAY',
                // для e2c выплат
                'e2c_terminal_key' => 'TERM_E2C',
                'e2c_token_password' => 'PWD_E2C',
                // флаг автоваыплаты
                'auto_payout_enabled' => true,
            ],
        ]);

        // Платёж уже создан ранее (как после Init)
        $tp = TinkoffPayment::create([
            'order_id' => 'order-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000, // 100 ₽
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-xyz',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '100.00',
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '100.00',
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-1',
        ]);

        // Автовыплата создаётся с задержкой +48 часов, поэтому e2c вызовов быть не должно
        Http::fake();

        // webhook оплаты с валидной подписью по payment token_password
        $payload = [
            'TerminalKey' => 'TERM_PAY',
            'OrderId' => 'order-1',
            'PaymentId' => 12345,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'SpAccumulationId' => 'deal-xyz',
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD_PAY');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $this->assertDatabaseHas('tinkoff_payouts', [
            'payment_id' => $tp->id,
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-xyz',
        ]);

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame('INITIATED', (string) $payout->status);
        $this->assertNotNull($payout->when_to_run);
        $this->assertNull($payout->tinkoff_payout_payment_id);
        $this->assertNull($payout->completed_at);

        // run_at ≈ confirmed_at + 48h
        $this->assertSame(
            $tp->fresh()->confirmed_at?->clone()->addHours(48)->format('Y-m-d H:i'),
            $payout->when_to_run?->format('Y-m-d H:i')
        );

        Http::assertNothingSent();
    }
}

