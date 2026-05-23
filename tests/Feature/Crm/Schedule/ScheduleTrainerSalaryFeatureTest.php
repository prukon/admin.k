<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;
use App\Models\TrainerProfile;
use App\Services\Schedule\TrainerWorkloadReportService;
use Illuminate\Support\Facades\DB;

/**
 * ЗП тренеров: черновик, подсчёт тренировок, слепки с версиями.
 */
final class ScheduleTrainerSalaryFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryView();
        $this->grantTrainerSalaryManage();
    }

    public function test_active_trainers_including_zero_trainings_appear_in_draft(): void
    {
        $withVisits = $this->makeTrainerProfile('С визитами');
        $zero = $this->makeTrainerProfile('Без визитов');
        $zero->update([
            'default_base_salary' => 15000,
            'default_rate_per_training' => 500,
        ]);

        [$student, , $trainerWithVisits] = $this->makeStudentTeamAndTrainer('С визитами');
        $this->createVisitedScheduleEntry($student->id, $trainerWithVisits->id, '2026-05-12');

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();

        $rows = collect($response->json('rows'));
        $this->assertTrue($rows->contains(fn (array $row) => str_contains($row['trainer_name'], 'С визитами')));
        $this->assertTrue($rows->contains(fn (array $row) => str_contains($row['trainer_name'], 'Без визитов')));

        $zeroRow = $rows->first(fn (array $row) => str_contains($row['trainer_name'], 'Без визитов'));
        $this->assertNotNull($zeroRow);
        $this->assertSame(0, $zeroRow['trainings_count']);
        $this->assertSame('15000.00', $zeroRow['base_salary']);
    }

    public function test_trainings_count_matches_workload_row_total_with_double_group_day(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Тренер группы');
        $otherTeam = \App\Models\Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа B']);
        $studentTwo = $this->makeStudent($otherTeam->id);

        $date = '2026-05-06';
        $this->createVisitedScheduleEntry($student->id, $trainer->id, $date);
        $this->createVisitedScheduleEntry($studentTwo->id, $trainer->id, $date);

        $workload = app(TrainerWorkloadReportService::class)->trainerRowTrainingsTotals(
            $this->partner->id,
            '2026-05-01',
            '2026-05-31',
        );

        $this->assertSame(2, $workload[$trainer->id] ?? 0);

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]));
        $rows = $response->json('rows');
        $row = collect($rows)->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['trainings_count']);
    }

    public function test_draft_patch_persists_and_recalculates_total(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер черновик');
        $trainer->update([
            'default_base_salary' => 10000,
            'default_rate_per_training' => 200,
        ]);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 1500,
            'deductions' => 200,
        ])
            ->assertOk()
            ->assertJsonPath('row.bonuses', '1500.00')
            ->assertJsonPath('row.total', '11300.00');

        $draft = TrainerSalaryDraftLine::query()
            ->whereHas('period', fn ($q) => $q
                ->where('partner_id', $this->partner->id)
                ->where('year', 2026)
                ->where('month', 5))
            ->where('trainer_profile_id', $trainer->id)
            ->first();

        $this->assertNotNull($draft);
        $this->assertSame('1500.00', (string) $draft->bonuses);
        $this->assertSame('11300.00', (string) $draft->total);
    }

    public function test_form_one_creates_versioned_snapshots(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер слепок');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('snapshot.version', 1);

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('snapshot.version', 2);

        $count = TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->count();

        $this->assertSame(2, $count);

        $latest = TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->orderByDesc('version')
            ->first();

        $this->assertSame((int) $this->user->id, (int) $latest->formed_by_user_id);
        $this->assertNotNull($latest->formed_at);
    }

    public function test_form_all_creates_snapshots_with_shared_batch_id(): void
    {
        $this->makeTrainerProfile('Тренер 1');
        $this->makeTrainerProfile('Тренер 2');

        $response = $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $batchId = $response->json('batch_id');
        $this->assertNotEmpty($batchId);
        $this->assertSame(2, $response->json('snapshots_count'));

        $snapshots = TrainerSalarySnapshot::query()
            ->where('batch_id', $batchId)
            ->get();

        $this->assertCount(2, $snapshots);
        $this->assertTrue($snapshots->every(fn ($s) => (int) $s->version === 1));
    }

    public function test_foreign_partner_trainer_not_accessible(): void
    {
        $foreignTrainer = $this->makeTrainerProfile('Чужой', $this->foreignPartner->id);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $foreignTrainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 1,
        ])->assertNotFound();
    }

    public function test_validation_errors_returned_for_invalid_money_field(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер валидация');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => -5,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bonuses']);
    }

    public function test_total_formula_includes_trainings_count_and_rate(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer('Тренер формула');
        $trainer->update([
            'default_base_salary' => 10000,
            'default_rate_per_training' => 300,
        ]);

        foreach (['2026-05-03', '2026-05-10', '2026-05-17'] as $date) {
            $this->createVisitedScheduleEntry($student->id, $trainer->id, $date);
        }

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();

        $row = collect($response->json('rows'))->firstWhere('trainer_profile_id', $trainer->id);
        $this->assertNotNull($row);
        $this->assertSame(3, $row['trainings_count']);
        $this->assertSame('900.00', $row['trainings_amount']);
        $this->assertSame('10900.00', $row['total']);
    }

    public function test_draft_patch_updates_salary_fields_and_comment(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер поля');

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'base_salary' => 25000,
            'rate_per_training' => 750,
            'comment' => 'Премия за соревнования',
        ])
            ->assertOk()
            ->assertJsonPath('row.base_salary', '25000.00')
            ->assertJsonPath('row.rate_per_training', '750.00')
            ->assertJsonPath('row.comment', 'Премия за соревнования');

        $draft = TrainerSalaryDraftLine::query()
            ->whereHas('period', fn ($q) => $q
                ->where('partner_id', $this->partner->id)
                ->where('year', 2026)
                ->where('month', 5))
            ->where('trainer_profile_id', $trainer->id)
            ->first();

        $this->assertNotNull($draft);
        $this->assertSame('25000.00', (string) $draft->base_salary);
        $this->assertSame('750.00', (string) $draft->rate_per_training);
        $this->assertSame('Премия за соревнования', $draft->comment);
    }

    public function test_disabled_trainer_excluded_from_report(): void
    {
        $active = $this->makeTrainerProfile('Активный тренер');
        $disabled = $this->makeTrainerProfile('Отключённый тренер');
        $disabled->update(['is_enabled' => false]);

        $response = $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();

        $ids = collect($response->json('rows'))->pluck('trainer_profile_id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
    }

    public function test_form_one_snapshot_copies_draft_amounts(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер копия');
        $trainer->update([
            'default_base_salary' => 12000,
            'default_rate_per_training' => 400,
        ]);

        $this->patchJson(route('schedule.trainer-salary.draft.update', $trainer), [
            'year' => 2026,
            'month' => 5,
            'bonuses' => 2000,
            'deductions' => 500,
        ])->assertOk();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $snapshot = TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->orderByDesc('version')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame('12000.00', (string) $snapshot->base_salary);
        $this->assertSame('400.00', (string) $snapshot->rate_per_training);
        $this->assertSame('2000.00', (string) $snapshot->bonuses);
        $this->assertSame('500.00', (string) $snapshot->deductions);
        $this->assertSame('13500.00', (string) $snapshot->total);
    }

    public function test_data_returns_latest_snapshot_after_form_one(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер слепок в data');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $row = collect($this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->json('rows'))
            ->firstWhere('trainer_profile_id', $trainer->id);

        $this->assertNotNull($row);
        $this->assertNotNull($row['latest_snapshot']);
        $this->assertSame(1, $row['latest_snapshot']['version']);
        $this->assertSame('0.00', $row['latest_snapshot']['total']);
    }

    public function test_first_data_load_creates_period_and_draft_lines(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер период');

        $this->assertDatabaseMissing('trainer_salary_periods', [
            'partner_id' => $this->partner->id,
            'year' => 2026,
            'month' => 5,
        ]);

        $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk();

        $period = TrainerSalaryPeriod::query()
            ->where('partner_id', $this->partner->id)
            ->where('year', 2026)
            ->where('month', 5)
            ->first();

        $this->assertNotNull($period);

        $this->assertDatabaseHas('trainer_salary_draft_lines', [
            'trainer_salary_period_id' => $period->id,
            'trainer_profile_id' => $trainer->id,
        ]);
    }

    public function test_form_one_foreign_trainer_returns_not_found(): void
    {
        $foreignTrainer = $this->makeTrainerProfile('Чужой POST', $this->foreignPartner->id);

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $foreignTrainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertNotFound();
    }

    public function test_snapshot_endpoints_validate_year_and_month(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер валидация слепок');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year', 'month']);

        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_table_html_includes_manage_actions_when_can_manage(): void
    {
        $this->makeTrainerProfile('Тренер manage html');

        $html = (string) $this->getJson(route('schedule.trainer-salary.data', ['year' => 2026, 'month' => 5]))
            ->assertOk()
            ->assertJsonPath('can_manage', true)
            ->json('table_html');

        $this->assertStringContainsString('trainer-salary-form-one-btn', $html);
        $this->assertStringContainsString('title="Расчет ЗП"', $html);
        $this->assertStringContainsString('>Расчет</', $html);
    }
}
