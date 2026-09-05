<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\TinkoffPayment;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Разметка и правила «если X, то по умолчанию Y»: кошелёк, абонплата, карточка ключей, success, QR.
 *
 * @see /docs/documentation/partner-wallet.html#tbank-sbp
 */
final class TbankAcquiringUxFeatureTest extends CrmTestCase
{
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

    public function test_wallet_first_open_checks_tbank_and_hides_yookassa_for_admin(): void
    {
        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertStringContainsString('id="walletPayTinkoffSbp"', $html);
        $this->assertStringContainsString('name="payment_method"', $html);
        $this->assertStringContainsString('value="tinkoff_sbp"', $html);
        $this->assertStringContainsString('data-error-for="payment_method"', $html);
        $this->assertStringNotContainsString('id="walletPayYookassa"', $html);
        $this->assertStringNotContainsString('value="yookassa"', $html);

        $this->assertMatchesRegularExpression(
            '/id="walletPayTinkoffSbp"[^>]*checked/',
            $html
        );

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
            $this->assertMatchesRegularExpression('/id="walletPayTinkoffSbp"[^>]*checked/', $html);
            $this->assertStringNotContainsString('id="walletPayYookassa"', $html);
        }
    }

    public function test_wallet_with_both_methods_defaults_to_tbank(): void
    {
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertStringContainsString('id="walletPayYookassa"', $html);
        $this->assertStringContainsString('id="walletPayTinkoffSbp"', $html);
        $this->assertMatchesRegularExpression('/id="walletPayTinkoffSbp"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="walletPayYookassa"[^>]*checked/', $html);

        $tbankPos = strpos($html, 'id="walletPayTinkoffSbp"');
        $yookassaPos = strpos($html, 'id="walletPayYookassa"');
        $this->assertNotFalse($tbankPos);
        $this->assertNotFalse($yookassaPos);
        $this->assertTrue($tbankPos < $yookassaPos);
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

    public function test_service_recharge_form_posts_to_tinkoff_sbp_and_has_tbank_radio(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $html = $this->get(route('partner.payment.recharge'))->assertOk()->getContent();

        $this->assertStringContainsString('/payment/service/tinkoff-sbp', $html);
        $cardPos = strpos($html, 'Оплата сервиса');
        $this->assertNotFalse($cardPos);
        $formEnd = strpos($html, '</form>', $cardPos);
        $this->assertNotFalse($formEnd);
        $card = substr($html, $cardPos, $formEnd - $cardPos);
        $this->assertStringContainsString('name="amount" value="2500.00"', $card);
        $this->assertStringContainsString('name="days" value="29"', $card);
        $this->assertStringNotContainsString('createPaymentYookassa', $card);
        $this->assertStringNotContainsString('ЮKassa', $card);
        $this->assertStringNotContainsString('yookassa', $card);
        $this->assertStringContainsString('name="payment_method"', $card);
        $this->assertStringContainsString('value="tinkoff_sbp"', $card);
        $this->assertStringContainsString('id="servicePayTinkoffSbp"', $card);
        $this->assertMatchesRegularExpression('/id="servicePayTinkoffSbp"[^>]*checked/', $card);
        $this->assertStringContainsString('2 500', $card);
        $this->assertStringContainsString('data-error-for="amount"', $card);
        $this->assertStringContainsString('data-error-for="partner_id"', $card);
        $this->assertStringContainsString('data-error-for="payment_method"', $card);
    }

    public function test_service_recharge_with_yookassa_permission_shows_both_radios_tbank_default(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');

        $html = $this->get(route('partner.payment.recharge'))->assertOk()->getContent();

        $cardPos = strpos($html, 'Оплата сервиса');
        $this->assertNotFalse($cardPos);
        $formEnd = strpos($html, '</form>', $cardPos);
        $this->assertNotFalse($formEnd);
        $card = substr($html, $cardPos, $formEnd - $cardPos);

        $this->assertStringContainsString('ЮKassa', $card);
        $this->assertStringContainsString('id="servicePayYookassa"', $card);
        $this->assertStringContainsString('id="servicePayTinkoffSbp"', $card);
        $this->assertMatchesRegularExpression('/id="servicePayTinkoffSbp"[^>]*checked/', $card);
        $this->assertDoesNotMatchRegularExpression('/id="servicePayYookassa"[^>]*checked/', $card);
        $this->assertStringNotContainsString('createPaymentYookassa', $card);
    }

    public function test_service_payment_success_page_points_back_to_service_history_not_wallet(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $html = $this->get(route('partner.payment.success'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Платёж обрабатывается', $html);
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/partner-payment\/history" class="btn btn-primary">К истории оплаты сервиса<\/a>/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a href="[^"]*\/partner-wallet"[^>]*>Вернуться в кошелёк<\/a>/',
            $html
        );
    }

    public function test_wallet_success_page_still_points_back_to_wallet(): void
    {
        $html = $this->get(route('partner.wallet.success'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Вернуться в кошелёк', $html);
        $this->assertStringContainsString('href="/partner-wallet"', $html);
        $this->assertStringNotContainsString('/partner-payment/history', $html);
    }

    public function test_payment_systems_page_shows_acquiring_card_and_standard_modal_without_e2c(): void
    {
        $this->grantNamedPermission($this->user, 'settings.paymentSystems.view');
        $this->grantNamedPermission($this->user, 'payment.method.tbankSBP');

        $html = $this->get(route('admin.setting.paymentSystem'))->assertOk()->getContent();

        $this->assertStringContainsString('T‑Банк (эквайринг)', $html);
        $this->assertStringContainsString('Обычный эквайринг платформы', $html);
        $this->assertStringContainsString('id="modalTbankAcquiring"', $html);
        $this->assertStringContainsString('id="tbankAcquiringForm"', $html);
        $this->assertStringContainsString('id="acquiring_terminal_key"', $html);
        $this->assertStringContainsString('id="acquiring_token_password"', $html);
        $this->assertStringContainsString('name="name" value="tbank_acquiring"', $html);
        $this->assertStringContainsString('id="tbank_acquiring_is_enabled"', $html);
        $this->assertStringContainsString('Эквайринг включён на платформе', $html);

        $modalPos = strpos($html, 'id="modalTbankAcquiring"');
        $this->assertNotFalse($modalPos);
        $modalChunk = substr($html, $modalPos, 2200);
        $this->assertStringContainsString('modal-dialog', $modalChunk);
        $this->assertStringNotContainsString('modal-fullscreen', $modalChunk);
        $this->assertStringNotContainsString('e2c_terminal_key', $modalChunk);
        $this->assertStringNotContainsString('e2c_token_password', $modalChunk);
        $this->assertStringNotContainsString('TerminalKey (выплаты партнёру)', $modalChunk);
    }

    public function test_payment_systems_page_hides_acquiring_card_without_tbank_method_permission(): void
    {
        $actor = $this->userWithOnlyPermissions([
            'settings.paymentSystems.view',
            'payment.method.robokassa',
        ]);
        $this->actingAs($actor);

        $html = $this->get(route('admin.setting.paymentSystem'))->assertOk()->getContent();

        $this->assertStringNotContainsString('T‑Банк (эквайринг)', $html);
        $this->assertStringNotContainsString('id="modalTbankAcquiring"', $html);
        $this->assertStringContainsString('Робокасса', $html);
    }

    public function test_wallet_acquiring_qr_page_links_back_to_wallet_not_parent_payment_choice(): void
    {
        $this->seedGlobalTbankAcquiring();
        $tp = $this->makeAcquiringPayment([
            'tinkoff_payment_id' => '995001',
            'payload' => [
                'init_data' => ['scope' => 'partner_wallet_topup'],
                'success_url' => url('/partner-wallet/success'),
            ],
        ]);

        $html = $this->get(route('tinkoff.qr', $tp->tinkoff_payment_id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('К кошельку', $html);
        $this->assertStringContainsString('/partner-wallet', $html);
        $this->assertStringNotContainsString('К выбору способа оплаты', $html);
        $this->assertStringNotContainsString('К оплате сервиса', $html);
    }

    public function test_service_acquiring_qr_page_links_back_to_recharge_not_wallet(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->seedGlobalTbankAcquiring();
        $tp = $this->makeAcquiringPayment([
            'tinkoff_payment_id' => '995002',
            'payload' => [
                'init_data' => ['scope' => 'partner_service_payment'],
                'success_url' => url('/partner-payment/success'),
            ],
        ]);

        $html = $this->get(route('tinkoff.qr', $tp->tinkoff_payment_id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('К оплате сервиса', $html);
        $this->assertStringContainsString('/partner-payment/recharge', $html);
        $this->assertStringNotContainsString('К кошельку', $html);
        $this->assertStringNotContainsString('К выбору способа оплаты', $html);
    }

    public function test_multisplit_qr_does_not_show_acquiring_back_link(): void
    {
        $this->grantNamedPermission($this->user, 'payment.method.tbankSBP');
        $this->seedGlobalTbank();

        TinkoffPayment::query()->create([
            'order_id' => 'ms-qr-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_MULTISPLIT,
            'status' => 'FORM',
            'tinkoff_payment_id' => '995003',
        ]);

        $html = $this->get(route('tinkoff.qr', '995003'))->assertOk()->getContent();

        $this->assertStringContainsString('В личный кабинет', $html);
        $this->assertStringNotContainsString('К кошельку', $html);
        $this->assertStringNotContainsString('К оплате сервиса', $html);
    }
}
