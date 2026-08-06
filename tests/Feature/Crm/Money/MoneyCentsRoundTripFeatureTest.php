<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Money;

use App\Models\LessonPackage;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Team;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayout;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Payments\UserLessonPackageFeePaymentResolver;
use App\Services\Payments\UserPriceMonthlyFeePaymentResolver;
use App\Services\TeamUserSyncService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Канон денег (BIGINT *_cents): round-trip UI-рубли ↔ БД-копейки,
 * JSON-границы, ledger, отчёт комиссий без лишнего ×100.
 *
 * @see docs/documentation/money.html
 * @see App\Support\Money
 */
final class MoneyCentsRoundTripFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Money cents',
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'Копеечный',
            'name' => 'Ученик',
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);
    }

    public function test_set_price_all_users_stores_price_cents_and_json_exposes_rubles(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 0,
            'is_paid' => false,
        ]);

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 30)->create([
            'price_cents' => 89910,
            'is_active' => true,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
            'usersPrice' => [[
                'user_id' => $this->student->id,
                'price' => 899.10,
                'lesson_package_id' => $package->id,
                'user' => ['name' => $this->student->name],
            ]],
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 89910,
        ]);

        $ulp = UserLessonPackage::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->first();
        $this->assertNotNull($ulp);
        $this->assertSame(89910, (int) $ulp->fee_amount_cents);

        $json = $this->postJson(route('getTeamPrice'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $this->team->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $usersPrice = collect($json['usersPrice'] ?? []);
        $this->assertNotEmpty($usersPrice);

        $found = $usersPrice->first(
            fn ($u) => (int) ($u['user_id'] ?? 0) === (int) $this->student->id
        );
        $this->assertNotNull($found);
        $this->assertEqualsWithDelta(899.10, (float) ($found['price'] ?? 0), 0.001);
        $this->assertSame(89910, (int) ($found['price_cents'] ?? 0));
    }

    public function test_custom_payment_with_kopecks_stores_amount_cents_and_returns_rubles_in_json(): void
    {
        $this->grantPermission('setPrices.customPayments.view');

        $this->postJson(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount' => '1234.56',
            'note' => 'С копейками',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.amount', 1234.56);

        $this->assertDatabaseHas('user_custom_payment', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount_cents' => 123456,
            'note' => 'С копейками',
        ]);
    }

    public function test_monthly_fee_resolver_returns_amount_cents_from_users_prices(): void
    {
        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2025-10-01',
            'price_cents' => 150050,
            'is_paid' => false,
        ]);

        $resolved = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
            (int) $this->student->id,
            (int) $this->partner->id,
            '2025-10-01',
            (int) $this->team->id
        );

        $this->assertSame(150050, $resolved['amount_cents']);
        $this->assertSame(Money::fromCents(150050), $resolved['out_sum']);
        $this->assertSame((int) $this->team->id, $resolved['team_id']);
    }

    public function test_ulp_fee_resolver_returns_amount_cents(): void
    {
        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 50000,
            'is_active' => true,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 77766,
            'is_paid' => false,
        ]);

        $resolved = app(UserLessonPackageFeePaymentResolver::class)->resolveOrAbort(
            (int) $this->student->id,
            (int) $this->partner->id,
            (int) $ulp->id
        );

        $this->assertSame(77766, $resolved['amount_cents']);
        $this->assertSame('777.66', $resolved['out_sum']);
    }

    public function test_payable_intent_payment_ledger_uses_cents_columns(): void
    {
        $payable = Payable::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'amount_cents' => 250050,
        ]);

        $intent = PaymentIntent::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'payable_id' => $payable->id,
            'out_sum_cents' => 250050,
            'status' => 'pending',
        ]);

        $payment = Payment::factory()->forUser($this->student)->create([
            'summ_cents' => 250050,
        ]);

        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'amount_cents' => 250050,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'out_sum_cents' => 250050,
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'summ_cents' => 250050,
        ]);

        $this->assertSame(250050, Money::toCents('2500.50'));
        $this->assertSame('2500.50', Money::fromCents(250050));
    }

    public function test_payments_report_commissions_use_summ_cents_without_extra_times_100(): void
    {
        $this->grantPermission('reports.view');
        $this->grantPermission('reports.additional.value.view');
        $this->grantPermission('reports.payments.commission_total.view');

        $rule = new TinkoffCommissionRule();
        $rule->partner_id = $this->partner->id;
        $rule->method = null;
        $rule->is_enabled = true;
        $rule->acquiring_percent = 2.5;
        $rule->acquiring_min_fixed = 3.5;
        $rule->payout_percent = 1.0;
        $rule->payout_min_fixed = 0.0;
        $rule->platform_percent = 5.0;
        $rule->platform_min_fixed = 1.0;
        $rule->min_fixed = 0.0;
        $rule->save();

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'summ_cents' => 100000,
            'deal_id' => 'money-cents-deal',
            'payment_id' => null,
            'payment_status' => null,
        ]);

        TinkoffPayout::query()->create([
            'partner_id' => $this->partner->id,
            'deal_id' => 'money-cents-deal',
            'amount' => 50_000,
            'is_final' => 1,
            'status' => 'COMPLETED',
        ]);

        $grossCents = 100000;
        $bankAcceptFee = max(
            (int) round($grossCents * 2.5 / 100),
            (int) round(3.5 * 100)
        );
        $bankPayoutFee = max((int) round($grossCents * 1.0 / 100), 0);
        $platformFee = max(
            (int) round($grossCents * 5.0 / 100),
            (int) round(1.0 * 100)
        );
        $expectedNet = round(max(0, $grossCents - $bankAcceptFee - $bankPayoutFee - $platformFee) / 100, 2);

        $row = collect(
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('payments.getPayments'))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $payment->id);

        $this->assertNotNull($row);
        $this->assertEquals(1000.0, (float) $row['summ']);
        $this->assertEquals($expectedNet, (float) $row['net_to_partner']);
        $this->assertGreaterThan(0, (float) $row['net_to_partner']);
        $this->assertLessThan(1000, (float) $row['net_to_partner']);
    }

    public function test_debts_report_sums_price_cents_as_rubles(): void
    {
        $this->grantPermission('reports.view');

        $pastMonth = Carbon::now()->subMonths(2)->startOfMonth()->format('Y-m-d');

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => $pastMonth,
            'price_cents' => 350050,
            'is_paid' => false,
        ]);

        UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'date_start' => Carbon::now()->subMonths(2)->format('Y-m-d'),
            'date_end' => Carbon::now()->subMonth()->format('Y-m-d'),
            'amount_cents' => 10050,
            'note' => 'Долг копейки',
            'is_paid' => false,
        ]);

        $this->getJson(route('reports.debts.total'))
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('total_raw', 3601)
                ->etc()
            );
    }

    public function test_money_discount_variant_a_available_for_future_percent_discounts(): void
    {
        $this->assertSame(53, Money::discountCents(105, 50));
        $this->assertSame(52, Money::payableAfterDiscountCents(105, 50));
        $this->assertSame('0,52', Money::formatRub(52));
        $this->assertSame('8 000', Money::formatRub(800000));
        $this->assertSame('8 000,50', Money::formatRub(800050));
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
