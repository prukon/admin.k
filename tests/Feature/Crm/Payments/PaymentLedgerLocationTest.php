<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\Location;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\User;
use App\Services\Payments\PaymentLedgerRecorder;
use App\Services\Payments\PaymentLedgerTeamResolver;
use App\Services\TeamUserSyncService;
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
            'summ_cents' => 100000,
            'location_id' => $location->id,
        ]);

        $this->assertSame($location->id, (int) $payment->location_id);

        $paymentAgain = $recorder->record('999001', $this->partner->id, $student->id, [
            'user_id' => $student->id,
            'user_name' => 'Test User Updated',
            'team_title' => 'Группа',
            'operation_date' => now()->format('Y-m-d H:i:s'),
            'payment_month' => '2026-05-01',
            'summ_cents' => 100000,
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
            'summ_cents' => 50000,
        ]);

        $this->assertNull($payment->location_id);
    }

    public function test_payment_ledger_team_resolver_snapshots_location_from_paid_team(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Робототехника',
            'location_id' => $location->id,
        ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2026-08-01',
            'meta' => ['team_id' => (int) $team->id],
        ]);

        $snapshot = app(PaymentLedgerTeamResolver::class)->resolveFromPayable($payable, $this->user);

        $this->assertSame((int) $team->id, $snapshot['team_id']);
        $this->assertSame('Робототехника', $snapshot['team_title']);
        $this->assertSame((int) $location->id, $snapshot['location_id']);
    }

    public function test_payment_ledger_team_resolver_leaves_location_null_when_team_has_no_object(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Без объекта',
            'location_id' => null,
        ]);

        $payable = Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2026-08-01',
            'meta' => ['team_id' => (int) $team->id],
        ]);

        $snapshot = app(PaymentLedgerTeamResolver::class)->resolveFromPayable($payable, $this->user);

        $this->assertSame((int) $team->id, $snapshot['team_id']);
        $this->assertNull($snapshot['location_id']);
    }

    public function test_tbank_webhook_snapshots_location_from_paid_team(): void
    {
        Queue::fake();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $team = $this->attachTeamWithLocation($location->id);

        $payment = $this->confirmTbankMonthlyPayment($team, 777333);

        $this->assertSame((int) $team->id, (int) $payment->team_id);
        $this->assertSame((int) $location->id, (int) $payment->location_id);
    }

    public function test_tbank_webhook_leaves_location_null_when_paid_team_has_no_object(): void
    {
        Queue::fake();

        $team = $this->attachTeamWithLocation(null);

        $payment = $this->confirmTbankMonthlyPayment($team, 777222);

        $this->assertSame((int) $team->id, (int) $payment->team_id);
        $this->assertNull($payment->location_id);
    }

    public function test_robokassa_result_snapshots_location_from_paid_team(): void
    {
        Queue::fake();

        PaymentSystem::factory()
            ->robokassa()
            ->create(['partner_id' => $this->partner->id]);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $team = $this->attachTeamWithLocation($location->id);

        $outSum = '3900.00';
        $month = '2026-08-01';

        Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount_cents' => 390000,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $month,
            'meta' => ['month' => $month, 'team_id' => (int) $team->id],
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => null,
            'provider' => 'robokassa',
            'status' => 'pending',
            'out_sum_cents' => 390000,
            'payment_date' => $month,
            'meta' => json_encode([
                'user_name' => (string) $this->user->name,
                'team_id' => (int) $team->id,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $providerInvId = 1000000000 + (int) $intent->id;
        $intent->provider_inv_id = $providerInvId;
        $intent->save();

        $password2 = 'pass2';
        $signature = strtoupper(md5("{$outSum}:{$providerInvId}:{$password2}:Shp_paymentDate={$month}:Shp_userId={$this->user->id}"));

        $this->get(route('payment.result', [
            'OutSum' => $outSum,
            'InvId' => $providerInvId,
            'SignatureValue' => $signature,
            'Shp_paymentDate' => $month,
            'Shp_userId' => (string) $this->user->id,
        ]))->assertOk();

        $payment = Payment::query()->where('payment_number', (string) $providerInvId)->first();
        $this->assertNotNull($payment);
        $this->assertSame((int) $team->id, (int) $payment->team_id);
        $this->assertSame((int) $location->id, (int) $payment->location_id);
    }

    private function attachTeamWithLocation(?int $locationId): Team
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Робототехника, Главная 10',
            'location_id' => $locationId,
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        return $team;
    }

    private function confirmTbankMonthlyPayment(Team $team, int $paymentId): Payment
    {
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
            'amount_cents' => 350000,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2026-03-01',
            'meta' => [
                'month' => '2026-03-01',
                'team_id' => (int) $team->id,
            ],
        ]);

        $intent = PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum_cents' => 350000,
            'payment_date' => '2026-03-01',
            'meta' => json_encode(['user_name' => $this->user->name], JSON_UNESCAPED_UNICODE),
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-loc-'.$paymentId,
            'partner_id' => $this->partner->id,
            'amount' => 350000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $intent->update([
            'tbank_order_id' => 'order-loc-'.$paymentId,
            'tbank_payment_id' => $paymentId,
            'provider_inv_id' => $paymentId,
        ]);

        app(TinkoffPaymentsService::class)->handleWebhook([
            'TerminalKey' => 'TerminalKey',
            'OrderId' => 'order-loc-'.$paymentId,
            'Success' => true,
            'Status' => 'CONFIRMED',
            'PaymentId' => $paymentId,
            'DATA' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
            ],
            'Token' => 'skip-in-test',
        ], true);

        $payment = Payment::query()->where('payment_number', (string) $paymentId)->first();
        $this->assertNotNull($payment);

        return $payment;
    }
}
