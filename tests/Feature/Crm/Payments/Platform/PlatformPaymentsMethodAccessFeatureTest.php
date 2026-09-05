<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Platform;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\Platform\Concerns\PlatformPaymentsMethodTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Доступ к способам оплаты платформы: гость, без права страницы, витринные payment.method.*,
 * право способа без права страницы, не 500 / не пустой 200.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/partner-wallet.html#tbank-sbp
 */
final class PlatformPaymentsMethodAccessFeatureTest extends CrmTestCase
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
        $this->partner->forceFill([
            'email' => 'school@example.com',
            'activity_start_date' => '2026-01-01',
        ])->save();
    }

    public function test_guest_is_redirected_or_401_on_wallet_and_service_payment_endpoints(): void
    {
        Auth::logout();

        $this->assertWalletEndpointsStatus(
            $this->platformPaymentSurfaceEndpointsWithoutGateway(),
            [302, 401, 403, 419],
            'Гость JSON',
            true
        );
        $this->assertWalletEndpointsStatus(
            $this->platformPaymentSurfaceEndpointsWithoutGateway(),
            [302, 401, 403, 419],
            'Гость web',
            false
        );
    }

    public function test_user_without_wallet_view_gets_403_even_with_platform_tbank_permission(): void
    {
        $actor = $this->userWithOnlyPermissions(['platformPayments.method.tbankSbp']);
        $this->actingAs($actor);

        $this->get(route('partner.wallet'))->assertForbidden();
        $this->postJson(
            route('partner.wallet.topup'),
            $this->walletTopupPayload(),
            $this->acquiringAjaxHeaders()
        )->assertForbidden();
        $this->post(route('partner.wallet.topup'), array_merge(
            ['_token' => csrf_token()],
            $this->walletTopupPayload()
        ))->assertForbidden();
    }

    public function test_user_without_service_view_gets_403_even_with_platform_tbank_permission(): void
    {
        $actor = $this->userWithOnlyPermissions(['platformPayments.method.tbankSbp']);
        $this->actingAs($actor);

        $this->get(route('partner.payment.recharge'))->assertForbidden();
        $this->get(route('partner.payment.history'))->assertForbidden();
        $this->postJson(route('partner.payment.tinkoff.sbp'), $this->servicePayPayload())
            ->assertForbidden();
        $this->post(route('partner.payment.tinkoff.sbp'), array_merge(
            ['_token' => csrf_token()],
            $this->servicePayPayload()
        ))->assertForbidden();
    }

    public function test_marketplace_payment_method_rights_do_not_unlock_wallet_platform_methods(): void
    {
        $actor = $this->actorWithMarketplaceMethodsAndWalletView();
        $this->actingAs($actor);

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="walletPayTinkoffSbp"', $html);
        $this->assertStringNotContainsString('id="walletPayYookassa"', $html);
        $this->assertStringContainsString('Нет доступного способа оплаты.', $html);
        $this->assertMatchesRegularExpression('/id="topupBtn"[^>]*disabled/', $html);

        $this->postJson(
            route('partner.wallet.topup'),
            $this->walletTopupPayload(['payment_method' => 'tinkoff_sbp']),
            $this->acquiringAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Нет доступного способа оплаты.');

        $this->postJson(
            route('partner.wallet.topup'),
            $this->walletTopupPayload(['payment_method' => 'yookassa']),
            $this->acquiringAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_parent_user_role_with_wallet_view_still_cannot_use_marketplace_sbp_on_platform(): void
    {
        $actor = $this->createUserWithRole('user');
        $this->grantNamedPermission($actor, 'partnerWallet.view');
        $this->actingAs($actor);

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="walletPayTinkoffSbp"', $html);
        $this->assertStringNotContainsString('id="walletPayYookassa"', $html);

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

    public function test_marketplace_payment_method_rights_do_not_unlock_service_platform_methods(): void
    {
        $actor = $this->actorWithMarketplaceMethodsAndServiceView();
        $this->actingAs($actor);

        $card = $this->serviceRechargeCardHtml(
            $this->get(route('partner.payment.recharge'))->assertOk()->getContent()
        );
        $this->assertStringNotContainsString('id="servicePayTinkoffSbp"', $card);
        $this->assertStringNotContainsString('id="servicePayYookassa"', $card);
        $this->assertStringContainsString('Нет доступного способа оплаты.', $card);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*disabled/', $card);

        $this->postJson(route('partner.payment.tinkoff.sbp'), $this->servicePayPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Нет доступного способа оплаты.');

        $this->assertNoPartnerPayment();
    }

    public function test_admin_with_wallet_and_tbank_permission_can_open_wallet_page(): void
    {
        $this->asAdmin();

        $response = $this->get(route('partner.wallet'))->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringContainsString('id="walletPayTinkoffSbp"', $response->getContent());
    }

    public function test_disallowed_http_methods_on_wallet_topup_do_not_return_500_or_empty_200(): void
    {
        $this->asAdmin();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $response = $this->call($method, route('partner.wallet.topup'), ['_token' => csrf_token()]);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' partner.wallet.topup');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не должен давать бессмысленный 200');
            $this->assertContains($response->getStatusCode(), [404, 405]);
        }
    }

    public function test_disallowed_http_methods_on_service_pay_do_not_return_500_or_empty_200(): void
    {
        $this->asAdmin();
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $response = $this->call($method, route('partner.payment.tinkoff.sbp'), ['_token' => csrf_token()]);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' partner.payment.tinkoff.sbp');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не должен давать бессмысленный 200');
            $this->assertContains($response->getStatusCode(), [404, 405]);
        }
    }
}
