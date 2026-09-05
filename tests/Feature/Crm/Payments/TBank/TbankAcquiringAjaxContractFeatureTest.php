<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\PartnerPayment;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * AJAX JSON-контракт кошелька СБП, абонплаты и карточки tbank_acquiring: 200 / 422 errors[field].
 *
 * @see /docs/documentation/partner-wallet.html#tbank-sbp
 */
final class TbankAcquiringAjaxContractFeatureTest extends CrmTestCase
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

    public function test_wallet_sbp_ajax_returns_ok_and_qr_redirect(): void
    {
        $this->fakeAcquiringInit('993001');

        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'redirect']);
    }

    public function test_wallet_sbp_ajax_amount_above_max_returns_422_amount_error(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 1_000_001,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Сумма для СБП не должна превышать 1 000 000 ₽.')
            ->assertJsonMissingValidationErrors(['payment_method']);

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_wallet_yookassa_amount_five_is_not_rejected_as_sbp_minimum(): void
    {
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');
        $this->useDummyYookassaCredentials();

        $response = $this->postJson(route('partner.wallet.topup'), [
            'amount' => 5,
            'partner_id' => $this->partner->id,
            'payment_method' => 'yookassa',
        ], $this->acquiringAjaxHeaders());

        $this->assertNotSame(422, $response->getStatusCode(), '5 ₽ для ЮKassa не должны падать на минимум СБП 10 ₽');
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function test_omitted_payment_method_applies_sbp_minimum_when_tbank_is_default(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 5,
            'partner_id' => $this->partner->id,
        ], $this->acquiringAjaxHeaders())
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

        $response = $this->postJson(route('partner.wallet.topup'), [
            'amount' => 5,
            'partner_id' => $this->partner->id,
        ], $this->acquiringAjaxHeaders());

        $this->assertNotSame(422, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function test_yookassa_without_permission_returns_422_on_payment_method(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'yookassa',
        ], $this->acquiringAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Некорректный способ оплаты.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_wallet_without_method_permissions_returns_422_on_payment_method(): void
    {
        $actor = $this->userWithOnlyPermissions(['partnerWallet.view']);
        $this->actingAs($actor);

        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Нет доступного способа оплаты.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_invalid_payment_method_returns_422_on_payment_method_field(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'robokassa',
        ], $this->acquiringAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'Некорректный способ оплаты.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_wallet_sbp_without_school_email_returns_422_on_payment_method(): void
    {
        $this->partner->forceFill(['email' => ''])->save();

        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method'])
            ->assertJsonPath('errors.payment_method.0', 'У школы не указан email. Он нужен для чека.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_service_sbp_json_amount_below_ten_returns_422(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');

        $this->postJson(route('partner.payment.tinkoff.sbp'), [
            'amount' => 9.99,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Сумма для СБП должна быть не меньше 10 ₽.');

        $this->assertSame(0, (int) PartnerPayment::query()->count());
    }

    public function test_service_yookassa_amount_five_is_not_rejected_as_sbp_minimum(): void
    {
        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $this->grantNamedPermission($this->user, 'platformPayments.method.yookassa');
        $this->useDummyYookassaCredentials();

        $response = $this->from(route('partner.payment.recharge'))
            ->post(route('partner.payment.tinkoff.sbp'), [
                '_token' => csrf_token(),
                'amount' => 5,
                'days' => 29,
                'partner_id' => $this->partner->id,
                'description' => 'Учет до 200 пользователей',
                'payment_method' => 'yookassa',
            ]);

        $this->assertNotSame(422, $response->getStatusCode(), '5 ₽ для ЮKassa абонплаты не должны падать на минимум СБП');
        $this->assertNotSame(
            'Сумма для СБП должна быть не меньше 10 ₽.',
            (string) optional(session('errors'))->first('amount')
        );
        $this->assertContains($response->getStatusCode(), [200, 302, 500]);
    }

    public function test_store_acquiring_ajax_ignores_e2c_keys(): void
    {
        $this->grantNamedPermission($this->user, 'settings.paymentSystems.view');
        $this->grantNamedPermission($this->user, 'payment.method.tbankCard');

        $this->postJson(route('payment-systems.store'), [
            'name' => 'tbank_acquiring',
            'terminal_key' => 'ACQ-ONLY',
            'token_password' => 'ACQ-PWD',
            'e2c_terminal_key' => 'SHOULD-NOT-SAVE',
            'e2c_token_password' => 'SHOULD-NOT-SAVE',
            'is_enabled' => 1,
        ])->assertOk()->assertJsonPath('status', 'success');

        $row = PaymentSystem::globalTbankAcquiring();
        $this->assertNotNull($row);
        $this->assertSame('ACQ-ONLY', $row->settings['terminal_key'] ?? null);
        $this->assertArrayNotHasKey('e2c_terminal_key', $row->settings);
        $this->assertArrayNotHasKey('e2c_token_password', $row->settings);
        $this->assertTrue($row->is_connected);
    }

    public function test_show_acquiring_ajax_returns_settings_without_e2c(): void
    {
        $this->grantNamedPermission($this->user, 'settings.paymentSystems.view');
        $this->grantNamedPermission($this->user, 'payment.method.tbankSBP');

        $response = $this->getJson(route('payment-systems.show', ['name' => 'tbank_acquiring']))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.terminal_key', 'TERM_ACQ');

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('e2c_terminal_key', $data);
        $this->assertArrayNotHasKey('e2c_token_password', $data);
    }

    public function test_wallet_sbp_init_failure_returns_json_500_with_message_not_empty_200(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => false,
                    'Message' => 'Init failed',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $response = $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $this->acquiringAjaxHeaders());

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(500)
            ->assertJsonPath('ok', false);
        $this->assertNotSame('', (string) $response->json('message'));
        $this->assertNotNull($this->latestWalletTx());
        $this->assertSame('pending', $this->latestWalletTx()->status);
        $this->assertSame(0, (int) TinkoffPayment::query()->whereNotNull('tinkoff_payment_id')->count());
    }
}
