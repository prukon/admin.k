<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Payable;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Строки «Сегодня» / «Вчера» в пульте: T‑Bank за календарный день, комиссия как в отчёте «Платежи».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsDayFeatureTest extends SystemMonitorsTestCase
{
    public function test_empty_day_is_zeros(): void
    {
        $this->asSuperadmin();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 0)
            ->assertJsonPath('day.commission', 0)
            ->assertJsonPath('day.payments_count', 0)
            ->assertJsonPath('yesterday.turnover', 0)
            ->assertJsonPath('yesterday.commission', 0)
            ->assertJsonPath('yesterday.payments_count', 0);
    }

    public function test_today_tbank_turnover_commission_and_count(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 15:20:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        $this->makeTbankPayment($student, 15_000_000, $now->copy()->setTime(10, 0));
        $this->makeTbankPayment($student, 15_000_000, $now->copy()->setTime(18, 30), 'deal-today-b');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 300000)
            ->assertJsonPath('day.commission', 1200)
            ->assertJsonPath('day.payments_count', 2)
            ->assertJsonPath('yesterday.turnover', 0)
            ->assertJsonPath('yesterday.commission', 0)
            ->assertJsonPath('yesterday.payments_count', 0);
    }

    public function test_yesterday_tomorrow_robokassa_and_zero_sum_are_excluded(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        $this->makeTbankPayment($student, 15_000_000, $now->copy()->setTime(8, 0));
        $this->makeTbankPayment(
            $student,
            10_000_000,
            $now->copy()->subDay()->setTime(23, 59),
            'deal-yesterday'
        );
        $this->makeTbankPayment(
            $student,
            8_880_000,
            $now->copy()->addDay()->startOfDay(),
            'deal-tomorrow'
        );
        Payment::factory()->forUser($student)->create([
            'summ_cents' => 7_770_000,
            'operation_date' => $now->toDateTimeString(),
            'deal_id' => null,
            'payment_id' => null,
            'payment_status' => null,
        ]);
        $this->makeTbankPayment($student, 0, $now->copy()->setTime(9, 0), 'deal-zero');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 600)
            ->assertJsonPath('day.payments_count', 1)
            ->assertJsonPath('yesterday.turnover', 100000)
            ->assertJsonPath('yesterday.commission', 400)
            ->assertJsonPath('yesterday.payments_count', 1);
    }

    public function test_counts_all_partners_not_current_session(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $this->makeTbankPayment($this->student(), 10_000_000, $now, 'deal-home');
        $foreignStudent = $this->createUserWithRole('user', $this->foreignPartner);
        $this->makeTbankPayment($foreignStudent, 5_000_000, $now, 'deal-foreign');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 600)
            ->assertJsonPath('day.payments_count', 2);
    }

    public function test_yesterday_tbank_row_same_formula_as_today(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        $this->makeTbankPayment($student, 15_000_000, $now->copy()->subDay()->setTime(10, 0), 'deal-yd-a');
        $this->makeTbankPayment($student, 15_000_000, $now->copy()->subDay()->setTime(18, 0), 'deal-yd-b');
        $this->makeTbankPayment($student, 5_000_000, $now->copy()->setTime(11, 0), 'deal-today-noise');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('yesterday.turnover', 300000)
            ->assertJsonPath('yesterday.commission', 1200)
            ->assertJsonPath('yesterday.payments_count', 2)
            ->assertJsonPath('day.turnover', 50000)
            ->assertJsonPath('day.commission', 200)
            ->assertJsonPath('day.payments_count', 1);
    }

    public function test_partner_rule_beats_global_and_uses_tbank_method(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 1.00,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);
        TinkoffCommissionRule::factory()->create([
            'partner_id' => $this->partner->id,
            'method' => 'sbp',
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $this->makeTbankPayment($this->student(), 15_000_000, $now, 'deal-sbp', 'sbp');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 600)
            ->assertJsonPath('day.payments_count', 1);
    }

    public function test_blocking_refund_skips_commission_but_keeps_turnover_and_count(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        $this->makeTbankPayment($student, 10_000_000, $now, 'deal-kept');
        $refunded = $this->makeTbankPayment($student, 5_000_000, $now, 'deal-refunded');

        $payable = Payable::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'status' => 'paid',
            'amount_cents' => 5_000_000,
        ]);
        Refund::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'payable_id' => $payable->id,
            'payment_id' => $refunded->id,
            'amount_cents' => 5_000_000,
            'currency' => 'RUB',
            'status' => 'succeeded',
            'provider' => 'tbank',
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 400)
            ->assertJsonPath('day.payments_count', 2);
    }

    public function test_midnight_belongs_to_today_and_last_second_to_yesterday(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 00:30:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        $this->makeTbankPayment(
            $student,
            15_000_000,
            Carbon::parse('2026-09-03 00:00:00', 'Europe/Moscow'),
            'deal-midnight-today'
        );
        $this->makeTbankPayment(
            $student,
            10_000_000,
            Carbon::parse('2026-09-02 23:59:59', 'Europe/Moscow'),
            'deal-last-second-yesterday'
        );

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.payments_count', 1)
            ->assertJsonPath('yesterday.turnover', 100000)
            ->assertJsonPath('yesterday.payments_count', 1);
        $this->assertIsInt($response->json('day.turnover'));
        $this->assertIsInt($response->json('yesterday.turnover'));
    }

    public function test_tbank_by_payment_status_or_payment_id_only_still_counts(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        Payment::factory()->forUser($student)->create([
            'partner_id' => $student->partner_id,
            'summ_cents' => 15_000_000,
            'operation_date' => $now->format('Y-m-d H:i:s'),
            'deal_id' => null,
            'payment_id' => null,
            'payment_status' => 'paid',
        ]);
        Payment::factory()->forUser($student)->create([
            'partner_id' => $student->partner_id,
            'summ_cents' => 5_000_000,
            'operation_date' => $now->format('Y-m-d H:i:s'),
            'deal_id' => null,
            'payment_id' => 'pid-status-only-sibling',
            'payment_status' => null,
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 200000)
            ->assertJsonPath('day.commission', 800)
            ->assertJsonPath('day.payments_count', 2);
    }

    public function test_whitespace_only_markers_are_not_tbank(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        Payment::factory()->forUser($student)->create([
            'partner_id' => $student->partner_id,
            'summ_cents' => 15_000_000,
            'operation_date' => $now->format('Y-m-d H:i:s'),
            'deal_id' => '   ',
            'payment_id' => '  ',
            'payment_status' => ' ',
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 0)
            ->assertJsonPath('day.commission', 0)
            ->assertJsonPath('day.payments_count', 0);
    }

    public function test_disabled_rule_is_ignored_and_missing_rule_gives_zero_commission(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->disabled()->create([
            'platform_percent' => 1.00,
            'platform_min_fixed' => 0,
        ]);

        $student = $this->student();
        $this->makeTbankPayment($student, 15_000_000, $now, 'deal-no-enabled-rule');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 0)
            ->assertJsonPath('day.payments_count', 1);
    }

    public function test_platform_min_fixed_beats_percent_when_larger(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.00,
            'platform_min_fixed' => 100.00,
            'is_enabled' => true,
        ]);

        $this->makeTbankPayment($this->student(), 15_000_000, $now, 'deal-min-fixed');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 100)
            ->assertJsonPath('day.payments_count', 1);
    }

    public function test_failed_refund_does_not_skip_commission_and_pending_does(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        $failedRefundPayment = $this->makeTbankPayment($student, 15_000_000, $now, 'deal-failed-refund');
        $pendingRefundPayment = $this->makeTbankPayment($student, 10_000_000, $now, 'deal-pending-refund');

        $this->attachRefund($student, $failedRefundPayment, 'failed');
        $this->attachRefund($student, $pendingRefundPayment, 'pending');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 250000)
            ->assertJsonPath('day.commission', 600)
            ->assertJsonPath('day.payments_count', 2);
    }

    public function test_latest_failed_refund_does_not_skip_commission_after_succeeded(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->student();
        $payment = $this->makeTbankPayment($student, 15_000_000, $now, 'deal-latest-failed');
        $this->attachRefund($student, $payment, 'succeeded');
        $this->attachRefund($student, $payment, 'failed');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 600)
            ->assertJsonPath('day.payments_count', 1);
    }

    public function test_yesterday_counts_all_partners_not_current_session(): void
    {
        $this->asSuperadmin();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $yesterday = $now->copy()->subDay()->setTime(11, 0);
        $this->makeTbankPayment($this->student(), 10_000_000, $yesterday, 'deal-yd-home');
        $foreignStudent = $this->createUserWithRole('user', $this->foreignPartner);
        $this->makeTbankPayment($foreignStudent, 5_000_000, $yesterday, 'deal-yd-foreign');

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('yesterday.turnover', 150000)
            ->assertJsonPath('yesterday.commission', 600)
            ->assertJsonPath('yesterday.payments_count', 2)
            ->assertJsonPath('day.turnover', 0);
    }

    public function test_personal_flag_off_still_returns_today_snapshot(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);
        $this->makeTbankPayment($this->student(), 15_000_000, $now, 'deal-flag-off');

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 600)
            ->assertJsonPath('day.payments_count', 1);
    }

    private function attachRefund(User $student, Payment $payment, string $status): void
    {
        $payable = Payable::factory()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'status' => 'paid',
            'amount_cents' => $payment->summ_cents,
        ]);
        Refund::query()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'payable_id' => $payable->id,
            'payment_id' => $payment->id,
            'amount_cents' => $payment->summ_cents,
            'currency' => 'RUB',
            'status' => $status,
            'provider' => 'tbank',
        ]);
    }

    private function student(): User
    {
        return $this->createUserWithRole('user', $this->partner);
    }

    private function makeTbankPayment(
        User $student,
        int $summCents,
        Carbon $operationAt,
        string $dealId = 'deal-today-a',
        string $method = 'card'
    ): Payment {
        $payment = Payment::factory()->forUser($student)->create([
            'partner_id' => $student->partner_id,
            'summ_cents' => $summCents,
            'operation_date' => $operationAt->format('Y-m-d H:i:s'),
            'deal_id' => $dealId,
            'payment_id' => 'pid-'.$dealId,
            'payment_status' => 'paid',
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-'.$dealId,
            'partner_id' => (int) $student->partner_id,
            'amount' => $summCents,
            'method' => $method,
            'status' => 'CONFIRMED',
            'deal_id' => $dealId,
            'tinkoff_payment_id' => (string) random_int(300_000_000, 2_000_000_000),
        ]);

        return $payment;
    }
}
