<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * UX-баг: QR кошелька/абонплаты был за can:payment.method.tbankSBP — школа без права СБП родителей
 * получала 403 на странице оплаты своей абонплаты. Канал acquiring открыт по partnerWallet.view /
 * servicePayments.view; мультисплит по-прежнему требует tbankSBP.
 */
final class TbankAcquiringQrAccessFeatureTest extends CrmTestCase
{
    use TbankAcquiringTestHelpers;

    private const PAYMENT_ID = '996001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->seedGlobalTbankAcquiring();
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_MS',
            'token_password' => 'PWD_MS',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2C_PWD',
        ]);
    }

    public function test_admin_without_tbank_sbp_permission_can_open_own_acquiring_qr(): void
    {
        $actor = $this->userWithOnlyPermissions(['partnerWallet.view']);
        $this->actingAs($actor);

        $this->makeAcquiringPayment([
            'tinkoff_payment_id' => self::PAYMENT_ID,
            'payload' => [
                'init_data' => ['scope' => 'partner_wallet_topup'],
                'success_url' => url('/partner-wallet/success'),
            ],
        ]);

        $this->get(route('tinkoff.qr', self::PAYMENT_ID))
            ->assertOk()
            ->assertSee('Оплата через СБП', false)
            ->assertSee('К кошельку', false);
    }

    public function test_admin_without_tbank_sbp_permission_still_cannot_open_multisplit_qr(): void
    {
        $denied = $this->createUserWithoutPermission('payment.method.tbankSBP', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'ms-denied',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_MULTISPLIT,
            'status' => 'FORM',
            'tinkoff_payment_id' => '996002',
        ]);

        $this->get(route('tinkoff.qr', '996002'))->assertForbidden();
    }

    public function test_user_with_only_service_payments_view_can_open_service_acquiring_qr(): void
    {
        $actor = $this->userWithOnlyPermissions(['servicePayments.view']);
        $this->actingAs($actor);

        $this->makeAcquiringPayment([
            'tinkoff_payment_id' => '996003',
            'payload' => [
                'init_data' => ['scope' => 'partner_service_payment'],
                'success_url' => url('/partner-payment/success'),
            ],
        ]);

        $this->get(route('tinkoff.qr', '996003'))
            ->assertOk()
            ->assertSee('К оплате сервиса', false);
    }

    public function test_user_without_wallet_service_or_tbank_sbp_cannot_open_acquiring_qr(): void
    {
        $actor = $this->userWithOnlyPermissions(['users.view']);
        $this->actingAs($actor);

        $this->makeAcquiringPayment(['tinkoff_payment_id' => '996004']);

        $this->get(route('tinkoff.qr', '996004'))->assertForbidden();
        $this->getJson('/tinkoff/qr/996004/json')->assertForbidden();
    }

    public function test_acquiring_get_qr_signs_with_acquiring_terminal_not_multisplit(): void
    {
        $this->asAdmin();
        $this->makeAcquiringPayment(['tinkoff_payment_id' => '996005']);

        $sentKeys = [];
        Http::fake(function ($request) use (&$sentKeys) {
            if (str_contains($request->url(), '/v2/GetQr')) {
                $sentKeys[] = $request->data()['TerminalKey'] ?? null;

                return Http::response(['Success' => true, 'Data' => 'qr'], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $this->getJson('/tinkoff/qr/996005/json')
            ->assertOk()
            ->assertJsonPath('Success', true);

        $this->assertSame(['TERM_ACQ'], $sentKeys);
        $this->assertNotContains('TERM_MS', $sentKeys);
    }

    public function test_guest_is_redirected_from_acquiring_qr(): void
    {
        Auth::logout();
        $this->makeAcquiringPayment(['tinkoff_payment_id' => '996006']);

        $this->get(route('tinkoff.qr', '996006'))->assertRedirect(route('login'));
    }

    public function test_foreign_partner_acquiring_qr_returns_404(): void
    {
        $this->asAdmin();
        $this->makeAcquiringPayment([
            'partner_id' => $this->foreignPartner->id,
            'tinkoff_payment_id' => '996007',
        ]);

        $this->get(route('tinkoff.qr', '996007'))->assertNotFound();
    }
}
