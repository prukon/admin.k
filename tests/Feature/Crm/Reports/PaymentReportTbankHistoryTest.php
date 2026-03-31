<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Partner;
use App\Models\Payment;
use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class PaymentReportTbankHistoryTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin(); // reports.view (viewing.all.logs у admin по умолчанию нет)
    }

    private function grantViewingAllLogsToCurrentAdminRole(): void
    {
        $now = now();
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId('viewing.all.logs'),
            ],
            ['created_at' => $now, 'updated_at' => $now]
        );
    }

    public function test_tbank_history_requires_reports_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $payment = Payment::factory()->forUser($actor)->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-1',
            'payment_id' => '123',
            'payment_status' => 'CONFIRMED',
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.tbankHistory', ['payment' => $payment->id]));

        $response->assertForbidden();
    }

    public function test_tbank_history_requires_viewing_all_logs_permission(): void
    {
        $payment = Payment::factory()->forUser($this->user)->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-logs',
            'payment_id' => '999',
            'payment_status' => 'CONFIRMED',
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.tbankHistory', ['payment' => $payment->id]));

        $response->assertForbidden();
    }

    public function test_tbank_history_returns_403_for_foreign_partner_payment(): void
    {
        $otherPartner = Partner::factory()->create();
        $otherUser = User::factory()->create(['partner_id' => $otherPartner->id]);

        $payment = Payment::factory()->forUser($otherUser)->create([
            'partner_id' => $otherPartner->id,
            'deal_id' => 'deal-foreign',
            'payment_id' => '777',
            'payment_status' => 'CONFIRMED',
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.tbankHistory', ['payment' => $payment->id]));

        $response->assertForbidden();
    }

    public function test_tbank_history_returns_404_for_non_tbank_payment(): void
    {
        $this->grantViewingAllLogsToCurrentAdminRole();

        $payment = Payment::factory()->forUser($this->user)->create([
            'partner_id' => $this->partner->id,
            'deal_id' => null,
            'payment_id' => null,
            'payment_status' => null,
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.tbankHistory', ['payment' => $payment->id]));

        $response->assertNotFound();
    }

    public function test_tbank_history_returns_combined_payment_and_payout_events(): void
    {
        $this->grantViewingAllLogsToCurrentAdminRole();

        $payment = Payment::factory()->forUser($this->user)->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'deal-1',
            'payment_id' => '123',
            'payment_status' => 'CONFIRMED',
        ]);

        // payment logs (webhooks)
        DB::table('tinkoff_payment_status_logs')->insert([
            'tinkoff_payment_id' => 1,
            'partner_id' => (int) $this->partner->id,
            'event_source' => 'webhook',
            'from_status' => 'NEW',
            'to_status' => 'CONFIRMED',
            'bank_status' => 'CONFIRMED',
            'bank_payment_id' => '123',
            'order_id' => 'order-1',
            'payload' => json_encode(['Status' => 'CONFIRMED'], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        // payout + payout logs
        $payout = TinkoffPayout::create([
            'partner_id' => (int) $this->partner->id,
            'deal_id' => 'deal-1',
            'amount' => 10000,
            'status' => 'INITIATED',
            'is_final' => false,
        ]);

        DB::table('tinkoff_payout_status_logs')->insert([
            'payout_id' => (int) $payout->id,
            'from_status' => 'INITIATED',
            'to_status' => 'COMPLETED',
            'payload' => json_encode(['Status' => 'COMPLETED'], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('payments.tbankHistory', ['payment' => $payment->id]));

        $response
            ->assertOk()
            ->assertJsonPath('payment.id', $payment->id)
            ->assertJsonPath('payment.deal_id', 'deal-1')
            ->assertJsonPath('payment.bank_payment_id', '123');

        $events = $response->json('events') ?? [];
        $this->assertIsArray($events);

        $kinds = collect($events)->pluck('kind')->unique()->values()->all();
        $this->assertContains('payment', $kinds);
        $this->assertContains('payout', $kinds);
    }
}

