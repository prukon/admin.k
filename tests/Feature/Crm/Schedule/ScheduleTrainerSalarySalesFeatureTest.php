<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Payable;
use App\Models\Team;
use App\Models\TrainerSalarySnapshot;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;

/**
 * Схема sales: оклад + % от оплаченных месяцев и абонементов, бонусы/вычеты.
 */
final class ScheduleTrainerSalarySalesFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryViewSales();
        $this->grantTrainerSalaryManage();
    }

    public function test_page_uses_sales_scheme_and_copies_default_salary(): void
    {
        $trainer = $this->makeTrainerProfile('Продажи оклад');
        $trainer->update(['default_base_salary_cents' => 1500000]);

        $this->get(route('schedule.trainer-salary', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertSee('data-scheme-code="sales"', false)
            ->assertSee('data-field="sales_percent"', false)
            ->assertSee('Оплаченные', false)
            ->assertDontSee('как в отчёте «Нагрузка тренеров»', false);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
                ->assertOk()
                ->assertJsonPath('scheme_code', 'sales')
                ->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertNotNull($row);
        $this->assertSame('15000.00', $row['base_salary']);
        $this->assertSame(0, $row['sales_percent']);
        $this->assertSame('0.00', $row['paid_months']);
        $this->assertSame('0.00', $row['commission']);
        $this->assertSame('15000.00', $row['total']);
    }

    public function test_paid_month_and_package_enter_base_unpaid_and_other_month_do_not(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Продажи база');
        $trainer->update(['default_base_salary_cents' => 0]);

        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 800000, true, (int) $team->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-04-01', 500000, true, (int) $team->id)->create();
        $unpaidStudent = $this->makeStudent((int) $team->id);
        UserPrice::factory()->forUserAndMonth((int) $unpaidStudent->id, '2026-05-01', 300000, false, (int) $team->id)->create();

        $this->createPaidPackage($student->id, (int) $team->id, 200000, '2026-05-12 10:00:00');
        $this->createPaidPackage($student->id, (int) $team->id, 400000, '2026-06-02 10:00:00');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
        ])->assertOk();

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('8000.00', $row['paid_months']);
        $this->assertSame('2000.00', $row['paid_packages']);
        $this->assertSame('10000.00', $row['sales_base']);
        $this->assertSame('1000.00', $row['commission']);
        $this->assertSame(10, $row['sales_percent']);
        $this->assertSame('1000.00', $row['total']);
    }

    public function test_manual_paid_month_enters_base(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Ручная оплата');
        $trainer->update(['default_base_salary_cents' => 0]);

        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 250000, false, (int) $team->id)->create([
            'is_manual_paid' => true,
            'manual_paid_at' => '2026-05-20 12:00:00',
        ]);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('2500.00', $row['paid_months']);
        $this->assertSame('2500.00', $row['sales_base']);
    }

    public function test_two_trainers_of_same_group_each_get_full_base(): void
    {
        [$student, $team, $trainerA] = $this->makeStudentTeamAndTrainer('Тренер А продаж');
        $trainerB = $this->makeTrainerProfile('Тренер Б продаж');
        $this->attachTrainerToTeam($trainerB, (int) $team->id);
        $trainerA->update(['default_base_salary_cents' => 0]);
        $trainerB->update(['default_base_salary_cents' => 0]);

        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 1000000, true, (int) $team->id)->create();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainerA), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
        ])->assertOk();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainerB), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 20,
        ])->assertOk();

        $rows = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        );
        $rowA = $rows->firstWhere('trainer_profile_id', $trainerA->id);
        $rowB = $rows->firstWhere('trainer_profile_id', $trainerB->id);

        $this->assertSame('10000.00', $rowA['sales_base']);
        $this->assertSame('10000.00', $rowB['sales_base']);
        $this->assertSame('1000.00', $rowA['commission']);
        $this->assertSame('2000.00', $rowB['commission']);
    }

    public function test_monthly_ulp_already_in_users_prices_is_not_double_counted(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Без двойного счёта');
        $trainer->update(['default_base_salary_cents' => 0]);

        $ulp = $this->createPaidPackage($student->id, (int) $team->id, 700000, '2026-05-08 09:00:00');
        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 700000, true, (int) $team->id)->create([
            'user_lesson_package_id' => $ulp->id,
        ]);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('7000.00', $row['paid_months']);
        $this->assertSame('0.00', $row['paid_packages']);
        $this->assertSame('7000.00', $row['sales_base']);
    }

    public function test_club_fee_payable_does_not_enter_sales_base(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Не клубный');
        $trainer->update(['default_base_salary_cents' => 0]);

        Payable::factory()->clubFee()->paid()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'amount_cents' => 999000,
            'paid_at' => '2026-05-11 12:00:00',
            'meta' => ['team_id' => $team->id],
        ]);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('0.00', $row['sales_base']);
    }

    public function test_patch_percent_bonuses_recalculates_and_validates_integer_percent(): void
    {
        $trainer = $this->makeTrainerProfile('Патч процента');
        $trainer->update(['default_base_salary_cents' => 500000]);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10.5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sales_percent']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 101,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sales_percent']);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
            'bonuses' => 100,
            'deductions' => 50,
        ])
            ->assertOk()
            ->assertJsonPath('row.sales_percent', 10)
            ->assertJsonPath('row.bonuses', '100.00')
            ->assertJsonPath('row.deductions', '50.00')
            ->assertJsonPath('row.total', '5050.00');

        $this->assertDatabaseHas('trainer_salary_sales_draft_trainers', [
            'sales_percent' => 10,
        ]);
    }

    public function test_snapshot_freezes_sales_fields_and_sheet_renders_them(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Слепок продаж');
        $trainer->update(['default_base_salary_cents' => 200000]);
        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 1000000, true, (int) $team->id)->create();

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
            'comment' => 'май',
        ])->assertOk();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('snapshot.scheme_code', 'sales');

        $snapshot = TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(300000, (int) $snapshot->total_cents);

        $this->assertDatabaseHas('trainer_salary_sales_snapshot_trainers', [
            'trainer_salary_snapshot_id' => $snapshot->id,
            'sales_percent' => 10,
            'paid_months_cents' => 1000000,
            'commission_cents' => 100000,
        ]);

        $html = $this->get(route('schedule.trainer-salary-sheets.snapshot.show', $snapshot))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('База', $html);
        $this->assertStringContainsString('% от', $html);
        $this->assertStringContainsString('Слепок продаж', $html);
    }

    public function test_foreign_partner_paid_month_is_isolated(): void
    {
        $foreign = Partner::factory()->create();
        $foreignTeam = Team::factory()->create(['partner_id' => $foreign->id]);
        $foreignTrainer = $this->makeTrainerProfile('Чужой sales', $foreign->id);
        DB::table('team_trainer')->insert([
            'partner_id' => $foreign->id,
            'team_id' => $foreignTeam->id,
            'trainer_profile_id' => $foreignTrainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignStudent = $this->makeStudent($foreignTeam->id);
        $foreignStudent->update(['partner_id' => $foreign->id]);
        UserPrice::factory()->forUserAndMonth((int) $foreignStudent->id, '2026-05-01', 800000, true, (int) $foreignTeam->id)->create();

        $local = $this->makeTrainerProfile('Локальный sales');
        $local->update(['default_base_salary_cents' => 0]);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $foreignTrainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 10,
        ])->assertNotFound();

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $local->id);
        $this->assertSame('0.00', $row['sales_base']);
    }

    public function test_explicit_manual_unpaid_excludes_month_even_if_is_paid(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Ручной отказ');
        $trainer->update(['default_base_salary_cents' => 0]);

        UserPrice::factory()->forUserAndMonth((int) $student->id, '2026-05-01', 900000, true, (int) $team->id)->create([
            'is_manual_paid' => false,
        ]);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('0.00', $row['paid_months']);
        $this->assertSame('0.00', $row['sales_base']);
    }

    public function test_paid_package_without_team_is_not_attributed(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Без team_id');
        $trainer->update(['default_base_salary_cents' => 0]);

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 400000,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'team_id' => null,
            'lesson_package_id' => $package->id,
            'fee_amount_cents' => 400000,
            'is_paid' => true,
            'billing_month' => null,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'created_by' => $this->user->id,
        ]);
        Payable::factory()->paid()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'type' => 'lesson_package_fee',
            'amount_cents' => 400000,
            'month' => null,
            'paid_at' => '2026-05-12 10:00:00',
            'meta' => [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
            ],
        ]);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('0.00', $row['paid_packages']);
        $this->assertSame('0.00', $row['sales_base']);
    }

    public function test_package_marked_paid_without_payment_date_does_not_enter_base(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Без даты оплаты');
        $trainer->update(['default_base_salary_cents' => 0]);

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => 300000,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'lesson_package_id' => $package->id,
            'fee_amount_cents' => 300000,
            'is_paid' => true,
            'billing_month' => null,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'created_by' => $this->user->id,
        ]);

        $row = collect(
            $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))->json('rows')
        )->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertSame('0.00', $row['paid_packages']);
        $this->assertSame('0.00', $row['sales_base']);
    }

    public function test_staff_with_view_but_without_sales_scheme_gets_403(): void
    {
        $this->revokePermission('schedule.trainerSalary.scheme.sales');
        $trainer = $this->makeTrainerProfile('Без sales');

        $this->get(route('schedule.trainer-salary'))->assertForbidden();
        $this->getJson(route('schedule.trainer-salary.data'))->assertForbidden();
        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'sales_percent' => 1,
        ])->assertForbidden();
    }

    private function attachTrainerToTeam(\App\Models\TrainerProfile $trainer, int $teamId): void
    {
        DB::table('team_trainer')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamId,
            'trainer_profile_id' => $trainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPaidPackage(int $userId, int $teamId, int $feeCents, string $paidAt): UserLessonPackage
    {
        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'price_cents' => $feeCents,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $userId,
            'team_id' => $teamId,
            'lesson_package_id' => $package->id,
            'fee_amount_cents' => $feeCents,
            'is_paid' => true,
            'billing_month' => null,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'created_by' => $this->user->id,
        ]);
        Payable::factory()->paid()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $userId,
            'type' => 'lesson_package_fee',
            'amount_cents' => $feeCents,
            'month' => null,
            'paid_at' => $paidAt,
            'meta' => [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $teamId,
            ],
        ]);

        return $ulp;
    }
}
