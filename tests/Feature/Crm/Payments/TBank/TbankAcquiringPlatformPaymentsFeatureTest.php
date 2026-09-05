<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Jobs\SendCloudKassirReceiptJob;
use App\Models\FiscalReceipt;
use App\Models\PartnerAccess;
use App\Models\PartnerPayment;
use App\Models\PartnerWalletTransaction;
use App\Models\TinkoffPayment;
use App\Services\CloudKassir\CloudKassirReceiptBuilder;
use App\Services\Tinkoff\TbankAcquiringTerminalConfig;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

final class TbankAcquiringPlatformPaymentsFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->partner->forceFill([
            'email' => 'school@example.com',
            'activity_start_date' => '2026-01-01',
        ])->save();
        $this->seedGlobalTbankAcquiring();
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.agent.enabled', true);
    }

    public function test_acquiring_terminal_is_active_without_e2c_keys(): void
    {
        $this->assertTrue(TbankAcquiringTerminalConfig::isActive());
        $cfg = TbankAcquiringTerminalConfig::paymentConfig();
        $this->assertSame('TERM_ACQ', $cfg['terminal_key']);
        $this->assertStringContainsString('/webhooks/tinkoff/acquiring', $cfg['notify_url']);
    }

    public function test_wallet_sbp_topup_inits_without_deal_and_redirects_to_qr(): void
    {
        $this->fakeAcquiringInit('991001');

        $response = $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->walletAjaxHeaders());

        $response->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertStringContainsString('/tinkoff/qr/991001', (string) $response->json('redirect'));

        $tx = $this->latestWalletTx();
        $this->assertNotNull($tx);
        $this->assertSame('tinkoff', $tx->provider);
        $this->assertSame('pending', $tx->status);
        $this->assertSame(10000, (int) $tx->amount_cents);

        $tp = TinkoffPayment::query()->where('tinkoff_payment_id', '991001')->first();
        $this->assertNotNull($tp);
        $this->assertSame(TinkoffPayment::CHANNEL_ACQUIRING, $tp->channel);
        $this->assertSame('sbp', $tp->method);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/Init')) {
                return false;
            }
            $data = $request->data();

            return ($data['TerminalKey'] ?? null) === 'TERM_ACQ'
                && ! array_key_exists('CreateDealWithType', $data)
                && ($data['DATA']['scope'] ?? null) === 'partner_wallet_topup';
        });
    }

    public function test_wallet_sbp_amount_below_ten_returns_422(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 9.99,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Сумма для СБП должна быть не меньше 10 ₽.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_acquiring_webhook_credits_wallet_and_creates_platform_receipt(): void
    {
        Queue::fake([SendCloudKassirReceiptJob::class]);
        $before = (int) $this->partner->wallet_balance_cents;

        $tx = $this->makeWalletTx($this->partner->id, $this->user->id, 'Пополнение баланса KidsCRM', [
            'provider' => 'tinkoff',
            'status' => 'pending',
            'amount_cents' => 10000,
        ]);

        $tp = TinkoffPayment::query()->create([
            'order_id' => 'acq-wallet-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
            'payload' => [
                'init_data' => [
                    'scope' => 'partner_wallet_topup',
                    'wallet_transaction_id' => (string) $tx->id,
                ],
                'success_url' => url('/partner-wallet/success'),
            ],
        ]);

        $this->postJson('/webhooks/tinkoff/acquiring', $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CONFIRMED',
            'PaymentId' => '991002',
            'Amount' => 10000,
            'DATA' => [
                'scope' => 'partner_wallet_topup',
                'wallet_transaction_id' => (string) $tx->id,
            ],
        ]))->assertOk();

        $tx->refresh();
        $this->partner->refresh();
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame($before + 10000, (int) $this->partner->wallet_balance_cents);

        $receipt = FiscalReceipt::query()->where('wallet_transaction_id', $tx->id)->first();
        $this->assertNotNull($receipt);
        $this->assertSame(FiscalReceipt::SOURCE_PLATFORM, $receipt->source);
        Queue::assertPushed(SendCloudKassirReceiptJob::class, 1);

        $payload = app(CloudKassirReceiptBuilder::class)->build($receipt);
        $this->assertSame('Пополнение баланса KidsCRM', $payload['CustomerReceipt']['Items'][0]['Label']);
        $this->assertNull($payload['CustomerReceipt']['Items'][0]['Vat']);
        $this->assertArrayNotHasKey('AgentSign', $payload['CustomerReceipt']['Items'][0]);
        $this->assertSame('school@example.com', $payload['CustomerReceipt']['Email']);
        $this->assertSame('7708806062', $payload['Inn']);
    }

    public function test_acquiring_webhook_is_idempotent_for_wallet(): void
    {
        Queue::fake([SendCloudKassirReceiptJob::class]);
        $tx = $this->makeWalletTx($this->partner->id, $this->user->id, 'Пополнение баланса KidsCRM', [
            'provider' => 'tinkoff',
            'status' => 'pending',
            'amount_cents' => 5000,
        ]);
        $tp = TinkoffPayment::query()->create([
            'order_id' => 'acq-wallet-2',
            'partner_id' => $this->partner->id,
            'amount' => 5000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
        ]);
        $payload = $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CONFIRMED',
            'PaymentId' => '991003',
            'Amount' => 5000,
            'DATA' => [
                'scope' => 'partner_wallet_topup',
                'wallet_transaction_id' => (string) $tx->id,
            ],
        ]);

        $this->postJson('/webhooks/tinkoff/acquiring', $payload)->assertOk();
        $balance = (int) $this->partner->fresh()->wallet_balance_cents;
        $this->postJson('/webhooks/tinkoff/acquiring', $payload)->assertOk();

        $this->assertSame($balance, (int) $this->partner->fresh()->wallet_balance_cents);
        $this->assertSame(1, (int) FiscalReceipt::query()->where('wallet_transaction_id', $tx->id)->count());
    }

    public function test_service_sbp_creates_pending_and_redirects_to_qr(): void
    {
        $this->grantServicePaymentsView();
        $this->fakeAcquiringInit('992001');

        $this->post(route('partner.payment.tinkoff.sbp'), [
            'amount' => 2500,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
        ])->assertRedirect(route('tinkoff.qr', '992001'));

        $payment = PartnerPayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame('tinkoff_sbp', $payment->payment_method);
        $this->assertSame('pending', $payment->payment_status);
        $this->assertSame(250000, (int) $payment->amount_cents);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/Init')) {
                return false;
            }
            $data = $request->data();

            return ! array_key_exists('CreateDealWithType', $data)
                && ($data['DATA']['scope'] ?? null) === 'partner_service_payment';
        });
    }

    public function test_acquiring_webhook_activates_service_access_and_receipt_label(): void
    {
        Queue::fake([SendCloudKassirReceiptJob::class]);
        $this->grantServicePaymentsView();

        $partnerPayment = PartnerPayment::query()->create([
            'payment_id' => 'pending-svc',
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'amount_cents' => 250000,
            'payment_date' => now(),
            'payment_method' => 'tinkoff_sbp',
            'payment_status' => 'pending',
            'description' => 'Учет до 200 пользователей',
        ]);
        PartnerAccess::query()->create([
            'partner_payment_id' => $partnerPayment->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(29)->toDateString(),
            'is_active' => 0,
        ]);

        $tp = TinkoffPayment::query()->create([
            'order_id' => 'acq-svc-1',
            'partner_id' => $this->partner->id,
            'amount' => 250000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
        ]);

        $this->postJson('/webhooks/tinkoff/acquiring', $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CONFIRMED',
            'PaymentId' => '992002',
            'Amount' => 250000,
            'DATA' => [
                'scope' => 'partner_service_payment',
                'partner_payment_id' => (string) $partnerPayment->id,
            ],
        ]))->assertOk();

        $this->assertSame('succeeded', $partnerPayment->fresh()->payment_status);
        $this->assertSame(1, (int) PartnerAccess::query()->where('partner_payment_id', $partnerPayment->id)->value('is_active'));

        $receipt = FiscalReceipt::query()->where('partner_payment_id', $partnerPayment->id)->first();
        $this->assertNotNull($receipt);
        $payload = app(CloudKassirReceiptBuilder::class)->build($receipt);
        $this->assertSame('Оплата доступа KidsCRM', $payload['CustomerReceipt']['Items'][0]['Label']);
        $this->assertNull($payload['CustomerReceipt']['Items'][0]['Vat']);
        $this->assertArrayNotHasKey('AgentSign', $payload['CustomerReceipt']['Items'][0]);
    }

    public function test_payment_systems_page_shows_acquiring_card(): void
    {
        $this->grantPermission('settings.paymentSystems.view');
        $this->grantPermission('payment.method.tbankSBP');

        $this->get(route('admin.setting.paymentSystem'))
            ->assertOk()
            ->assertSee('T‑Банк (эквайринг)', false)
            ->assertSee('Обычный эквайринг платформы', false)
            ->assertViewHas('tbankAcquiring');
    }

    public function test_store_acquiring_ajax_saves_global_row_without_e2c(): void
    {
        $this->grantPermission('settings.paymentSystems.view');
        $this->grantPermission('payment.method.tbankCard');

        $this->postJson(route('payment-systems.store'), [
            'name' => 'tbank_acquiring',
            'terminal_key' => 'ACQ-NEW',
            'token_password' => 'ACQ-PWD',
            'is_enabled' => 1,
        ])->assertOk()->assertJsonPath('status', 'success');

        $row = \App\Models\PaymentSystem::globalTbankAcquiring();
        $this->assertNotNull($row);
        $this->assertNull($row->partner_id);
        $this->assertSame('ACQ-NEW', $row->settings['terminal_key'] ?? null);
        $this->assertTrue($row->is_connected);
    }

    public function test_acquiring_webhook_with_wrong_signature_does_not_credit_wallet(): void
    {
        $before = (int) $this->partner->wallet_balance_cents;
        $tx = $this->makeWalletTx($this->partner->id, $this->user->id, 'Пополнение баланса KidsCRM', [
            'provider' => 'tinkoff',
            'status' => 'pending',
            'amount_cents' => 10000,
        ]);
        $tp = TinkoffPayment::query()->create([
            'order_id' => 'acq-bad-sig',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
        ]);

        $this->postJson('/webhooks/tinkoff/acquiring', $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CONFIRMED',
            'PaymentId' => '991010',
            'Amount' => 10000,
            'DATA' => [
                'scope' => 'partner_wallet_topup',
                'wallet_transaction_id' => (string) $tx->id,
            ],
        ], 'WRONG_PASSWORD'))->assertOk();

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame($before, (int) $this->partner->fresh()->wallet_balance_cents);
        $this->assertSame('FORM', $tp->fresh()->status);
    }

    public function test_canceled_acquiring_webhook_marks_wallet_tx_canceled_without_credit(): void
    {
        $before = (int) $this->partner->wallet_balance_cents;
        $tx = $this->makeWalletTx($this->partner->id, $this->user->id, 'Пополнение баланса KidsCRM', [
            'provider' => 'tinkoff',
            'status' => 'pending',
            'amount_cents' => 10000,
        ]);
        $tp = TinkoffPayment::query()->create([
            'order_id' => 'acq-cancel',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
        ]);

        $this->postJson('/webhooks/tinkoff/acquiring', $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CANCELED',
            'PaymentId' => '991011',
            'Amount' => 10000,
            'DATA' => [
                'scope' => 'partner_wallet_topup',
                'wallet_transaction_id' => (string) $tx->id,
            ],
        ]))->assertOk();

        $this->assertSame('canceled', $tx->fresh()->status);
        $this->assertSame($before, (int) $this->partner->fresh()->wallet_balance_cents);
        $this->assertSame(0, (int) FiscalReceipt::query()->where('wallet_transaction_id', $tx->id)->count());
    }

    public function test_amount_mismatch_does_not_credit_wallet(): void
    {
        $before = (int) $this->partner->wallet_balance_cents;
        $tx = $this->makeWalletTx($this->partner->id, $this->user->id, 'Пополнение баланса KidsCRM', [
            'provider' => 'tinkoff',
            'status' => 'pending',
            'amount_cents' => 10000,
        ]);
        $tp = TinkoffPayment::query()->create([
            'order_id' => 'acq-mismatch',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
        ]);

        $this->postJson('/webhooks/tinkoff/acquiring', $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CONFIRMED',
            'PaymentId' => '991012',
            'Amount' => 9999,
            'DATA' => [
                'scope' => 'partner_wallet_topup',
                'wallet_transaction_id' => (string) $tx->id,
            ],
        ]))->assertOk();

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame($before, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_multisplit_webhook_does_not_confirm_or_credit_acquiring_payment(): void
    {
        $this->seedGlobalTbank();
        $before = (int) $this->partner->wallet_balance_cents;
        $tx = $this->makeWalletTx($this->partner->id, $this->user->id, 'Пополнение баланса KidsCRM', [
            'provider' => 'tinkoff',
            'status' => 'pending',
            'amount_cents' => 10000,
        ]);
        $tp = TinkoffPayment::query()->create([
            'order_id' => 'acq-wrong-url',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
        ]);

        $this->postJson('/webhooks/tinkoff/payments', $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CONFIRMED',
            'PaymentId' => '991013',
            'Amount' => 10000,
            'DATA' => [
                'scope' => 'partner_wallet_topup',
                'wallet_transaction_id' => (string) $tx->id,
            ],
        ], 'PWD_PAY'))->assertOk();

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame($before, (int) $this->partner->fresh()->wallet_balance_cents);
        $this->assertSame('FORM', $tp->fresh()->status);
    }

    public function test_wallet_sbp_init_payload_has_no_shop_code(): void
    {
        $this->fakeAcquiringInit('991014');

        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->walletAjaxHeaders())->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/Init')) {
                return false;
            }
            $data = $request->data();

            return ! array_key_exists('ShopCode', $data)
                && ! array_key_exists('CreateDealWithType', $data);
        });
    }

    private function fakeAcquiringInit(string $paymentId): void
    {
        Http::fake(function ($request) use ($paymentId) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => $paymentId,
                    'PaymentURL' => 'https://securepay.tinkoff.ru/'.$paymentId,
                ], 200);
            }

            return Http::response(['Success' => false, 'Message' => 'unexpected '.$request->url()], 500);
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function signedAcquiringPayload(array $fields, string $password = 'PWD_ACQ'): array
    {
        $fields['TerminalKey'] = $fields['TerminalKey'] ?? 'TERM_ACQ';
        $fields['Success'] = $fields['Success'] ?? true;
        $fields['Token'] = TinkoffSignature::makeToken($fields, $password);

        return $fields;
    }

    private function grantServicePaymentsView(): void
    {
        $this->grantPermission('servicePayments.view');
    }

    private function grantPermission(string $permissionName): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => (int) $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
