<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Доступ к обычному эквайрингу: гость, без прав, с правами, не 500 / не пустой 200.
 *
 * @see /docs/documentation/partner-wallet.html#tbank-sbp
 * @see /docs/documentation/partner-service-payments.html
 * @see /docs/documentation/settings-payment-systems.html#tbank-acquiring
 */
final class TbankAcquiringAccessFeatureTest extends CrmTestCase
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
        $this->partner->forceFill([
            'email' => 'school@example.com',
            'activity_start_date' => '2026-01-01',
        ])->save();
    }

    public function test_guest_is_denied_on_wallet_sbp_service_sbp_and_payment_systems(): void
    {
        Auth::logout();

        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders())->assertStatus(401);

        $this->post(route('partner.wallet.topup'), [
            '_token' => csrf_token(),
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ])->assertRedirect();

        $this->postJson(route('partner.payment.tinkoff.sbp'), [
            'amount' => 2500,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
        ])->assertStatus(401);

        $this->get(route('partner.payment.success'))->assertRedirect();
        $this->postJson(route('payment-systems.store'), [
            'name' => 'tbank_acquiring',
            'terminal_key' => 'x',
        ])->assertStatus(401);
    }

    public function test_admin_without_wallet_permission_gets_403_on_sbp_topup(): void
    {
        $denied = $this->createUserWithoutPermission('partnerWallet.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders())->assertForbidden();
    }

    public function test_admin_without_service_payments_permission_gets_403_on_sbp_pay_and_success(): void
    {
        $denied = $this->createUserWithoutPermission('servicePayments.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('partner.payment.tinkoff.sbp'), [
            'amount' => 2500,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
        ])->assertForbidden();
        $this->get(route('partner.payment.success'))->assertForbidden();
    }

    public function test_admin_with_wallet_permission_gets_422_not_500_when_acquiring_is_off(): void
    {
        $this->asAdmin();

        $response = $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Оплата T‑Bank СБП не подключена на платформе.');
        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_admin_with_service_permission_gets_redirect_with_message_when_acquiring_is_off(): void
    {
        $this->asAdmin();
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $response = $this->from(route('partner.payment.recharge'))
            ->post(route('partner.payment.tinkoff.sbp'), [
                '_token' => csrf_token(),
                'amount' => 2500,
                'days' => 29,
                'partner_id' => $this->partner->id,
                'description' => 'Учет до 200 пользователей',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['message']);
        $this->assertSame(0, (int) \App\Models\PartnerPayment::query()->count());
    }

    public function test_guest_can_post_acquiring_webhook_without_login(): void
    {
        Auth::logout();
        $this->seedGlobalTbankAcquiring();

        $tp = $this->makeAcquiringPayment(['order_id' => 'acq-guest-hook']);
        $response = $this->postJson('/webhooks/tinkoff/acquiring', $this->signedAcquiringPayload([
            'OrderId' => $tp->order_id,
            'Status' => 'CONFIRMED',
            'PaymentId' => '991200',
            'Amount' => 10000,
        ]));

        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_user_with_view_but_without_tbank_method_gets_403_on_acquiring_mutations(): void
    {
        $actor = $this->userWithOnlyPermissions(['settings.paymentSystems.view']);
        $this->actingAs($actor);

        $this->postJson(route('payment-systems.store'), [
            'name' => 'tbank_acquiring',
            'terminal_key' => 'ACQ',
            'token_password' => 'PWD',
        ])->assertForbidden();

        $row = $this->seedGlobalTbankAcquiring();
        $this->getJson(route('payment-systems.show', ['name' => 'tbank_acquiring']))
            ->assertForbidden();
        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $row->id]))
            ->assertForbidden();
    }

    public function test_non_superadmin_with_method_permission_cannot_destroy_acquiring_terminal(): void
    {
        $actor = $this->userWithOnlyPermissions([
            'settings.paymentSystems.view',
            'payment.method.tbankSBP',
        ]);
        $this->actingAs($actor);
        $row = $this->seedGlobalTbankAcquiring();

        $this->deleteJson(route('payment-systems.destroy', ['payment_system' => $row->id]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Удаление глобального терминала T‑Bank доступно только superadmin');

        $this->assertDatabaseHas('payment_systems', ['id' => $row->id]);
    }

    public function test_disallowed_methods_on_service_sbp_do_not_return_500_or_empty_200(): void
    {
        $this->asAdmin();
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $response = $this->call($method, route('partner.payment.tinkoff.sbp'), ['_token' => csrf_token()]);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' tinkoff-sbp');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не должен давать бессмысленный 200');
            $this->assertContains($response->getStatusCode(), [404, 405]);
        }
    }

    public function test_service_yookassa_without_permission_returns_422_on_payment_method(): void
    {
        $this->asAdmin();
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $this->postJson(route('partner.payment.tinkoff.sbp'), [
            'amount' => 2500,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
            'payment_method' => 'yookassa',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Некорректный способ оплаты.');
    }

    public function test_service_without_method_permissions_returns_422_on_payment_method(): void
    {
        $actor = $this->userWithOnlyPermissions(['servicePayments.view']);
        $this->actingAs($actor);

        $this->postJson(route('partner.payment.tinkoff.sbp'), [
            'amount' => 2500,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
            'payment_method' => 'tinkoff_sbp',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Нет доступного способа оплаты.');
    }

    public function test_yookassa_service_route_is_gone(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('createPaymentYookassa'),
            'Маршрут createPaymentYookassa для абонплаты не должен существовать'
        );
    }
}
