<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * Публичные вебхуки ЮKassa: зачисление на tx.partner_id, не Partner::first()
 * и не metadata.partner_id. Оба URL. IP-фильтр. Идемпотентность.
 *
 * @see /docs/documentation/partner-wallet.html
 */
final class PartnerWalletWebhookFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;

    /**
     * @return list<string>
     */
    private function webhookUrls(): array
    {
        return [
            route('partner.wallet.webhook'),
            '/webhook/yookassa',
        ];
    }

    public function test_guest_from_unknown_ip_gets_403_not_login_redirect(): void
    {
        Auth::logout();

        foreach ($this->webhookUrls() as $url) {
            $tx = $this->makePendingYookassaTx((int) $this->foreignPartner->id, (int) $this->foreignUser->id);
            $response = $this->postWalletWebhookJson(
                $url,
                $this->yookassaWalletWebhookPayload($tx),
                '127.0.0.1'
            );

            $this->assertNotSame(500, $response->getStatusCode(), $url);
            $this->assertNotSame(302, $response->getStatusCode(), $url.' не должен гнать гостя на логин');
            $response->assertStatus(403);
            $this->assertNotSame('', trim((string) $response->getContent()));
            $this->assertSame('pending', $tx->fresh()->status);
        }
    }

    public function test_guest_from_yookassa_ip_can_post_without_csrf(): void
    {
        Auth::logout();

        $this->partner->forceFill(['wallet_balance_cents' => 10000])->save();
        $tx = $this->makePendingYookassaTx((int) $this->partner->id, (int) $this->user->id, 7000);

        $response = $this->postWalletWebhookJson(
            route('partner.wallet.webhook'),
            $this->yookassaWalletWebhookPayload($tx)
        );

        $this->assertNotSame(419, $response->getStatusCode());
        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('succeeded', $tx->fresh()->status);
        $this->assertSame(17000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_succeeded_credits_tx_partner_not_first_partner_or_metadata(): void
    {
        $this->partner->forceFill(['wallet_balance_cents' => 11100])->save();
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 22200])->save();

        $firstId = (int) Partner::query()->orderBy('id')->value('id');
        $this->assertSame((int) $this->partner->id, $firstId, 'Partner::first() в этом тесте — школа админа');

        $tx = $this->makePendingYookassaTx((int) $this->foreignPartner->id, (int) $this->foreignUser->id, 7000);

        $response = $this->postWalletWebhookJson(
            route('partner.wallet.webhook'),
            $this->yookassaWalletWebhookPayload($tx, metadataExtra: [
                'partner_id' => (string) $this->partner->id,
            ])
        );

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('succeeded', $tx->fresh()->status);
        $this->assertSame(29200, (int) $this->foreignPartner->fresh()->wallet_balance_cents);
        $this->assertSame(11100, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_unified_yookassa_webhook_credits_tx_partner_when_scope_is_wallet_topup(): void
    {
        $this->partner->forceFill(['wallet_balance_cents' => 5000])->save();
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 8000])->save();

        $tx = $this->makePendingYookassaTx((int) $this->foreignPartner->id, (int) $this->foreignUser->id, 4000);

        $response = $this->postWalletWebhookJson(
            '/webhook/yookassa',
            $this->yookassaWalletWebhookPayload($tx, metadataExtra: [
                'partner_id' => (string) $this->partner->id,
            ])
        );

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('succeeded', $tx->fresh()->status);
        $this->assertSame(12000, (int) $this->foreignPartner->fresh()->wallet_balance_cents);
        $this->assertSame(5000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_canceled_does_not_change_balance(): void
    {
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 20000])->save();
        $tx = $this->makePendingYookassaTx((int) $this->foreignPartner->id, (int) $this->foreignUser->id, 7000);

        $this->postWalletWebhookJson(
            route('partner.wallet.webhook'),
            $this->yookassaWalletWebhookPayload($tx, 'payment.canceled')
        )->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('canceled', $tx->fresh()->status);
        $this->assertSame(20000, (int) $this->foreignPartner->fresh()->wallet_balance_cents);
    }

    public function test_already_succeeded_webhook_is_idempotent_and_does_not_double_credit(): void
    {
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 20000])->save();
        $tx = $this->makePendingYookassaTx((int) $this->foreignPartner->id, (int) $this->foreignUser->id, 7000);
        $payload = $this->yookassaWalletWebhookPayload($tx);

        $this->postWalletWebhookJson(route('partner.wallet.webhook'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postWalletWebhookJson(route('partner.wallet.webhook'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('succeeded', $tx->fresh()->status);
        $this->assertSame(27000, (int) $this->foreignPartner->fresh()->wallet_balance_cents);
    }

    public function test_amount_mismatch_does_not_credit(): void
    {
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 20000])->save();
        $tx = $this->makePendingYookassaTx((int) $this->foreignPartner->id, (int) $this->foreignUser->id, 7000);

        $this->postWalletWebhookJson(
            route('partner.wallet.webhook'),
            $this->yookassaWalletWebhookPayload($tx, amountValue: '71.00')
        )->assertStatus(422);

        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame(20000, (int) $this->foreignPartner->fresh()->wallet_balance_cents);
    }

    public function test_missing_wallet_transaction_id_returns_400_not_500(): void
    {
        $tx = $this->makePendingYookassaTx((int) $this->partner->id, (int) $this->user->id);
        $payload = $this->yookassaWalletWebhookPayload($tx);
        unset($payload['object']['metadata']['wallet_transaction_id']);

        $response = $this->postWalletWebhookJson(route('partner.wallet.webhook'), $payload);
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(400);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_unknown_wallet_transaction_returns_404_not_500(): void
    {
        $tx = $this->makePendingYookassaTx((int) $this->partner->id, (int) $this->user->id);
        $payload = $this->yookassaWalletWebhookPayload($tx);
        $payload['object']['metadata']['wallet_transaction_id'] = '999999';

        $response = $this->postWalletWebhookJson(route('partner.wallet.webhook'), $payload);
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(404);
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_wallet_webhook_bad_payload_returns_400_unified_returns_422(): void
    {
        $wallet = $this->postWalletWebhookJson(route('partner.wallet.webhook'), ['event' => 'payment.succeeded']);
        $this->assertNotSame(500, $wallet->getStatusCode());
        $wallet->assertStatus(400);
        $this->assertNotSame('', trim((string) $wallet->getContent()));

        $unified = $this->postWalletWebhookJson('/webhook/yookassa', ['event' => 'payment.succeeded']);
        $this->assertNotSame(500, $unified->getStatusCode());
        $unified->assertStatus(422);
        $this->assertNotSame('', trim((string) $unified->getContent()));
    }

    public function test_get_webhook_is_not_500(): void
    {
        Auth::logout();

        $response = $this->withServerVariables(['REMOTE_ADDR' => $this->yookassaAllowedIp()])
            ->get(route('partner.wallet.webhook'));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [404, 405]);
    }
}
