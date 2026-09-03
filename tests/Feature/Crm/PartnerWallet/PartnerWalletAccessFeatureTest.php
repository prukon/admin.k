<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * Доступ к /partner-wallet: guest, без права, с правом, SetPartner, не 500 / не пустой 200.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/partner-wallet.html
 */
final class PartnerWalletAccessFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_is_denied_on_wallet_json_and_web(): void
    {
        Auth::logout();

        $this->assertWalletEndpointsStatus(
            $this->walletAuthEndpointsWithoutGateway(),
            [302, 401, 403, 419],
            'Гость JSON',
            true
        );
        $this->assertWalletEndpointsStatus(
            $this->walletAuthEndpointsWithoutGateway(),
            [302, 401, 403, 419],
            'Гость web',
            false
        );
    }

    public function test_manager_without_permission_gets_403_on_wallet_json_and_web(): void
    {
        $denied = $this->createUserWithoutPermission('partnerWallet.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertWalletEndpointsStatus(
            $this->walletAuthEndpointsWithoutGateway(),
            [403],
            'Без partnerWallet.view JSON',
            true
        );
        $this->assertWalletEndpointsStatus(
            $this->walletAuthEndpointsWithoutGateway(),
            [403],
            'Без partnerWallet.view web',
            false
        );
    }

    public function test_trainer_without_permission_gets_403(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->actingAs($trainer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('partner.wallet'))->assertForbidden();
        $this->getJson($this->walletTransactionsUrl())->assertForbidden();
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
        ], $this->walletAjaxHeaders())->assertForbidden();
    }

    public function test_admin_with_base_permission_gets_expected_status_not_500_or_empty_200(): void
    {
        $this->asAdmin();

        $this->assertWalletEndpointsStatus(
            $this->walletAuthEndpointsWithoutGateway(),
            [200, 422],
            'Admin JSON',
            true
        );
        $this->assertWalletEndpointsStatus(
            $this->walletAuthEndpointsWithoutGateway(),
            [200, 302, 422],
            'Admin web',
            false
        );
    }

    public function test_superadmin_can_open_wallet_without_explicit_grants(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('partner.wallet'))->assertOk();
        $this->get(route('partner.wallet.success'))->assertOk();
        $this->getJson($this->walletTransactionsUrl())->assertOk();
    }

    public function test_admin_without_partner_is_logged_out_with_email_error(): void
    {
        $this->asAdmin();
        $this->user->partner_id = null;
        $this->user->save();

        $this->actingAs($this->user);
        $this->withSession([
            'current_partner' => null,
            '2fa:passed' => true,
        ]);

        $response = $this->get(route('partner.wallet'));
        $response->assertStatus(302);
        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => 'Ваша организация недоступна.']);
    }

    public function test_disallowed_methods_on_wallet_do_not_return_500_or_empty_200(): void
    {
        $this->asAdmin();

        foreach (['PATCH', 'DELETE', 'PUT'] as $method) {
            $response = $this->call($method, route('partner.wallet'), ['_token' => csrf_token()]);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' /partner-wallet');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не должен давать бессмысленный 200');
            $this->assertContains($response->getStatusCode(), [404, 405]);
        }
    }

    public function test_public_wallet_webhook_is_not_behind_login(): void
    {
        Auth::logout();

        $response = $this->postJson(route('partner.wallet.webhook'), [
            'event' => 'payment.succeeded',
            'object' => [
                'id' => 'x',
                'amount' => ['value' => '10.00'],
            ],
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [400, 403, 404, 422]);
        $this->assertNotSame('', trim((string) $response->getContent()));
    }
}
