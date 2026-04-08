<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankAdminPayoutsAccessFeatureTest extends CrmTestCase
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

    private function actingAsUserWithoutPayoutManage(): void
    {
        $u = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->actingAs($u);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_tbank_payouts_routes_return_403_without_manage_permission(): void
    {
        $this->actingAsUserWithoutPayoutManage();

        $this->get('/admin/tinkoff/payouts')->assertForbidden();
        $this->get('/admin/tinkoff/payouts/data')->assertForbidden();
        $this->get('/admin/tinkoff/payouts/total')->assertForbidden();
        $this->get('/admin/tinkoff/payouts/columns-settings')->assertForbidden();
        $this->postJson('/admin/tinkoff/payouts/columns-settings', ['columns' => ['status' => true]])->assertForbidden();
        $this->get('/admin/tinkoff/payouts/payers-search?q=')->assertForbidden();
        $this->get('/admin/tinkoff/payouts/partners-search?q=a')->assertForbidden();
        $this->get('/admin/tinkoff/payouts/1')->assertForbidden();
    }

    public function test_tbank_payouts_endpoints_return_200_when_allowed(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $payout = TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'test-access-' . uniqid(),
            'amount' => 100,
            'is_final' => false,
            'status' => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => null,
            'completed_at' => null,
        ]);

        $this->get('/admin/tinkoff/payouts')->assertOk();

        $this->get('/admin/tinkoff/payouts/total')->assertOk()->assertJsonStructure([
            'payments_total_formatted',
            'payments_total_raw',
            'payouts_total_formatted',
            'payouts_total_raw',
            'platform_fee_total_formatted',
            'platform_fee_total_raw',
        ]);

        $this->get('/admin/tinkoff/payouts/data?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'source',
                        'partner',
                        'payer',
                        'initiator',
                        'payment_id',
                        'provider_inv_id',
                        'deal_id',
                        'gross',
                        'bank_accept_fee',
                        'bank_payout_fee',
                        'platform_fee',
                        'net',
                        'when_to_run',
                        'created_at',
                        'completed_at',
                        'tinkoff_payout_payment_id',
                    ],
                ],
            ]);

        $this->get('/admin/tinkoff/payouts/columns-settings')->assertOk();

        $this->postJson('/admin/tinkoff/payouts/columns-settings', [
            'columns' => ['status' => true],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->get('/admin/tinkoff/payouts/payers-search?q=')->assertOk()->assertJsonStructure(['results']);

        $this->get('/admin/tinkoff/payouts/' . $payout->id)->assertOk();
    }

    /**
     * Superadmin с tbank.payouts.manage видит partners-search (остальным ролям — 403 в контроллере).
     */
    public function test_tbank_payouts_partners_search_returns_200_for_superadmin_with_manage_permission(): void
    {
        $this->asSuperadmin();
        $this->grantPayoutManageToUser($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->user->load('role');

        $this->get('/admin/tinkoff/payouts/partners-search?q=')
            ->assertOk()
            ->assertJsonStructure(['results']);
    }

    public function test_tbank_payouts_show_returns_404_for_other_partner_payout(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $foreign = TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'foreign-deal-' . uniqid(),
            'amount' => 50,
            'is_final' => false,
            'status' => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => null,
            'completed_at' => null,
        ]);

        $this->get('/admin/tinkoff/payouts/' . $foreign->id)->assertNotFound();
    }

    public function test_tbank_payouts_data_excludes_foreign_partner_rows_for_regular_user(): void
    {
        $this->grantPayoutManageToUser($this->user);

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'own-' . uniqid(),
            'amount' => 10,
            'is_final' => false,
            'status' => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => null,
            'completed_at' => null,
        ]);

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'foreign-' . uniqid(),
            'amount' => 20,
            'is_final' => false,
            'status' => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => null,
            'completed_at' => null,
        ]);

        $resp = $this->get('/admin/tinkoff/payouts/data?draw=1&start=0&length=50');
        $resp->assertOk();
        $this->assertSame(1, $resp->json('recordsTotal'));
        $this->assertCount(1, $resp->json('data'));
    }

    public function test_tbank_payouts_superadmin_sees_foreign_partner_in_data_when_filtered(): void
    {
        $this->asSuperadmin();
        $this->grantPayoutManageToUser($this->user);

        TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'sa-foreign-' . uniqid(),
            'amount' => 30,
            'is_final' => false,
            'status' => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => null,
            'completed_at' => null,
        ]);

        $url = '/admin/tinkoff/payouts/data?draw=1&start=0&length=50&partner_id=' . $this->foreignPartner->id;
        $resp = $this->get($url);
        $resp->assertOk();
        $this->assertSame(1, $resp->json('recordsTotal'));
        $this->assertCount(1, $resp->json('data'));
    }
}
