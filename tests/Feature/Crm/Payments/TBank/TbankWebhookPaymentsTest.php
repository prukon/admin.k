<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Models\UserPrice;
use App\Models\UserPeriodPrice;
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
        $this->assertSame('card', (string) $intent->payment_method_webhook);

        $this->assertDatabaseHas('payments', [
            'partner_id' => $this->partner->id,
            'payment_number' => '12345',
        ]);

        $this->assertSame('card', (string) $tp->fresh()->method);
    }

    public function test_webhook_confirmed_data_source_tpay_sets_intent_webhook_and_tinkoff_method(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        $tp = TinkoffPayment::create([
            'order_id' => 'order-tpay-1',
            'partner_id' => $this->partner->id,
            'amount' => 2000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '20.00',
            'currency' => 'RUB',
            'status' => 'pending',
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'payment_method' => 'card',
            'status' => 'pending',
            'out_sum' => '20.00',
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-tpay-1',
        ]);

        $payload = $this->makeWebhookPayload([
            'OrderId' => 'order-tpay-1',
            'PaymentId' => 888001,
            'Status' => 'CONFIRMED',
            'Data' => json_encode(['source' => 'TinkoffPay'], JSON_UNESCAPED_UNICODE),
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $tp->refresh();
        $this->assertSame('tpay', (string) $tp->method);

        $intent->refresh();
        $this->assertSame('tpay', (string) $intent->payment_method_webhook);
        $this->assertSame('card', (string) $intent->payment_method);
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

        // Историю webhook'ов пишем всегда (даже повторные)
        $this->assertSame(
            2,
            DB::table('tinkoff_payment_status_logs')
                ->where('partner_id', $this->partner->id)
                ->where('order_id', 'order-1')
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

    public function test_webhook_confirmed_monthly_fee_marks_user_price_paid_and_writes_payment_month_even_if_tbank_fields_not_saved_yet(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        TinkoffPayment::create([
            'order_id' => 'order-m1',
            'partner_id' => $this->partner->id,
            'amount' => 1500,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '15.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2024-09-01',
        ]);

        // ВАЖНО: намеренно НЕ сохраняем tbank_order_id/tbank_payment_id в intent — имитируем гонку.
        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '15.00',
            'payment_date' => '2024-09-01',
        ]);

        $payload = $this->makeWebhookPayload([
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-m1',
            'PaymentId' => 55555,
            'Status' => 'CONFIRMED',
            // Банк возвращает DATA в webhook в параметре Data
            'Data' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
                'month' => '2024-09-01',
            ],
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $intent->refresh();
        $payable->refresh();
        $this->assertSame('paid', (string) $intent->status);
        $this->assertSame('paid', (string) $payable->status);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->user->id,
            'new_month' => '2024-09-01',
            'is_paid' => 1,
        ]);

        $this->assertDatabaseHas('payments', [
            'partner_id' => $this->partner->id,
            'payment_number' => '55555',
            'payment_month' => '2024-09-01',
        ]);
    }

    public function test_webhook_confirmed_abonement_fee_period_marks_user_period_price_paid(): void
    {
        $this->setupTbankKeysForPartner('TERM', 'PWD');

        TinkoffPayment::create([
            'order_id' => 'order-a1',
            'partner_id' => $this->partner->id,
            'amount' => 32100,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $upp = UserPeriodPrice::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'date_start' => '2026-11-01',
            'date_end' => '2026-11-30',
            'amount' => '321.00',
            'is_paid' => 0,
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'abonement_fee_period',
            'amount' => '321.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'meta' => [
                'user_period_price_id' => $upp->id,
            ],
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '321.00',
            'payment_date' => 'Абонемент',
            'tbank_order_id' => 'order-a1',
        ]);

        $payload = $this->makeWebhookPayload([
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-a1',
            'PaymentId' => 66666,
            'Status' => 'CONFIRMED',
            'Data' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
                'user_period_price_id' => (string) $upp->id,
                'payment_kind' => 'abonement',
            ],
        ], 'PWD');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $intent->refresh();
        $payable->refresh();
        $upp->refresh();

        $this->assertSame('paid', (string) $intent->status);
        $this->assertSame('paid', (string) $payable->status);
        $this->assertTrue((bool) $upp->is_paid);
    }
}

