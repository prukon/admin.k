<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\PaymentIntent;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankAdminPayoutsProviderInvIdFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPayoutManageToUser(User $user, ?int $partnerId = null): void
    {
        $partnerId ??= $this->partner->id;
        $permId = $this->permissionId('tbank.payouts.manage');

        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $partnerId,
                'role_id' => $user->role_id,
                'permission_id' => $permId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * [P1] В /admin/tinkoff/payouts/data поле provider_inv_id берётся из payment_intents
     * по связке tbank_order_id = tinkoff_payments.order_id и совпадению partner_id (provider = tbank).
     */
    public function test_payouts_data_includes_provider_inv_id_from_linked_tbank_payment_intent(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $orderId = 'ord-pinv-' . uniqid();
        $providerInvId = random_int(500_000_000_000, 900_000_000_000);

        $payment = TinkoffPayment::query()->create([
            'order_id' => $orderId,
            'partner_id' => $this->partner->id,
            'amount' => 10_000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => (string) $providerInvId,
            'deal_id' => 'deal-pinv-' . uniqid(),
            'confirmed_at' => now(),
        ]);

        PaymentIntent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'provider' => 'tbank',
            'provider_inv_id' => $providerInvId,
            'tbank_order_id' => $orderId,
            'status' => 'paid',
            'out_sum' => '100.00',
        ]);

        $payout = TinkoffPayout::query()->create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $payment->deal_id,
            'amount' => 9_500,
            'status' => 'NEW',
            'payer_user_id' => $this->user->id,
        ]);

        $resp = $this->get('/admin/tinkoff/payouts/data?draw=1&start=0&length=50');
        $resp->assertOk();

        $rows = collect($resp->json('data'));
        $row = $rows->firstWhere('id', $payout->id);
        $this->assertNotNull($row, 'Строка выплаты должна присутствовать в ответе DataTables');
        $this->assertSame((string) $providerInvId, $row['provider_inv_id']);
    }

    /**
     * [P1] Без подходящего payment_intents (tbank + tbank_order_id + partner) в ответе — «—».
     */
    public function test_payouts_data_provider_inv_id_is_em_dash_without_matching_intent(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $payment = TinkoffPayment::query()->create([
            'order_id' => 'ord-no-pi-' . uniqid(),
            'partner_id' => $this->partner->id,
            'amount' => 5_000,
            'method' => 'sbp',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => '999001',
            'deal_id' => 'deal-nopi-' . uniqid(),
            'confirmed_at' => now(),
        ]);

        $payout = TinkoffPayout::query()->create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $payment->deal_id,
            'amount' => 4_800,
            'status' => 'NEW',
            'payer_user_id' => $this->user->id,
        ]);

        $resp = $this->get('/admin/tinkoff/payouts/data?draw=1&start=0&length=50');
        $resp->assertOk();

        $rows = collect($resp->json('data'));
        $row = $rows->firstWhere('id', $payout->id);
        $this->assertNotNull($row);
        $this->assertSame('—', $row['provider_inv_id']);
    }

    /**
     * [P1] Совпадение только по order_id без partner_id не подставляет чужой intent.
     */
    public function test_payouts_data_does_not_pick_foreign_partner_payment_intent(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $orderId = 'ord-cross-' . uniqid();
        $foreignInvId = random_int(500_000_000_001, 900_000_000_001);

        $payment = TinkoffPayment::query()->create([
            'order_id' => $orderId,
            'partner_id' => $this->partner->id,
            'amount' => 3_000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'tinkoff_payment_id' => '1',
            'deal_id' => 'deal-cross-' . uniqid(),
            'confirmed_at' => now(),
        ]);

        PaymentIntent::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $this->foreignUser->id,
            'provider' => 'tbank',
            'provider_inv_id' => $foreignInvId,
            'tbank_order_id' => $orderId,
            'status' => 'paid',
            'out_sum' => '30.00',
        ]);

        $payout = TinkoffPayout::query()->create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => (string) $payment->deal_id,
            'amount' => 2_900,
            'status' => 'NEW',
            'payer_user_id' => $this->user->id,
        ]);

        $resp = $this->get('/admin/tinkoff/payouts/data?draw=1&start=0&length=50');
        $resp->assertOk();

        $rows = collect($resp->json('data'));
        $row = $rows->firstWhere('id', $payout->id);
        $this->assertNotNull($row);
        $this->assertSame('—', $row['provider_inv_id']);
    }
}
