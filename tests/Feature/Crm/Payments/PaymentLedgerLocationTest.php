<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\Location;
use App\Models\Payment;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Models\User;
use App\Services\Payments\PaymentLedgerRecorder;
use App\Services\Tinkoff\TinkoffPaymentsService;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

final class PaymentLedgerLocationTest extends CrmTestCase
{
    public function test_payment_ledger_recorder_sets_location_only_on_first_create_when_passed(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $recorder = app(PaymentLedgerRecorder::class);

        $payment = $recorder->record('999001', $this->partner->id, $student->id, [
            'user_id' => $student->id,
            'user_name' => 'Test User',
            'team_title' => 'Группа',
            'operation_date' => now()->format('Y-m-d H:i:s'),
            'payment_month' => '2026-05-01',
            'summ' => '1000',
            'location_id' => $location->id,
        ]);

        $this->assertSame($location->id, (int) $payment->location_id);

        $paymentAgain = $recorder->record('999001', $this->partner->id, $student->id, [
            'user_id' => $student->id,
            'user_name' => 'Test User Updated',
            'team_title' => 'Группа',
            'operation_date' => now()->format('Y-m-d H:i:s'),
            'payment_month' => '2026-05-01',
            'summ' => '1000',
        ]);

        $this->assertSame($location->id, (int) $paymentAgain->location_id);
        $this->assertSame('Test User Updated', $paymentAgain->fresh()->user_name);
    }

    public function test_payment_ledger_recorder_leaves_location_null_without_explicit_attribute(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $payment = app(PaymentLedgerRecorder::class)->record('999002', $this->partner->id, $student->id, [
            'user_id' => $student->id,
            'user_name' => 'Test User',
            'team_title' => 'Группа',
            'operation_date' => now()->format('Y-m-d H:i:s'),
            'payment_month' => '2026-05-01',
            'summ' => '500',
        ]);

        $this->assertNull($payment->location_id);
    }

    public function test_tbank_webhook_does_not_set_payment_location_from_user(): void
    {
        Queue::fake();

        $this->seedGlobalTbank([
            'terminal_key' => 'TerminalKey',
            'token_password' => 'Password',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2CPass',
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
            'order_id' => 'order-loc-1',
            'partner_id' => $this->partner->id,
            'amount' => 350000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $intent->update([
            'tbank_order_id' => 'order-loc-1',
            'tbank_payment_id' => 777222,
            'provider_inv_id' => 777222,
        ]);

        app(TinkoffPaymentsService::class)->handleWebhook([
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'order-loc-1',
            'Success' => true,
            'Status' => 'CONFIRMED',
            'PaymentId' => 777222,
            'DATA' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
            ],
            'Token' => 'skip-in-test',
        ], true);

        $payment = Payment::query()->where('payment_number', '777222')->first();
        $this->assertNotNull($payment);
        $this->assertNull($payment->location_id);
    }
}
