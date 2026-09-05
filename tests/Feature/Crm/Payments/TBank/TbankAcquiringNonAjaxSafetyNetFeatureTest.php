<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\PartnerPayment;
use App\Models\PaymentSystem;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Non-AJAX safety-net: native POST без X-Requested-With → 302 / JSON не пустой 200, запись создана.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TbankAcquiringNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;
    use TbankAcquiringTestHelpers;

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
    }

    public function test_non_ajax_wallet_sbp_creates_pending_and_is_not_empty_200_without_row(): void
    {
        $this->fakeAcquiringInit('994001');

        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 80,
                'partner_id' => $this->partner->id,
                'payment_method' => 'tinkoff_sbp',
            ]);

        $this->assertNotSame(422, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $tx = $this->latestWalletTx();
        $this->assertNotNull($tx);
        $this->assertSame('tinkoff', $tx->provider);
        $this->assertSame('pending', $tx->status);
        $this->assertSame(8000, (int) $tx->amount_cents);
        $this->assertStringContainsString('/tinkoff/qr/994001', (string) $response->json('redirect'));
    }

    public function test_non_ajax_wallet_sbp_below_ten_redirects_with_amount_error(): void
    {
        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 9.99,
                'partner_id' => $this->partner->id,
                'payment_method' => 'tinkoff_sbp',
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertRedirect(route('partner.wallet'));
        $response->assertSessionHasErrors(['amount']);
        $this->assertSame(
            'Сумма для СБП должна быть не меньше 10 ₽.',
            session('errors')->first('amount')
        );
        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_after_sbp_validation_redirect_keeps_tinkoff_radio_checked(): void
    {
        $html = $this->from(route('partner.wallet'))
            ->followingRedirects()
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 5,
                'partner_id' => $this->partner->id,
                'payment_method' => 'tinkoff_sbp',
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="walletPayTinkoffSbp"[^>]*checked/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="walletPayYookassa"[^>]*checked/',
            $html
        );
        $this->assertWalletFieldSlotContains($html, 'amount', 'Сумма для СБП должна быть не меньше 10 ₽.');
        $this->assertWalletFieldSlotNotContains($html, 'payment_method', 'Сумма для СБП должна быть не меньше 10 ₽.');
    }

    public function test_non_ajax_service_sbp_redirects_to_qr_and_creates_pending(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->fakeAcquiringInit('994002');

        $this->from(route('partner.payment.recharge'))
            ->post(route('partner.payment.tinkoff.sbp'), [
                '_token' => csrf_token(),
                'amount' => 2500,
                'days' => 29,
                'partner_id' => $this->partner->id,
                'description' => 'Учет до 200 пользователей',
            ])
            ->assertRedirect(route('tinkoff.qr', '994002'));

        $payment = PartnerPayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame('tinkoff_sbp', $payment->payment_method);
        $this->assertSame('pending', $payment->payment_status);
    }

    public function test_non_ajax_service_sbp_below_ten_redirects_with_amount_error(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $response = $this->from(route('partner.payment.recharge'))
            ->post(route('partner.payment.tinkoff.sbp'), [
                '_token' => csrf_token(),
                'amount' => 9,
                'days' => 29,
                'partner_id' => $this->partner->id,
                'description' => 'Учет до 200 пользователей',
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertRedirect(route('partner.payment.recharge'));
        $response->assertSessionHasErrors(['amount']);
        $this->assertSame(0, (int) PartnerPayment::query()->count());
    }

    public function test_store_acquiring_non_ajax_redirects_and_saves_global_row(): void
    {
        $this->grantNamedPermission($this->user, 'settings.paymentSystems.view');
        $this->grantNamedPermission($this->user, 'payment.method.tbankCard');

        $response = $this->post(route('payment-systems.store'), [
            '_token' => csrf_token(),
            'name' => 'tbank_acquiring',
            'terminal_key' => 'NON-AJAX-ACQ',
            'token_password' => 'NON-AJAX-PWD',
            'is_enabled' => 1,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.setting.paymentSystem'));
        $response->assertSessionHas('status');

        $row = PaymentSystem::globalTbankAcquiring();
        $this->assertNotNull($row);
        $this->assertNull($row->partner_id);
        $this->assertSame('NON-AJAX-ACQ', $row->settings['terminal_key'] ?? null);
    }

    private function assertWalletFieldSlotContains(string $html, string $field, string $message): void
    {
        $this->assertTrue(
            (bool) preg_match(
                '/data-error-for="'.preg_quote($field, '/').'"[^>]*>\s*'.preg_quote($message, '/').'\s*<\/div>/u',
                $html
            ),
            "Ожидали «{$message}» в data-error-for=\"{$field}\""
        );
    }

    private function assertWalletFieldSlotNotContains(string $html, string $field, string $message): void
    {
        $this->assertFalse(
            (bool) preg_match(
                '/data-error-for="'.preg_quote($field, '/').'"[^>]*>\s*'.preg_quote($message, '/').'\s*<\/div>/u',
                $html
            ),
            "Не ожидали «{$message}» в data-error-for=\"{$field}\""
        );
    }
}
