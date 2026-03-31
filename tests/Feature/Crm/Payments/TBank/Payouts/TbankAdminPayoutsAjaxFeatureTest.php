<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\Partner;
use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankAdminPayoutsAjaxFeatureTest extends CrmTestCase
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
     * [P0] Новый endpoint /admin/tinkoff/payouts/total под доступом tbank.payouts.manage.
     */
    public function test_tbank_payouts_total_requires_manage_permission(): void
    {
        $u = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->actingAs($u);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get('/admin/tinkoff/payouts/total')->assertForbidden();
    }

    /**
     * [P0] /admin/tinkoff/payouts/total возвращает сумму COALESCE(net_amount, amount) (в рублях) и не течёт между партнёрами.
     */
    public function test_tbank_payouts_total_returns_correct_sum_for_current_partner(): void
    {
        $this->grantPayoutManageToUser($this->user);

        // В текущем партнёре:
        // 1) net_amount null -> берём amount
        TinkoffPayout::query()->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'p1-' . uniqid(),
            'status' => 'NEW',
            'amount' => 15000, // 150 руб
            'net_amount' => null,
            'payer_user_id' => $this->user->id,
        ]);
        // 2) net_amount есть -> берём net_amount
        TinkoffPayout::query()->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'p2-' . uniqid(),
            'status' => 'NEW',
            'amount' => 99999,
            'net_amount' => 25000, // 250 руб
            'payer_user_id' => $this->user->id,
        ]);

        // В чужом партнёре (не должен попасть):
        TinkoffPayout::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'foreign-' . uniqid(),
            'status' => 'NEW',
            'amount' => 999900, // 9999 руб
            'net_amount' => null,
            'payer_user_id' => $this->foreignUser->id,
        ]);

        $expectedRawRub = 400.0;
        $expectedFormatted = number_format($expectedRawRub, 0, '', ' ');

        $this->get('/admin/tinkoff/payouts/total')
            ->assertOk()
            ->assertJson([
                'total_formatted' => $expectedFormatted,
                'total_raw' => $expectedRawRub,
            ]);
    }

    /**
     * [P1] /admin/tinkoff/payouts/total учитывает фильтр payer_id.
     */
    public function test_tbank_payouts_total_respects_payer_id_filter(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $payer1 = $this->user;
        $payer2 = User::factory()->create(['partner_id' => $this->partner->id]);

        TinkoffPayout::query()->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'pp1-' . uniqid(),
            'status' => 'NEW',
            'amount' => 10000, // 100 руб
            'payer_user_id' => $payer1->id,
        ]);
        TinkoffPayout::query()->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'pp2-' . uniqid(),
            'status' => 'NEW',
            'amount' => 20000, // 200 руб
            'payer_user_id' => $payer2->id,
        ]);

        $this->get('/admin/tinkoff/payouts/total?payer_id=' . $payer2->id)
            ->assertOk()
            ->assertJson([
                'total_formatted' => '200',
                'total_raw' => 200.0,
            ]);
    }

    /**
     * [P0] Select2 endpoints:
     * - partners-search доступен только superadmin (и под middleware tbank.payouts.manage)
     * - payers-search доступен всем с tbank.payouts.manage
     */
    public function test_tbank_payouts_select2_endpoints_access_and_shape(): void
    {
        $this->grantPayoutManageToUser($this->user);

        // partners-search: обычному пользователю 403
        $this->get('/admin/tinkoff/payouts/partners-search?q=a')->assertForbidden();

        // payers-search: 200 + results
        $resp = $this->get('/admin/tinkoff/payouts/payers-search?q=');
        $resp->assertOk()->assertJsonStructure(['results']);

        // superadmin: partners-search 200
        $this->asSuperadmin();
        // важно: hasRole() в коде использует $user->role relation; обновим её после смены role_id
        $this->user->load('role');
        $this->grantPayoutManageToUser($this->user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $p = Partner::factory()->create(['title' => 'PAYOUTS PARTNER X']);
        $this->get('/admin/tinkoff/payouts/partners-search?q=PAYOUTS')
            ->assertOk()
            ->assertJsonStructure(['results']);

        // payers-search with partner_id filters to that partner (superadmin case)
        $u = User::factory()->create(['partner_id' => $p->id, 'lastname' => 'Петров', 'name' => 'Пётр']);
        $usersJson = $this->get('/admin/tinkoff/payouts/payers-search?q=Пет&partner_id=' . $p->id)
            ->assertOk()
            ->json();

        $ids = collect($usersJson['results'])->pluck('id')->all();
        $this->assertContains($u->id, $ids);
    }
}

