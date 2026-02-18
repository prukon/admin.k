<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

class TbankPayoutManualTest extends CrmTestCase
{
    private function grantPayoutsManagePermissionForCurrentUser(): void
    {
        $permId = $this->permissionId('tbank.payouts.manage');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function seedE2cKeysForPartner(Partner $partner): void
    {
        PaymentSystem::create([
            'partner_id' => $partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'settings' => [
                // чтобы $ps->is_connected === true
                'terminal_key' => 'TERM_PAY',
                'token_password' => 'PWD_PAY',
                'e2c_terminal_key' => 'TERM_E2C',
                'e2c_token_password' => 'PWD_E2C',
            ],
        ]);
    }

    public function test_pay_now_creates_payout_and_calls_e2c_until_completed(): void
    {
        $this->grantPayoutsManagePermissionForCurrentUser();

        $this->partner->tinkoff_partner_id = 'SHOP-1';
        $this->partner->save();
        $this->seedE2cKeysForPartner($this->partner);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-payout-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-1',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/e2c/v2/Init')) {
                return Http::response(['Success' => true, 'PaymentId' => 9001], 200);
            }
            if (str_contains($url, '/e2c/v2/Payment')) {
                return Http::response(['Success' => true, 'Status' => 'CREDIT_CHECKING'], 200);
            }
            if (str_contains($url, '/e2c/v2/GetState')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            return Http::response(['Success' => true], 200);
        });

        $this->post('/tinkoff/payouts/deal-1/pay-now')
            ->assertStatus(302)
            ->assertSessionHas('status', 'Выплата запущена');

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame('COMPLETED', (string) $payout->status);
        $this->assertNotNull($payout->completed_at);
        $this->assertNotNull($payout->tinkoff_payout_payment_id);
    }

    public function test_pay_now_marks_payout_rejected_when_e2c_init_fails(): void
    {
        $this->grantPayoutsManagePermissionForCurrentUser();

        $this->partner->tinkoff_partner_id = 'SHOP-1';
        $this->partner->save();
        $this->seedE2cKeysForPartner($this->partner);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-payout-2',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-2',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/e2c/v2/Init')) {
                return Http::response(['Success' => false, 'ErrorCode' => '9999'], 200);
            }
            return Http::response(['Success' => true], 200);
        });

        $this->post('/tinkoff/payouts/deal-2/pay-now')->assertStatus(302);

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
        $this->assertSame('REJECTED', (string) $payout->status);
        $this->assertNotNull($payout->completed_at);
    }

    public function test_delay_creates_scheduled_payout_and_does_not_call_bank(): void
    {
        $this->grantPayoutsManagePermissionForCurrentUser();

        $this->partner->tinkoff_partner_id = 'SHOP-1';
        $this->partner->save();
        $this->seedE2cKeysForPartner($this->partner);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-payout-3',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-3',
        ]);

        Http::fake();

        $this->post('/tinkoff/payouts/deal-3/delay', [
            'run_at' => now()->addDay()->format('Y-m-d H:i'),
        ])
            ->assertStatus(302)
            ->assertSessionHas('status', 'Выплата отложена');

        Http::assertNothingSent();

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
        $this->assertNotNull($payout->when_to_run);
        $this->assertSame('INITIATED', (string) $payout->status);
    }

    public function test_delay_validation_rejects_invalid_run_at(): void
    {
        $this->grantPayoutsManagePermissionForCurrentUser();

        TinkoffPayment::create([
            'order_id' => 'order-payout-4',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-4',
        ]);

        $this->post('/tinkoff/payouts/deal-4/delay', [
            'run_at' => 'bad-format',
        ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['run_at']);
    }

    public function test_pay_now_rejects_and_does_not_call_bank_when_net_amount_is_zero(): void
    {
        $this->grantPayoutsManagePermissionForCurrentUser();

        $this->partner->tinkoff_partner_id = 'SHOP-1';
        $this->partner->save();
        $this->seedE2cKeysForPartner($this->partner);

        // Сделаем платформенную минималку > суммы, чтобы net ушёл в 0
        TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 0,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0,
            'payout_min_fixed' => 0,
            'platform_percent' => 0,
            'platform_min_fixed' => 999, // 999 ₽ => 99900 коп
            'is_enabled' => 1,
        ]);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-payout-5',
            'partner_id' => $this->partner->id,
            'amount' => 1000, // 10 ₽
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-5',
        ]);

        Http::fake();

        $this->post('/tinkoff/payouts/deal-5/pay-now')->assertStatus(302);

        Http::assertNothingSent();

        $payout = TinkoffPayout::where('payment_id', $tp->id)->firstOrFail();
        $this->assertSame('REJECTED', (string) $payout->status);
        $this->assertNotNull($payout->completed_at);
    }

    public function test_user_without_payouts_permission_gets_403(): void
    {
        // permission не выдаём
        TinkoffPayment::create([
            'order_id' => 'order-payout-6',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-6',
        ]);

        $this->post('/tinkoff/payouts/deal-6/pay-now')->assertStatus(403);
    }

    public function test_non_superadmin_cannot_manage_payouts_of_foreign_partner_even_with_permission(): void
    {
        $this->grantPayoutsManagePermissionForCurrentUser();

        // платёж чужого партнёра
        TinkoffPayment::create([
            'order_id' => 'order-foreign',
            'partner_id' => $this->foreignPartner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-foreign',
        ]);

        $this->post('/tinkoff/payouts/deal-foreign/pay-now')->assertStatus(404);
    }

    public function test_superadmin_can_manage_payouts_by_deal_id_for_any_partner(): void
    {
        // суперюзер проходит Gate::before
        $this->asSuperadmin();

        $this->foreignPartner->tinkoff_partner_id = 'SHOP-FOREIGN';
        $this->foreignPartner->save();
        $this->seedE2cKeysForPartner($this->foreignPartner);

        $tp = TinkoffPayment::create([
            'order_id' => 'order-super',
            'partner_id' => $this->foreignPartner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-super',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/e2c/v2/Init')) {
                return Http::response(['Success' => true, 'PaymentId' => 9101], 200);
            }
            if (str_contains($request->url(), '/e2c/v2/Payment')) {
                return Http::response(['Success' => true, 'Status' => 'COMPLETED'], 200);
            }
            return Http::response(['Success' => true], 200);
        });

        $this->post('/tinkoff/payouts/deal-super/pay-now')->assertStatus(302);

        $payout = TinkoffPayout::where('payment_id', $tp->id)->first();
        $this->assertNotNull($payout);
    }
}

