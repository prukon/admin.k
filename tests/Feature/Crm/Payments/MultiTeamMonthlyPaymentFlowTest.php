<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\UserPrice;
use App\Services\Payments\PaymentLedgerRecorder;
use App\Services\Payments\UserPriceMonthlyFeePaymentResolver;
use App\Services\TeamUserSyncService;
use App\Services\Tinkoff\TinkoffPaymentsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Месячная оплата при нескольких группах: resolver, витрина, Init, webhook, журнал payments.
 */
final class MultiTeamMonthlyPaymentFlowTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * @return array{0: Team, 1: Team}
     */
    private function attachTwoTeamsWithPrices(string $month = '2026-08-01'): array
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Альфа',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Бета',
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $teamA->id);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $teamB->id);

        UserPrice::factory()->forUserAndMonth((int) $this->user->id, $month, 3000, false, (int) $teamA->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->user->id, $month, 4500, false, (int) $teamB->id)->create();

        return [$teamA, $teamB];
    }

    public function test_resolver_requires_team_id_when_multiple_prices_for_same_month(): void
    {
        $this->attachTwoTeamsWithPrices('2026-08-01');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Укажите группу для оплаты за этот месяц.');

        app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
            (int) $this->user->id,
            (int) $this->partner->id,
            '2026-08-01',
            null,
        );
    }

    public function test_resolver_returns_price_for_requested_team(): void
    {
        [$teamA, $teamB] = $this->attachTwoTeamsWithPrices('2026-09-01');

        $resolvedA = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
            (int) $this->user->id,
            (int) $this->partner->id,
            '2026-09-01',
            (int) $teamA->id,
        );

        $this->assertSame('3000.00', $resolvedA['out_sum']);
        $this->assertSame((int) $teamA->id, $resolvedA['team_id']);

        $resolvedB = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
            (int) $this->user->id,
            (int) $this->partner->id,
            '2026-09-01',
            (int) $teamB->id,
        );

        $this->assertSame('4500.00', $resolvedB['out_sum']);
        $this->assertSame((int) $teamB->id, $resolvedB['team_id']);
    }

    public function test_payment_index_shows_team_title_for_monthly_fee(): void
    {
        [$teamA] = $this->attachTwoTeamsWithPrices('2026-10-01');

        $response = $this->post(route('payment'), [
            'paymentDate' => 'Октябрь 2026',
            'formatedPaymentDate' => '2026-10-01',
            'team_id' => $teamA->id,
        ]);

        $response->assertOk();
        $response->assertViewHas('monthlyTeamId', (int) $teamA->id);
        $response->assertViewHas('monthlyTeamTitle', 'Группа Альфа');
        $response->assertSee('Группа');
        $response->assertSee('Группа Альфа');
    }

    public function test_tinkoff_init_includes_team_id_in_data_for_monthly_fee(): void
    {
        $this->grantTbankCardPermission();

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_MULTI',
            'token_password' => 'PWD_MULTI',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2CP',
        ]);

        [$teamA, $teamB] = $this->attachTwoTeamsWithPrices('2026-11-01');

        $entity = $this->seedRegisteredLegalEntityForPartner(shopCode: 'SHOP-MULTI');
        $this->bindTeamsToLegalEntity($entity, $teamA, $teamB);

        $capturedData = null;
        Http::fake(function ($request) use (&$capturedData) {
            if (str_contains($request->url(), '/v2/Init')) {
                $capturedData = $request->data()['DATA'] ?? null;

                return Http::response([
                    'Success' => true,
                    'PaymentId' => 990011,
                    'PaymentURL' => 'https://example.test/pay-multi',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $this->post(route('payment.tinkoff.pay'), [
            'formatedPaymentDate' => '2026-11-01',
            'team_id' => $teamA->id,
        ])->assertRedirect();

        $this->assertIsArray($capturedData);
        $this->assertSame((string) $teamA->id, (string) ($capturedData['team_id'] ?? ''));

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame((int) $teamA->id, (int) ($payable->meta['team_id'] ?? 0));

        $intent = PaymentIntent::query()->latest('id')->first();
        $this->assertNotNull($intent);
        $intentMeta = json_decode((string) $intent->meta, true);
        $this->assertSame((int) $teamA->id, (int) ($intentMeta['team_id'] ?? 0));
    }

    public function test_tbank_webhook_records_payments_team_id_for_monthly_fee(): void
    {
        Queue::fake();

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_WH',
            'token_password' => 'PWD_WH',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2CP',
        ]);

        [$teamA, $teamB] = $this->attachTwoTeamsWithPrices('2026-12-01');

        TinkoffPayment::create([
            'order_id' => 'order-multi-team',
            'partner_id' => $this->partner->id,
            'amount' => 450000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '4500.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2026-12-01',
            'meta' => [
                'month' => '2026-12-01',
                'team_id' => (int) $teamB->id,
            ],
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '4500.00',
            'payment_date' => '2026-12-01',
            'tbank_order_id' => 'order-multi-team',
            'tbank_payment_id' => 880012,
            'provider_inv_id' => 880012,
        ]);

        app(TinkoffPaymentsService::class)->handleWebhook([
            'TerminalKey' => 'TERM_WH',
            'OrderId' => 'order-multi-team',
            'Success' => true,
            'Status' => 'CONFIRMED',
            'PaymentId' => 880012,
            'Data' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
                'month' => '2026-12-01',
                'team_id' => (string) $teamB->id,
            ],
            'Token' => 'skip',
        ], true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->user->id,
            'team_id' => $teamB->id,
            'new_month' => '2026-12-01',
            'is_paid' => 1,
        ]);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->user->id,
            'team_id' => $teamA->id,
            'new_month' => '2026-12-01',
            'is_paid' => 0,
        ]);

        $payment = Payment::query()->where('payment_number', '880012')->first();
        $this->assertNotNull($payment);
        $this->assertSame((int) $teamB->id, (int) $payment->team_id);
        $this->assertSame('Группа Бета', (string) $payment->team_title);
    }

    public function test_payment_ledger_recorder_sets_team_id_only_on_first_create(): void
    {
        [$teamA, $teamB] = $this->attachTwoTeamsWithPrices('2027-01-01');

        $recorder = app(PaymentLedgerRecorder::class);

        $payment = $recorder->record('ledger-team-1', $this->partner->id, (int) $this->user->id, [
            'user_id' => (int) $this->user->id,
            'user_name' => 'Test',
            'team_id' => (int) $teamA->id,
            'team_title' => 'Группа Альфа',
            'operation_date' => now()->format('Y-m-d H:i:s'),
            'payment_month' => '2027-01-01',
            'summ' => '1000',
        ]);

        $this->assertSame((int) $teamA->id, (int) $payment->team_id);

        $paymentAgain = $recorder->record('ledger-team-1', $this->partner->id, (int) $this->user->id, [
            'user_id' => (int) $this->user->id,
            'user_name' => 'Test Updated',
            'team_id' => (int) $teamB->id,
            'team_title' => 'Группа Бета',
            'operation_date' => now()->format('Y-m-d H:i:s'),
            'payment_month' => '2027-01-01',
            'summ' => '1000',
        ]);

        $this->assertSame((int) $teamA->id, (int) $paymentAgain->fresh()->team_id);
        $this->assertSame('Группа Бета', (string) $paymentAgain->fresh()->team_title);
    }

    private function grantTbankCardPermission(): void
    {
        $permId = $this->permissionId('payment.method.tbankCard');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
