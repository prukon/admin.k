<?php

namespace Tests\Feature\Crm\Payments\TBank\Payouts;

use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TbankPayoutScheduleUpdateFeatureTest extends CrmTestCase
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

    private function makeDeferrablePayout(): TinkoffPayout
    {
        return TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'sched-test-' . uniqid(),
            'amount' => 1000,
            'is_final' => true,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
            'completed_at' => null,
            'source' => 'delayed',
        ]);
    }

    public function test_schedule_update_success_updates_when_payload_and_my_logs(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $payout = $this->makeDeferrablePayout();
        $newLocal = now()->addHours(3)->startOfMinute();

        $resp = $this->from('/admin/tinkoff/payouts/' . $payout->id)
            ->post('/admin/tinkoff/payouts/' . $payout->id . '/schedule', [
                'when_to_run' => $newLocal->format('Y-m-d\TH:i'),
            ]);

        $resp->assertRedirect('/admin/tinkoff/payouts/' . $payout->id);
        $resp->assertSessionHas('status');

        $payout->refresh();
        $this->assertTrue(
            $payout->when_to_run->equalTo($newLocal),
            'when_to_run в БД должен совпадать с переданным значением (с минутной точностью).'
        );

        $state = $payout->payload_state ?? [];
        $this->assertIsArray($state['when_to_run_changes'] ?? null);
        $this->assertCount(1, $state['when_to_run_changes']);
        $this->assertSame(auth()->id(), $state['when_to_run_changes'][0]['user_id'] ?? null);

        $this->assertDatabaseHas('my_logs', [
            'type' => 6,
            'action' => 61,
            'partner_id' => $this->partner->id,
            'target_type' => TinkoffPayout::class,
            'target_id' => $payout->id,
            'author_id' => $this->user->id,
        ]);
    }

    public function test_schedule_update_returns_403_without_manage_permission(): void
    {
        $u = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->actingAs($u);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $payout = $this->makeDeferrablePayout();

        $this->post('/admin/tinkoff/payouts/' . $payout->id . '/schedule', [
            'when_to_run' => now()->addHours(2)->format('Y-m-d\TH:i'),
        ])->assertForbidden();
    }

    public function test_schedule_update_returns_404_for_foreign_partner_payout(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $foreign = TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'foreign-sched-' . uniqid(),
            'amount' => 500,
            'is_final' => true,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
            'completed_at' => null,
            'source' => 'delayed',
        ]);

        $this->post('/admin/tinkoff/payouts/' . $foreign->id . '/schedule', [
            'when_to_run' => now()->addHours(5)->format('Y-m-d\TH:i'),
        ])->assertNotFound();
    }

    public function test_schedule_update_fails_when_payout_not_eligible(): void
    {
        $this->grantPayoutManageToUser($this->user);

        $payout = TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'bad-status-' . uniqid(),
            'amount' => 100,
            'is_final' => true,
            'status' => 'NEW',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
            'completed_at' => null,
            'source' => 'delayed',
        ]);

        $this->from('/admin/tinkoff/payouts/' . $payout->id)
            ->post('/admin/tinkoff/payouts/' . $payout->id . '/schedule', [
                'when_to_run' => now()->addHours(2)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect('/admin/tinkoff/payouts/' . $payout->id)
            ->assertSessionHasErrors('when_to_run');
    }

    public function test_schedule_update_validation_rejects_past_time(): void
    {
        $this->grantPayoutManageToUser($this->user);
        $payout = $this->makeDeferrablePayout();

        $past = now()->subHour()->format('Y-m-d\TH:i');

        $this->from('/admin/tinkoff/payouts/' . $payout->id)
            ->post('/admin/tinkoff/payouts/' . $payout->id . '/schedule', [
                'when_to_run' => $past,
            ])
            ->assertRedirect('/admin/tinkoff/payouts/' . $payout->id)
            ->assertSessionHasErrors('when_to_run');
    }

    public function test_superadmin_can_reschedule_foreign_partner_payout(): void
    {
        $this->asSuperadmin();
        $this->grantPayoutManageToUser($this->user);

        $foreign = TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->foreignPartner->id,
            'deal_id' => 'sa-sched-' . uniqid(),
            'amount' => 800,
            'is_final' => true,
            'status' => 'INITIATED',
            'tinkoff_payout_payment_id' => null,
            'when_to_run' => now()->addDay(),
            'completed_at' => null,
            'source' => 'delayed',
        ]);

        $newLocal = now()->addHours(4)->startOfMinute();

        $this->from('/admin/tinkoff/payouts/' . $foreign->id)
            ->post('/admin/tinkoff/payouts/' . $foreign->id . '/schedule', [
                'when_to_run' => $newLocal->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect('/admin/tinkoff/payouts/' . $foreign->id);

        $foreign->refresh();
        $this->assertTrue($foreign->when_to_run->equalTo($newLocal));
    }
}
