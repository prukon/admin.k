<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Platform;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\Platform\Concerns\PlatformPaymentsMethodTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * AJAX JSON-контракт способов оплаты платформы: 200 / 422 errors[field].
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/partner-wallet.html#tbank-sbp
 */
final class PlatformPaymentsMethodAjaxContractFeatureTest extends CrmTestCase
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

    public function test_wallet_ajax_with_tbank_permission_returns_ok_and_qr_redirect(): void
    {
        $this->fakeAcquiringInit('996001');

        $this->postJson(
            route('partner.wallet.topup'),
            $this->walletTopupPayload(),
            $this->acquiringAjaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'redirect']);
    }

    public function test_omitted_payment_method_applies_sbp_minimum_when_admin_has_tbank(): void
    {
        $this->postJson(
            route('partner.wallet.topup'),
            [
                'amount' => 5,
                'partner_id' => $this->partner->id,
            ],
            $this->acquiringAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Сумма для СБП должна быть не меньше 10 ₽.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_omitted_payment_method_uses_yookassa_when_only_yookassa_is_allowed(): void
    {
        $actor = $this->userWithOnlyPermissions([
            'partnerWallet.view',
            'platformPayments.method.yookassa',
        ]);
        $this->actingAs($actor);
        $this->useDummyYookassaCredentials();

        $response = $this->postJson(
            route('partner.wallet.topup'),
            [
                'amount' => 5,
                'partner_id' => $this->partner->id,
            ],
            $this->acquiringAjaxHeaders()
        );

        $this->assertNotSame(422, $response->getStatusCode(), 'Только ЮKassa: 5 ₽ не должны падать на минимум СБП');
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function test_yookassa_without_permission_returns_422_on_payment_method(): void
    {
        $this->postJson(
            route('partner.wallet.topup'),
            $this->walletTopupPayload(['payment_method' => 'yookassa']),
            $this->acquiringAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Некорректный способ оплаты.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_tbank_is_rejected_when_user_has_only_yookassa(): void
    {
        $actor = $this->userWithOnlyPermissions([
            'partnerWallet.view',
            'platformPayments.method.yookassa',
        ]);
        $this->actingAs($actor);

        $this->postJson(
            route('partner.wallet.topup'),
            $this->walletTopupPayload(['payment_method' => 'tinkoff_sbp']),
            $this->acquiringAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Некорректный способ оплаты.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_wallet_without_method_permissions_returns_422_on_payment_method(): void
    {
        $actor = $this->userWithOnlyPermissions(['partnerWallet.view']);
        $this->actingAs($actor);

        $this->postJson(
            route('partner.wallet.topup'),
            $this->walletTopupPayload(),
            $this->acquiringAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Нет доступного способа оплаты.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_service_omitted_payment_method_applies_sbp_minimum_for_admin(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $this->postJson(route('partner.payment.tinkoff.sbp'), [
            'amount' => 5,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Сумма для СБП должна быть не меньше 10 ₽.');

        $this->assertNoPartnerPayment();
    }

    public function test_service_omitted_payment_method_uses_yookassa_when_only_yookassa_is_allowed(): void
    {
        $actor = $this->userWithOnlyPermissions([
            'servicePayments.view',
            'platformPayments.method.yookassa',
        ]);
        $this->actingAs($actor);
        $this->useDummyYookassaCredentials();

        $response = $this->postJson(route('partner.payment.tinkoff.sbp'), [
            'amount' => 5,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
        ]);

        $this->assertNotSame(422, $response->getStatusCode(), 'Только ЮKassa: 5 ₽ не должны падать на минимум СБП');
        $this->assertNotSame(
            'Сумма для СБП должна быть не меньше 10 ₽.',
            (string) optional(session('errors'))->first('amount')
        );
        $this->assertContains($response->getStatusCode(), [200, 302, 500]);
    }

    public function test_service_without_method_permissions_returns_422_on_payment_method(): void
    {
        $actor = $this->userWithOnlyPermissions(['servicePayments.view']);
        $this->actingAs($actor);

        $this->postJson(route('partner.payment.tinkoff.sbp'), $this->servicePayPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Нет доступного способа оплаты.');

        $this->assertNoPartnerPayment();
    }

    public function test_service_yookassa_without_permission_returns_422_on_payment_method(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $this->postJson(
            route('partner.payment.tinkoff.sbp'),
            $this->servicePayPayload(['payment_method' => 'yookassa'])
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Некорректный способ оплаты.');

        $this->assertNoPartnerPayment();
    }
}
