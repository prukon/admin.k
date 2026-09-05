<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Platform;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\Platform\Concerns\PlatformPaymentsMethodTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Разметка и правила «если X, то по умолчанию Y» для способов оплаты платформы.
 * Каждый UI-триггер открытия/пересборки и негатив «не X → Y не навязывается».
 *
 * @see /docs/documentation/partner-wallet.html#tbank-sbp
 */
final class PlatformPaymentsMethodUxFeatureTest extends CrmTestCase
{
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
    }

    public function test_wallet_first_open_checks_tbank_enables_pay_and_hides_yookassa_for_admin(): void
    {
        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertStringContainsString('id="walletPayTinkoffSbp"', $html);
        $this->assertStringNotContainsString('id="walletPayYookassa"', $html);
        $this->assertRadioChecked($html, 'walletPayTinkoffSbp');
        $this->assertDoesNotMatchRegularExpression('/id="topupBtn"[^>]*disabled/', $html);

        $amountPos = strpos($html, 'id="walletTopupAmount"');
        $methodPos = strpos($html, 'id="walletPayTinkoffSbp"');
        $submitPos = strpos($html, 'id="topupBtn"');
        $this->assertNotFalse($amountPos);
        $this->assertNotFalse($methodPos);
        $this->assertNotFalse($submitPos);
        $this->assertTrue($amountPos < $methodPos);
        $this->assertTrue($methodPos < $submitPos);
    }

    public function test_wallet_reopen_keeps_tbank_checked_when_not_old_input(): void
    {
        $first = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $second = $this->get(route('partner.wallet'))->assertOk()->getContent();

        foreach ([$first, $second] as $html) {
            $this->assertRadioChecked($html, 'walletPayTinkoffSbp');
            $this->assertStringNotContainsString('id="walletPayYookassa"', $html);
        }
    }

    public function test_wallet_with_both_methods_defaults_to_tbank_and_renders_tbank_first(): void
    {
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertStringContainsString('id="walletPayYookassa"', $html);
        $this->assertRadioChecked($html, 'walletPayTinkoffSbp');
        $this->assertRadioNotChecked($html, 'walletPayYookassa');

        $tbankPos = strpos($html, 'id="walletPayTinkoffSbp"');
        $yookassaPos = strpos($html, 'id="walletPayYookassa"');
        $this->assertNotFalse($tbankPos);
        $this->assertNotFalse($yookassaPos);
        $this->assertTrue($tbankPos < $yookassaPos);
    }

    public function test_only_yookassa_permission_defaults_to_yookassa_and_does_not_force_tbank(): void
    {
        $actor = $this->userWithOnlyPermissions([
            'partnerWallet.view',
            'platformPayments.method.yookassa',
        ]);
        $this->actingAs($actor);

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="walletPayTinkoffSbp"', $html);
        $this->assertStringContainsString('id="walletPayYookassa"', $html);
        $this->assertRadioChecked($html, 'walletPayYookassa');
        $this->assertDoesNotMatchRegularExpression('/id="topupBtn"[^>]*disabled/', $html);
    }

    public function test_wallet_without_method_permissions_hides_radios_and_disables_pay(): void
    {
        $actor = $this->userWithOnlyPermissions(['partnerWallet.view']);
        $this->actingAs($actor);

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="walletPayTinkoffSbp"', $html);
        $this->assertStringNotContainsString('id="walletPayYookassa"', $html);
        $this->assertStringContainsString('Нет доступного способа оплаты.', $html);
        $this->assertMatchesRegularExpression('/id="topupBtn"[^>]*disabled/', $html);
    }

    public function test_service_first_open_checks_tbank_and_enables_pay(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $card = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('id="servicePayTinkoffSbp"', $card);
        $this->assertStringNotContainsString('id="servicePayYookassa"', $card);
        $this->assertRadioChecked($card, 'servicePayTinkoffSbp');
        $this->assertDoesNotMatchRegularExpression('/<button type="submit"[^>]*disabled/', $card);
    }

    public function test_service_reopen_keeps_tbank_checked_when_not_old_input(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $first = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );
        $second = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );

        foreach ([$first, $second] as $card) {
            $this->assertRadioChecked($card, 'servicePayTinkoffSbp');
            $this->assertStringNotContainsString('id="servicePayYookassa"', $card);
        }
    }

    public function test_service_with_both_methods_defaults_to_tbank_and_renders_tbank_first(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');

        $card = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );

        $this->assertRadioChecked($card, 'servicePayTinkoffSbp');
        $this->assertRadioNotChecked($card, 'servicePayYookassa');
        $tbankPos = strpos($card, 'id="servicePayTinkoffSbp"');
        $yookassaPos = strpos($card, 'id="servicePayYookassa"');
        $this->assertNotFalse($tbankPos);
        $this->assertNotFalse($yookassaPos);
        $this->assertTrue($tbankPos < $yookassaPos);
    }

    public function test_service_only_yookassa_defaults_to_yookassa_and_does_not_force_tbank(): void
    {
        $actor = $this->userWithOnlyPermissions([
            'servicePayments.view',
            'platformPayments.method.yookassa',
        ]);
        $this->actingAs($actor);

        $card = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('id="servicePayTinkoffSbp"', $card);
        $this->assertStringContainsString('id="servicePayYookassa"', $card);
        $this->assertRadioChecked($card, 'servicePayYookassa');
        $this->assertDoesNotMatchRegularExpression('/<button type="submit"[^>]*disabled/', $card);
    }

    public function test_service_without_method_permissions_shows_alert_and_disables_pay(): void
    {
        $actor = $this->userWithOnlyPermissions(['servicePayments.view']);
        $this->actingAs($actor);

        $card = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('id="servicePayTinkoffSbp"', $card);
        $this->assertStringNotContainsString('id="servicePayYookassa"', $card);
        $this->assertStringContainsString('Нет доступного способа оплаты.', $card);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*disabled/', $card);
    }

    public function test_superadmin_sees_both_methods_with_tbank_default(): void
    {
        $this->asSuperadmin();

        $wallet = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringContainsString('id="walletPayTinkoffSbp"', $wallet);
        $this->assertStringContainsString('id="walletPayYookassa"', $wallet);
        $this->assertRadioChecked($wallet, 'walletPayTinkoffSbp');
        $this->assertRadioNotChecked($wallet, 'walletPayYookassa');

        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $card = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );
        $this->assertStringContainsString('id="servicePayTinkoffSbp"', $card);
        $this->assertStringContainsString('id="servicePayYookassa"', $card);
        $this->assertRadioChecked($card, 'servicePayTinkoffSbp');
        $this->assertRadioNotChecked($card, 'servicePayYookassa');
    }

    public function test_history_tab_opens_without_method_permissions_and_without_radios(): void
    {
        $actor = $this->userWithOnlyPermissions(['servicePayments.view']);
        $this->actingAs($actor);

        $html = $this->get(route('partner.payment.history'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="paymentsTable"', $html);
        $this->assertStringNotContainsString('id="servicePayTinkoffSbp"', $html);
        $this->assertStringNotContainsString('id="walletPayTinkoffSbp"', $html);
    }
}
