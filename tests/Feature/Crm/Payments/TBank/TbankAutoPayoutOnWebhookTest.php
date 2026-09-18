<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\SmRegisterClient;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;

class TbankAutoPayoutOnWebhookTest extends CrmTestCase
{
    public function test_confirmed_webhook_schedules_payout_after_48_hours_when_auto_payout_enabled(): void
    {
        $this->bindSmPatchStub();

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_PAY',
            'token_password' => 'PWD_PAY',
            'e2c_terminal_key' => 'TERM_E2C',
            'e2c_token_password' => 'PWD_E2C',
        ]);

        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOPCODE-1');

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-xyz',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'status' => 'pending',
            'meta' => ['team_id' => $chain['team']->id],
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum_cents' => 10000,
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-1',
        ]);

        Http::fake();

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

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame('INITIATED', (string) $payout->status);
        $this->assertNotNull($payout->when_to_run);
        $this->assertSame(
            $tp->fresh()->confirmed_at?->clone()->addHours(48)->format('Y-m-d H:i'),
            $payout->when_to_run?->format('Y-m-d H:i')
        );

        Http::assertNothingSent();
    }

    public function test_confirmed_webhook_uses_delay_from_commission_rule(): void
    {
        $this->bindSmPatchStub();

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_PAY2',
            'token_password' => 'PWD_PAY2',
        ]);

        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOPCODE-2');

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 24,
        ]);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-2',
            'partner_id' => $this->partner->id,
            'amount' => 5000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-2',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount_cents' => 5000,
            'currency' => 'RUB',
            'status' => 'pending',
            'meta' => ['team_id' => $chain['team']->id],
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum_cents' => 5000,
            'payment_date' => 'Взнос',
            'tbank_order_id' => 'order-2',
        ]);

        Http::fake();

        $payload = [
            'TerminalKey' => 'TERM_PAY2',
            'OrderId' => 'order-2',
            'PaymentId' => 12346,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'SpAccumulationId' => 'deal-2',
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD_PAY2');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame(
            $tp->fresh()->confirmed_at?->clone()->addHours(24)->format('Y-m-d H:i'),
            $payout->when_to_run?->format('Y-m-d H:i')
        );
    }

    public function test_confirmed_webhook_zero_delay_creates_payout_with_null_when_to_run(): void
    {
        $this->bindSmPatchStub();

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_PAY3',
            'token_password' => 'PWD_PAY3',
        ]);

        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOPCODE-3');

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 0,
        ]);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-3',
            'partner_id' => $this->partner->id,
            'amount' => 5000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-3',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount_cents' => 5000,
            'currency' => 'RUB',
            'status' => 'pending',
            'meta' => ['team_id' => $chain['team']->id],
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum_cents' => 5000,
            'payment_date' => 'Взнос',
            'tbank_order_id' => 'order-3',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/e2c/v2/Init')) {
                return Http::response(['Success' => true, 'PaymentId' => 9002], 200);
            }
            if (str_contains($url, '/e2c/v2/Payment')) {
                return Http::response(['Success' => true, 'Status' => 'CREDIT_CHECKING'], 200);
            }
            if (str_contains($url, '/e2c/v2/GetState')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }

            return Http::response([], 200);
        });

        $payload = [
            'TerminalKey' => 'TERM_PAY3',
            'OrderId' => 'order-3',
            'PaymentId' => 12347,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'SpAccumulationId' => 'deal-3',
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD_PAY3');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
        $this->assertNull($payout->when_to_run);
    }

    public function test_confirmed_webhook_patches_full_bank_account_before_payout(): void
    {
        $sm = Mockery::mock(SmRegisterClient::class);
        $sm->shouldReceive('patch')
            ->once()
            ->with('SHOPCODE-PATCH', Mockery::on(function (array $payload): bool {
                $this->assertSame(['bankAccount'], array_keys($payload));
                $this->assertSame('40802810420000841544', $payload['bankAccount']['account']);
                $this->assertSame('ООО Банк Точка', $payload['bankAccount']['bankName']);
                $this->assertSame('044525104', $payload['bankAccount']['bik']);
                $this->assertNotSame('', (string) ($payload['bankAccount']['details'] ?? ''));
                $this->assertSame('30101810400000000225', $payload['bankAccount']['korAccount']);
                $this->assertArrayNotHasKey('disableReimbursement', $payload['bankAccount']);

                return true;
            }))
            ->andReturn(['ok' => true]);
        $this->app->instance(SmRegisterClient::class, $sm);

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_PAY_PATCH',
            'token_password' => 'PWD_PAY_PATCH',
        ]);

        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOPCODE-PATCH', entityOverrides: [
            'bank_name' => 'ООО Банк Точка',
            'bank_bik' => '044525104',
            'bank_account' => '40802810420000841544',
            'bank_corr_account' => '30101810400000000225',
            'sm_details_template' => 'Выплата по договору',
        ]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-patch-full',
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $chain['entity']->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-patch-full',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'status' => 'pending',
            'meta' => ['team_id' => $chain['team']->id],
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum_cents' => 10000,
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-patch-full',
        ]);

        Http::fake();

        $payload = [
            'TerminalKey' => 'TERM_PAY_PATCH',
            'OrderId' => 'order-patch-full',
            'PaymentId' => 12348,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'SpAccumulationId' => 'deal-patch-full',
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD_PAY_PATCH');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $this->assertNotNull(TinkoffPayout::where('payment_id', $tp->id)->first());
    }

    public function test_confirmed_webhook_still_creates_payout_when_bank_requisites_are_incomplete(): void
    {
        $sm = Mockery::mock(SmRegisterClient::class);
        $sm->shouldNotReceive('patch');
        $this->app->instance(SmRegisterClient::class, $sm);

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_PAY_NOBANK',
            'token_password' => 'PWD_PAY_NOBANK',
        ]);

        $chain = $this->seedTbankTeamChainForStudent(shopCode: 'SHOPCODE-NOBANK', entityOverrides: [
            'bank_name' => null,
            'bank_bik' => null,
            'bank_account' => null,
            'bank_corr_account' => null,
        ]);

        $this->seedTbankCommissionRule((int) $this->partner->id, [
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-patch-nobank',
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $chain['entity']->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'FORM',
            'deal_id' => 'deal-patch-nobank',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'status' => 'pending',
            'meta' => ['team_id' => $chain['team']->id],
        ]);

        PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum_cents' => 10000,
            'payment_date' => 'Клубный взнос',
            'tbank_order_id' => 'order-patch-nobank',
        ]);

        Http::fake();

        $payload = [
            'TerminalKey' => 'TERM_PAY_NOBANK',
            'OrderId' => 'order-patch-nobank',
            'PaymentId' => 12349,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'SpAccumulationId' => 'deal-patch-nobank',
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD_PAY_NOBANK');

        $this->post('/webhooks/tinkoff/payments', $payload)->assertOk();

        $this->assertNotNull(TinkoffPayout::where('payment_id', $tp->id)->first());
    }

    private function bindSmPatchStub(): void
    {
        $sm = Mockery::mock(SmRegisterClient::class);
        $sm->shouldReceive('patch')->zeroOrMoreTimes()->andReturn(['ok' => true]);
        $this->app->instance(SmRegisterClient::class, $sm);
    }
}
