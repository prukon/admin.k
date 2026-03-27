<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Страница СБП QR, AJAX GetQr/GetState и контроль доступа (can:payment.method.tbankSBP).
 */
class TbankQrFeatureTest extends CrmTestCase
{
    private const PAYMENT_ID = '8207892986';

    private const PNG_1X1_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmWQQAAAABJRU5ErkJggg==';

    /** Минимальный SVG в base64 (как в ответе GetQr с DataType=IMAGE). */
    private const SVG_1X1_BASE64 = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxIiBoZWlnaHQ9IjEiLz4=';

    private function grantTbankPaymentPermissionForCurrentUser(): void
    {
        $permId = $this->permissionId('payment.method.tbankSBP');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedConnectedTbankForPartner(int $partnerId): void
    {
        PaymentSystem::create([
            'partner_id' => $partnerId,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                'terminal_key' => 'TERM_QR_TEST',
                'token_password' => 'PWD_QR_TEST',
                'e2c_terminal_key' => 'E2C_TERM',
                'e2c_token_password' => 'E2C_PWD',
            ],
        ]);
    }

    private function createTinkoffPaymentForCurrentPartner(): TinkoffPayment
    {
        return TinkoffPayment::create([
            'order_id' => 'order-qr-feature-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'status' => 'FORM',
            'tinkoff_payment_id' => self::PAYMENT_ID,
        ]);
    }

    private function fakeTinkoffQrAndState(?string $qrData = null, string $status = 'FORM_SHOWED'): void
    {
        $qrData ??= self::SVG_1X1_BASE64;

        Http::fake(function ($request) use ($qrData, $status) {
            $url = $request->url();
            if (str_contains($url, '/v2/GetQr')) {
                $this->assertSame(
                    'IMAGE',
                    $request->data()['DataType'] ?? null,
                    'GetQr должен запрашиваться с DataType=IMAGE (SVG), иначе в Data приходит URL страницы НСПК, а не картинка.'
                );

                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Message' => 'OK',
                    'Data' => $qrData,
                ], 200);
            }
            if (str_contains($url, '/v2/GetState')) {
                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Message' => 'OK',
                    'Status' => $status,
                    'PaymentId' => self::PAYMENT_ID,
                    'Amount' => 10000,
                ], 200);
            }

            return Http::response(['Success' => false, 'Message' => 'unexpected URL: ' . $url], 500);
        });
    }

    public function test_authorized_user_with_tbank_permission_gets_200_on_qr_page_json_and_state(): void
    {
        $this->grantTbankPaymentPermissionForCurrentUser();
        $this->seedConnectedTbankForPartner($this->partner->id);
        $this->createTinkoffPaymentForCurrentPartner();
        $this->fakeTinkoffQrAndState();

        $show = $this->get(route('tinkoff.qr', self::PAYMENT_ID));
        $show->assertOk();
        $show->assertSee('Оплата через СБП', false);
        $show->assertSee('100.00', false);
        $show->assertSee('appendQrToBox', false);
        $show->assertSee('data:image/svg+xml', false);
        $show->assertSee('qr.nspk.ru', false);
        $show->assertSee('sandbox', false);

        $json = $this->getJson('/tinkoff/qr/' . self::PAYMENT_ID . '/json');
        $json->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonPath('Data', self::SVG_1X1_BASE64);

        $state = $this->getJson(route('tinkoff.qr.state', self::PAYMENT_ID));
        $state->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonPath('Status', 'FORM_SHOWED');
    }

    public function test_get_qr_json_returns_base64_png_data_unchanged_in_payload(): void
    {
        $this->grantTbankPaymentPermissionForCurrentUser();
        $this->seedConnectedTbankForPartner($this->partner->id);
        $this->createTinkoffPaymentForCurrentPartner();
        $this->fakeTinkoffQrAndState(self::PNG_1X1_BASE64);

        $this->getJson('/tinkoff/qr/' . self::PAYMENT_ID . '/json')
            ->assertOk()
            ->assertJsonPath('Success', true)
            ->assertJsonPath('Data', self::PNG_1X1_BASE64);
    }

    public function test_get_qr_json_returns_data_uri_in_data_field_as_is(): void
    {
        $this->grantTbankPaymentPermissionForCurrentUser();
        $this->seedConnectedTbankForPartner($this->partner->id);
        $this->createTinkoffPaymentForCurrentPartner();
        $dataUri = 'data:image/png;base64,' . self::PNG_1X1_BASE64;
        $this->fakeTinkoffQrAndState($dataUri);

        $this->getJson('/tinkoff/qr/' . self::PAYMENT_ID . '/json')
            ->assertOk()
            ->assertJsonPath('Data', $dataUri);
    }

    public function test_user_without_tbank_permission_cannot_access_qr_routes(): void
    {
        $deniedUser = $this->createUserWithoutPermission('payment.method.tbankSBP', $this->partner);
        $this->seedConnectedTbankForPartner($this->partner->id);
        $this->createTinkoffPaymentForCurrentPartner();

        $this->actingAs($deniedUser);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('tinkoff.qr', self::PAYMENT_ID))->assertForbidden();
        $this->get('/tinkoff/qr/' . self::PAYMENT_ID . '/json')->assertForbidden();
        $this->get(route('tinkoff.qr.state', self::PAYMENT_ID))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_qr_routes(): void
    {
        Auth::logout();

        $this->get(route('tinkoff.qr', self::PAYMENT_ID))->assertRedirect(route('login'));
        $this->get('/tinkoff/qr/' . self::PAYMENT_ID . '/json')->assertRedirect(route('login'));
        $this->get(route('tinkoff.qr.state', self::PAYMENT_ID))->assertRedirect(route('login'));
    }

    public function test_get_qr_json_propagates_bank_error_to_client(): void
    {
        $this->grantTbankPaymentPermissionForCurrentUser();
        $this->seedConnectedTbankForPartner($this->partner->id);
        $this->createTinkoffPaymentForCurrentPartner();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/GetQr')) {
                return Http::response([
                    'Success' => false,
                    'ErrorCode' => '999',
                    'Message' => 'QR недоступен',
                ], 200);
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return Http::response(['Success' => true, 'Status' => 'NEW'], 200);
            }

            return Http::response([], 500);
        });

        $this->getJson('/tinkoff/qr/' . self::PAYMENT_ID . '/json')
            ->assertOk()
            ->assertJsonPath('Success', false)
            ->assertJsonPath('Message', 'QR недоступен');
    }
}
