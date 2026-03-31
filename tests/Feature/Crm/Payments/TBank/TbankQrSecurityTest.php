<?php

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankQrSecurityTest extends CrmTestCase
{
    private function grantTbankPaymentPermissionForCurrentUser(): void
    {
        $permId = $this->permissionId('payment.method.tbankSBP');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantTbankPaymentPermissionForForeignUser(): void
    {
        $permId = $this->permissionId('payment.method.tbankSBP');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->foreignPartner->id,
            'role_id'       => $this->foreignUser->role_id,
            'permission_id' => $permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_sbp_create_ignores_partner_id_from_request_and_uses_current_partner_keys(): void
    {
        $this->grantTbankPaymentPermissionForCurrentUser();

        $this->partner->tinkoff_partner_id = 'SHOP-CURRENT';
        $this->partner->save();

        // Настроим разные ключи для текущего и чужого партнёра
        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                'terminal_key' => 'TERM_CURRENT',
                'token_password' => 'PWD_CURRENT',
                // PaymentSystem::is_connected вычисляется из наличия и e2c ключей тоже
                'e2c_terminal_key' => 'E2C_TERM_CURRENT',
                'e2c_token_password' => 'E2C_PWD_CURRENT',
            ],
        ]);

        PaymentSystem::create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                'terminal_key' => 'TERM_FOREIGN',
                'token_password' => 'PWD_FOREIGN',
                'e2c_terminal_key' => 'E2C_TERM_FOREIGN',
                'e2c_token_password' => 'E2C_PWD_FOREIGN',
            ],
        ]);

        $sentTerminalKeys = [];

        Http::fake(function ($request) use (&$sentTerminalKeys) {
            if (str_contains($request->url(), '/v2/Init')) {
                $sentTerminalKeys[] = $request->data()['TerminalKey'] ?? null;
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 777,
                    'PaymentURL' => 'https://example.test/pay',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2024-01-01', 10, false)
            ->create();

        // Пытаемся подложить чужой partner_id (должен игнорироваться — партнёр только из контекста)
        $resp = $this->post(route('payment.tinkoff.sbp'), [
            'partner_id' => $this->foreignPartner->id,
            'outSum' => '10.00',
            'formatedPaymentDate' => '2024-01-01',
        ]);

        $resp->assertRedirect(route('tinkoff.qr', 777));

        $this->assertSame(['TERM_CURRENT'], $sentTerminalKeys);

        $tp = TinkoffPayment::where('tinkoff_payment_id', '777')->first();
        $this->assertNotNull($tp);
        $this->assertSame($this->partner->id, (int) $tp->partner_id);
    }

    public function test_qr_endpoints_return_404_for_foreign_partner_payment_id(): void
    {
        $this->grantTbankPaymentPermissionForCurrentUser();
        $this->grantTbankPaymentPermissionForForeignUser();

        // Платёж партнёра A
        $tp = TinkoffPayment::create([
            'order_id' => 'order-qr-1',
            'partner_id' => $this->partner->id,
            'amount' => 1000,
            'method' => 'sbp',
            'status' => 'FORM',
            'tinkoff_payment_id' => '999001',
        ]);

        // Под чужим партнёром пытаемся открыть/получить QR и state
        $this->asForeignUser();

        $this->get(route('tinkoff.qr', $tp->tinkoff_payment_id))->assertStatus(404);
        $this->get('/tinkoff/qr/' . $tp->tinkoff_payment_id . '/json')->assertStatus(404);
        $this->get(route('tinkoff.qr.payload', $tp->tinkoff_payment_id))->assertStatus(404);
        $this->get('/tinkoff/qr/' . $tp->tinkoff_payment_id . '/state')->assertStatus(404);
    }
}

