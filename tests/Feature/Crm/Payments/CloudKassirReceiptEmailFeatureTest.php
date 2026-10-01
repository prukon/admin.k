<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Jobs\SendCloudKassirReceiptJob;
use App\Models\FiscalReceipt;
use App\Models\ParentProfile;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Services\TeamUserSyncService;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Чек CloudKassir после оплаты T‑Bank: родитель, иначе ученик, иначе школа.
 * Ручная отметка и Робокасса чек не создают.
 */
final class CloudKassirReceiptEmailFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_bank_webhook_sends_custom_payment_receipt_to_parent_not_student_or_school(): void
    {
        $this->attachParent('  Parent@Mail.ru  ', 'student@example.com');
        $schoolEmail = (string) $this->partner->email;

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);

        $custom = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $chain['team']->id,
            'amount_cents' => 150000,
            'note' => 'Форма',
            'is_paid' => false,
        ]);

        $payable = $this->makePayable('custom_payment_fee', (int) $chain['team']->id, [
            'user_period_price_id' => $custom->id,
        ], 150000);
        $intent = $this->makeIntent($payable, 'Дополнительный платеж', 150000);

        Auth::logout();
        $this->postBankWebhook($payable, $intent, 'order-custom-1', 770001)
            ->assertOk()
            ->assertSee('OK');

        $custom->refresh();
        $this->assertTrue((bool) $custom->is_paid);

        $emails = $this->sendQueuedReceipts();
        $this->assertSame(['Parent@Mail.ru'], $emails);
        $this->assertNotContains('student@example.com', $emails);
        $this->assertNotContains($schoolEmail, $emails);
    }

    public function test_bank_webhook_sends_one_receipt_when_parent_and_student_emails_match(): void
    {
        $this->attachParent('Parent@Mail.ru', 'parent@mail.ru');

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $payable = $this->makePayable('monthly_fee', (int) $chain['team']->id, [
            'month' => '2026-03-01',
        ], 350000, '2026-03-01');
        $intent = $this->makeIntent($payable, '2026-03-01', 350000);

        Auth::logout();
        $this->postBankWebhook($payable, $intent, 'order-month-same', 770002)->assertOk();

        $emails = $this->sendQueuedReceipts();
        $this->assertSame(['Parent@Mail.ru'], $emails);
        $this->assertCount(1, Http::recorded());
    }

    public function test_bank_webhook_sends_receipt_to_student_when_parent_email_is_blank(): void
    {
        $this->attachParent('   ', 'student@example.com');

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $payable = $this->makePayable('monthly_fee', (int) $chain['team']->id, [
            'month' => '2026-04-01',
        ], 100000, '2026-04-01');
        $intent = $this->makeIntent($payable, '2026-04-01', 100000);

        Auth::logout();
        $this->postBankWebhook($payable, $intent, 'order-month-student', 770003)->assertOk();

        $this->assertSame(['student@example.com'], $this->sendQueuedReceipts());
    }

    public function test_bank_webhook_sends_receipt_to_school_when_parent_and_student_have_no_email(): void
    {
        $this->attachParent(null, null);
        $schoolEmail = (string) $this->partner->fresh()->email;

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $payable = $this->makePayable('club_fee', (int) $chain['team']->id, [], 80000);
        $intent = $this->makeIntent($payable, 'Клубный взнос', 80000);

        Auth::logout();
        $this->postBankWebhook($payable, $intent, 'order-club-school', 770004)->assertOk();

        $this->assertSame([$schoolEmail], $this->sendQueuedReceipts());
    }

    public function test_package_receipt_goes_to_parent_email(): void
    {
        $this->attachParent('Parent@Mail.ru', 'other@example.com');

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $payable = $this->makePayable('lesson_package_fee', (int) $chain['team']->id, [
            'user_lesson_package_id' => 1,
        ], 200000);
        $intent = $this->makeIntent($payable, 'Абонемент', 200000);

        Auth::logout();
        $this->postBankWebhook($payable, $intent, 'order-package', 770005)->assertOk();

        $this->assertSame(['Parent@Mail.ru'], $this->sendQueuedReceipts());
    }

    public function test_return_receipt_goes_to_parent_email(): void
    {
        $this->attachParent('Parent@Mail.ru', 'student@example.com');
        $this->fakeCloudKassir();

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $payable = $this->makePayable('monthly_fee', (int) $chain['team']->id, [
            'month' => '2026-05-01',
        ], 100000, '2026-05-01');
        $payable->forceFill(['status' => 'refunded'])->save();
        $intent = $this->makeIntent($payable, '2026-05-01', 100000);
        $intent->forceFill(['status' => 'paid'])->save();

        $receipt = FiscalReceipt::query()->create([
            'partner_id' => $this->partner->id,
            'payment_intent_id' => $intent->id,
            'payable_id' => $payable->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'type' => FiscalReceipt::TYPE_INCOME_RETURN,
            'status' => FiscalReceipt::STATUS_PENDING,
            'amount_cents' => 100000,
            'invoice_id' => 'return_'.$intent->id,
            'account_id' => (string) $this->user->id,
            'idempotency_key' => 'return:test:'.$intent->id,
        ]);

        dispatch_sync(new SendCloudKassirReceiptJob($receipt->id));

        $payloads = $this->recordedReceiptPayloads();
        $this->assertCount(1, $payloads);
        $this->assertSame('IncomeReturn', $payloads[0]['Type']);
        $this->assertSame('Parent@Mail.ru', $payloads[0]['CustomerReceipt']['Email']);
    }

    public function test_rejected_bank_webhook_does_not_email_a_receipt(): void
    {
        $this->attachParent('Parent@Mail.ru', 'student@example.com');
        Http::fake();

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $payable = $this->makePayable('custom_payment_fee', (int) $chain['team']->id, [], 150000);
        $intent = $this->makeIntent($payable, 'Дополнительный платеж', 150000);

        Auth::logout();
        $this->postBankWebhook($payable, $intent, 'order-rejected', 770006, 'REJECTED')
            ->assertOk()
            ->assertSee('OK');

        $this->assertSame(0, FiscalReceipt::query()->count());
        $intent->refresh();
        $this->assertSame('failed', (string) $intent->status);
        Http::assertNothingSent();
    }

    public function test_bad_bank_signature_does_not_email_a_receipt(): void
    {
        $this->attachParent('Parent@Mail.ru', 'student@example.com');
        Http::fake();

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $payable = $this->makePayable('monthly_fee', (int) $chain['team']->id, [
            'month' => '2026-06-01',
        ], 100000, '2026-06-01');
        $intent = $this->makeIntent($payable, '2026-06-01', 100000);

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM',
            'token_password' => 'PWD',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2CP',
        ]);
        TinkoffPayment::query()->create([
            'order_id' => 'order-bad-sign',
            'partner_id' => $this->partner->id,
            'amount' => 100000,
            'method' => 'card',
            'status' => 'FORM',
        ]);
        $intent->update([
            'tbank_order_id' => 'order-bad-sign',
            'tbank_payment_id' => 770007,
            'provider_inv_id' => 770007,
        ]);

        Auth::logout();
        $this->post('/webhooks/tinkoff/payments', [
            'TerminalKey' => 'TERM',
            'OrderId' => 'order-bad-sign',
            'PaymentId' => 770007,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'Token' => 'bad-token',
            'Data' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
            ],
        ])->assertOk()->assertSee('OK');

        $this->assertSame(0, FiscalReceipt::query()->count());
        $this->assertSame('pending', (string) $intent->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_robokassa_payment_does_not_email_a_cloudkassir_receipt(): void
    {
        $this->attachParent('Parent@Mail.ru', 'student@example.com');
        Http::fake();

        PaymentSystem::factory()->robokassa()->create([
            'partner_id' => $this->partner->id,
        ]);

        $chain = $this->seedFiscalTeamChainForStudent(entityOverrides: [
            'tax_id' => '7700000000',
        ]);
        $custom = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $chain['team']->id,
            'amount_cents' => 150000,
            'note' => 'Форма',
            'is_paid' => false,
        ]);
        $payable = $this->makePayable('custom_payment_fee', (int) $chain['team']->id, [
            'user_period_price_id' => $custom->id,
        ], 150000);
        $intent = $this->makeIntent($payable, 'Дополнительный платеж', 150000);
        $intent->forceFill([
            'provider' => 'robokassa',
            'tbank_order_id' => null,
            'tbank_payment_id' => null,
        ])->save();

        $invId = 1000000000 + (int) $intent->id;
        $intent->forceFill(['provider_inv_id' => $invId])->save();

        $outSum = '1500.00';
        $date = 'Дополнительный платеж';
        $signature = strtoupper(md5("{$outSum}:{$invId}:pass2:Shp_paymentDate={$date}:Shp_userId={$this->user->id}"));

        Auth::logout();
        $this->get(route('payment.result', [
            'OutSum' => $outSum,
            'InvId' => $invId,
            'SignatureValue' => $signature,
            'Shp_paymentDate' => $date,
            'Shp_userId' => (string) $this->user->id,
        ]))->assertOk();

        $this->assertTrue((bool) $custom->fresh()->is_paid);
        $this->assertSame('paid', (string) $intent->fresh()->status);
        $this->assertSame(0, FiscalReceipt::query()->count());
        Http::assertNothingSent();
    }

    public function test_guest_and_user_without_rights_cannot_mark_custom_payment_paid(): void
    {
        Http::fake();
        $payment = $this->makeCustomPaymentRow();

        Auth::logout();
        $guest = $this->post(route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]), [
            'mode' => 'paid',
            'comment' => 'Оплата наличными',
        ]);
        $this->assertContains($guest->getStatusCode(), [302, 401]);
        $this->assertNotSame(500, $guest->getStatusCode());
        $this->assertNull($payment->fresh()->is_manual_paid);
        $this->assertSame(0, FiscalReceipt::query()->count());

        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->actingAs($actor);
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]), [
                'mode' => 'paid',
                'comment' => 'Оплата наличными',
            ])
            ->assertForbidden();

        $this->assertNull($payment->fresh()->is_manual_paid);
        Http::assertNothingSent();
    }

    public function test_manual_mark_paid_without_comment_returns_field_error_and_does_not_send_receipt(): void
    {
        Http::fake();
        $this->grantManualPaidAccess($this->user);
        $payment = $this->makeCustomPaymentRow();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]), [
                'mode' => 'paid',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);

        $this->assertNull($payment->fresh()->is_manual_paid);

        $this->flushHeaders();
        $this->from(route('admin.settingPrices.customPayments'))
            ->post(route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]), [
                'mode' => 'paid',
            ])
            ->assertRedirect(route('admin.settingPrices.customPayments'))
            ->assertSessionHasErrors(['comment']);

        $this->assertNull($payment->fresh()->is_manual_paid);
        $this->assertSame(0, FiscalReceipt::query()->count());
        Http::assertNothingSent();
    }

    public function test_manual_mark_paid_saves_status_and_does_not_send_receipt(): void
    {
        Http::fake();
        $this->grantManualPaidAccess($this->user);
        $payment = $this->makeCustomPaymentRow();
        $body = [
            'mode' => 'paid',
            'comment' => 'Оплата наличными',
        ];

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]), $body)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.is_manual_paid', true);

        $this->assertTrue((bool) $payment->fresh()->is_manual_paid);
        $this->assertSame(0, FiscalReceipt::query()->count());

        $second = $this->makeCustomPaymentRow();
        $this->flushHeaders();
        $plain = $this->post(route('setting-prices.custom-payments.manual-paid', ['id' => $second->id]), $body);
        $plain->assertOk();
        $plain->assertJsonPath('success', true);
        $this->assertNotSame('', (string) $plain->getContent());
        $this->assertTrue((bool) $second->fresh()->is_manual_paid);
        $this->assertSame(0, FiscalReceipt::query()->count());
        Http::assertNothingSent();
    }

    private function attachParent(?string $parentEmail, ?string $studentEmail): ParentProfile
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => $parentEmail,
        ]);
        $this->user->forceFill([
            'parent_id' => $parent->id,
            'email' => $studentEmail,
        ])->save();

        return $parent;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function makePayable(string $type, int $teamId, array $meta, int $amountCents, ?string $month = null): Payable
    {
        return Payable::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $month,
            'meta' => array_merge(['team_id' => $teamId], $meta),
        ]);
    }

    private function makeIntent(Payable $payable, string $paymentDate, int $amountCents): PaymentIntent
    {
        return PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum_cents' => $amountCents,
            'payment_date' => $paymentDate,
            'meta' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function postBankWebhook(
        Payable $payable,
        PaymentIntent $intent,
        string $orderId,
        int $paymentId,
        string $status = 'CONFIRMED',
    ): \Illuminate\Testing\TestResponse {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM',
            'token_password' => 'PWD',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2CP',
        ]);

        TinkoffPayment::query()->create([
            'order_id' => $orderId,
            'partner_id' => $this->partner->id,
            'amount' => (int) $payable->amount_cents,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $intent->update([
            'tbank_order_id' => $orderId,
            'tbank_payment_id' => $paymentId,
            'provider_inv_id' => $paymentId,
        ]);

        $payload = [
            'TerminalKey' => 'TERM',
            'OrderId' => $orderId,
            'PaymentId' => $paymentId,
            'Status' => $status,
            'Success' => $status === 'CONFIRMED',
            'SpAccumulationId' => 'deal-'.$orderId,
            'Data' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
            ],
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, 'PWD');

        return $this->post('/webhooks/tinkoff/payments', $payload);
    }

    /**
     * @return list<string>
     */
    private function sendQueuedReceipts(): array
    {
        $this->fakeCloudKassir();

        $receipts = FiscalReceipt::query()->orderBy('id')->get();
        $this->assertNotEmpty($receipts);

        foreach ($receipts as $receipt) {
            dispatch_sync(new SendCloudKassirReceiptJob((int) $receipt->id));
            $receipt->refresh();
            $this->assertSame(FiscalReceipt::STATUS_QUEUED, (string) $receipt->status);
            $stored = json_decode((string) $receipt->request_payload, true);
            $this->assertIsArray($stored);
        }

        return array_map(
            static fn (array $payload): string => (string) ($payload['CustomerReceipt']['Email'] ?? ''),
            $this->recordedReceiptPayloads(),
        );
    }

    private function fakeCloudKassir(): void
    {
        Config::set('services.cloudkassir.base_url', 'https://api.cloudpayments.ru');
        Config::set('services.cloudkassir.public_id', 'test-public-id');
        Config::set('services.cloudkassir.api_secret', 'test-secret');
        Config::set('services.cloudkassir.inn', '7708806062');
        Config::set('services.cloudkassir.timeout', 30);
        Config::set('services.cloudkassir.taxation_system', 1);
        Config::set('services.cloudkassir.default_method', 4);
        Config::set('services.cloudkassir.default_object', 4);
        Config::set('services.cloudkassir.russia_time_zone', 2);
        Config::set('services.cloudkassir.agent.enabled', false);

        Http::fake([
            'https://api.cloudpayments.ru/kkt/receipt' => Http::response([
                'Success' => true,
                'Message' => 'Queued',
                'Model' => [
                    'Id' => 'ck-queued-email',
                    'ErrorCode' => 0,
                    'ReceiptLocalUrl' => 'https://receipts.ru/ck-queued-email',
                ],
            ], 200),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recordedReceiptPayloads(): array
    {
        $payloads = [];
        foreach (Http::recorded() as [$request]) {
            if (! str_ends_with((string) $request->url(), '/kkt/receipt')) {
                continue;
            }
            $payloads[] = $request->data();
        }

        return $payloads;
    }

    private function makeCustomPaymentRow(): UserCustomPayment
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        return UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'amount_cents' => 50000,
            'note' => 'Форма',
            'is_paid' => false,
            'is_manual_paid' => null,
        ]);
    }

    private function grantManualPaidAccess(User $actor): void
    {
        foreach (['setPrices.view', 'setPrices.customPayments.view', 'setPrices.manualPaid.manage'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }
}
