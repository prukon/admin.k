<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Platform;

use App\Models\PartnerPayment;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\Platform\Concerns\PlatformPaymentsMethodTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Native POST без X-Requested-With: 302 / не пустой 200, запись в БД, old() сохраняет выбранный способ.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PlatformPaymentsMethodNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;
    use PlatformPaymentsMethodTestHelpers;
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
        $this->fakeAcquiringInit('996101');

        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), array_merge(
                ['_token' => csrf_token()],
                $this->walletTopupPayload(['amount' => 80])
            ));

        $this->assertNotSame(422, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $tx = $this->latestWalletTx();
        $this->assertNotNull($tx);
        $this->assertSame('tinkoff', $tx->provider);
        $this->assertSame('pending', $tx->status);
        $this->assertSame(8000, (int) $tx->amount_cents);
    }

    public function test_non_ajax_wallet_without_method_permission_redirects_with_payment_method_error(): void
    {
        $actor = $this->userWithOnlyPermissions(['partnerWallet.view']);
        $this->actingAs($actor);

        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), array_merge(
                ['_token' => csrf_token()],
                $this->walletTopupPayload()
            ));

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertRedirect(route('partner.wallet'));
        $response->assertSessionHasErrors(['payment_method']);
        $this->assertSame(
            'Нет доступного способа оплаты.',
            session('errors')->first('payment_method')
        );
        $this->assertTopupDidNotCreateTransaction();

        $html = $this->from(route('partner.wallet'))
            ->followingRedirects()
            ->post(route('partner.wallet.topup'), array_merge(
                ['_token' => csrf_token()],
                $this->walletTopupPayload()
            ))
            ->assertOk()
            ->getContent();

        $this->assertFieldSlotContains($html, 'payment_method', 'Нет доступного способа оплаты.');
        $this->assertMatchesRegularExpression('/id="topupBtn"[^>]*disabled/', $html);
    }

    public function test_after_validation_keeps_selected_yookassa_checked_when_both_methods_allowed(): void
    {
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');

        $html = $this->from(route('partner.wallet'))
            ->followingRedirects()
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 0.5,
                'partner_id' => $this->partner->id,
                'payment_method' => 'yookassa',
            ])
            ->assertOk()
            ->getContent();

        $this->assertRadioChecked($html, 'walletPayYookassa');
        $this->assertRadioNotChecked($html, 'walletPayTinkoffSbp');
        $this->assertFieldSlotContains($html, 'amount', 'Сумма должна быть не меньше 1 ₽.');
        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_after_sbp_validation_with_both_methods_does_not_switch_to_yookassa(): void
    {
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');

        $html = $this->from(route('partner.wallet'))
            ->followingRedirects()
            ->post(route('partner.wallet.topup'), array_merge(
                ['_token' => csrf_token()],
                $this->walletTopupPayload(['amount' => 5])
            ))
            ->assertOk()
            ->getContent();

        $this->assertRadioChecked($html, 'walletPayTinkoffSbp');
        $this->assertRadioNotChecked($html, 'walletPayYookassa');
        $this->assertFieldSlotContains($html, 'amount', 'Сумма для СБП должна быть не меньше 10 ₽.');
    }

    public function test_non_ajax_service_without_method_permission_redirects_with_payment_method_error(): void
    {
        $actor = $this->userWithOnlyPermissions(['servicePayments.view']);
        $this->actingAs($actor);

        $response = $this->from(route('partner.payment.recharge'))
            ->post(route('partner.payment.tinkoff.sbp'), array_merge(
                ['_token' => csrf_token()],
                $this->servicePayPayload()
            ));

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertRedirect(route('partner.payment.recharge'));
        $response->assertSessionHasErrors(['payment_method']);
        $this->assertSame(
            'Нет доступного способа оплаты.',
            session('errors')->first('payment_method')
        );
        $this->assertNoPartnerPayment();

        $html = $this->from(route('partner.payment.recharge'))
            ->followingRedirects()
            ->post(route('partner.payment.tinkoff.sbp'), array_merge(
                ['_token' => csrf_token()],
                $this->servicePayPayload()
            ))
            ->assertOk()
            ->getContent();

        $card = $this->serviceRechargeCardHtml($html);
        $this->assertFieldSlotContains($card, 'payment_method', 'Нет доступного способа оплаты.');
        $this->assertStringContainsString('Нет доступного способа оплаты.', $card);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*disabled/', $card);
    }

    public function test_after_service_validation_keeps_selected_yookassa_checked(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');

        $html = $this->from(route('partner.payment.recharge'))
            ->followingRedirects()
            ->post(route('partner.payment.tinkoff.sbp'), array_merge(
                ['_token' => csrf_token()],
                $this->servicePayPayload([
                    'amount' => 0.5,
                    'payment_method' => 'yookassa',
                ])
            ))
            ->assertOk()
            ->getContent();

        $card = $this->serviceRechargeCardHtml($html);
        $this->assertRadioChecked($card, 'servicePayYookassa');
        $this->assertRadioNotChecked($card, 'servicePayTinkoffSbp');
        $this->assertFieldSlotContains($card, 'amount', 'Сумма должна быть не меньше 1 ₽.');
        $this->assertNoPartnerPayment();
    }

    public function test_non_ajax_service_sbp_redirects_to_qr_and_creates_pending(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->fakeAcquiringInit('996102');

        $this->from(route('partner.payment.recharge'))
            ->post(route('partner.payment.tinkoff.sbp'), array_merge(
                ['_token' => csrf_token()],
                $this->servicePayPayload()
            ))
            ->assertRedirect(route('tinkoff.qr', '996102'));

        $payment = PartnerPayment::query()->first();
        $this->assertNotNull($payment);
        $this->assertSame('tinkoff_sbp', $payment->payment_method);
        $this->assertSame('pending', $payment->payment_status);
    }

    public function test_non_ajax_service_yookassa_with_permission_is_not_empty_200_without_intent(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');
        $this->useDummyYookassaCredentials();

        $response = $this->from(route('partner.payment.recharge'))
            ->post(route('partner.payment.tinkoff.sbp'), array_merge(
                ['_token' => csrf_token()],
                $this->servicePayPayload(['payment_method' => 'yookassa'])
            ));

        $this->assertNotSame(422, $response->getStatusCode(), 'С правом ЮKassa native POST не должен быть 422 на способ');
        $this->assertNotSame(
            'Некорректный способ оплаты.',
            (string) optional(session('errors'))->first('payment_method')
        );
        $this->assertContains($response->getStatusCode(), [200, 302, 500]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $payment = PartnerPayment::query()->first();
        if ($payment !== null) {
            $this->assertSame('yookassa', $payment->payment_method);
        }
    }
}
