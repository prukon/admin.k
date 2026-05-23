<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;

/**
 * Листы ЗП: список слепков, деталь, фильтр актуальных, изоляция партнёра.
 */
final class ScheduleTrainerSalarySheetsFeatureTest extends ScheduleTrainerSalaryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantTrainerSalaryView();
        $this->grantTrainerSalaryManage();
    }

    public function test_sheets_page_lists_batch_and_single_snapshots(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер лист');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $response = $this->getJson(route('schedule.trainer-salary-sheets.data', [
            'year' => 2026,
            'month' => 5,
        ]))->assertOk();

        $sheets = $response->json('sheets');
        $this->assertGreaterThanOrEqual(2, count($sheets));

        $hasBatch = collect($sheets)->contains(fn (array $s) => ($s['kind'] ?? '') === 'batch');
        $hasSingle = collect($sheets)->contains(fn (array $s) => ($s['kind'] ?? '') === 'snapshot');
        $this->assertTrue($hasBatch);
        $this->assertTrue($hasSingle);

        $batch = collect($sheets)->firstWhere('kind', 'batch');
        $this->assertNotEmpty($batch['grand_total'] ?? null);
        $this->assertGreaterThan(0, (int) ($batch['trainers_count'] ?? 0));
    }

    public function test_latest_only_filter_and_latest_by_trainer_block(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер версии');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 5,
        ])->assertOk();

        $all = $this->getJson(route('schedule.trainer-salary-sheets.data', [
            'year' => 2026,
            'month' => 5,
        ]))->json('sheets');

        $filteredResponse = $this->getJson(route('schedule.trainer-salary-sheets.data', [
            'year' => 2026,
            'month' => 5,
            'latest_only' => 1,
        ]))->assertOk();

        $this->assertGreaterThan(count($filteredResponse->json('sheets')), count($all));

        $latest = collect($filteredResponse->json('latest_by_trainer'));
        $this->assertTrue($latest->contains(fn (array $r) => str_contains($r['trainer_name'], 'Тренер версии')));
        $row = $latest->first(fn (array $r) => str_contains($r['trainer_name'], 'Тренер версии'));
        $this->assertSame(2, $row['version']);
    }

    public function test_batch_show_page_renders_readonly_table(): void
    {
        $this->makeTrainerProfile('Тренер A');
        $this->makeTrainerProfile('Тренер B');

        $batchResponse = $this->postJson(route('schedule.trainer-salary.snapshots.form-all'), [
            'year' => 2026,
            'month' => 6,
        ])->assertOk();

        $batchId = $batchResponse->json('batch_id');

        $this->get(route('schedule.trainer-salary-sheets.batch.show', ['batchId' => $batchId]))
            ->assertOk()
            ->assertSee('Лист ЗП', false)
            ->assertSee('Полный лист', false)
            ->assertSee('trainer-salary-table--readonly', false)
            ->assertSee('К списку листов', false);
    }

    public function test_snapshot_show_page_ok(): void
    {
        $trainer = $this->makeTrainerProfile('Тренер слепок UI');

        $this->postJson(route('schedule.trainer-salary.snapshots.form-one', $trainer), [
            'year' => 2026,
            'month' => 7,
        ])->assertOk();

        $snapshotId = (int) TrainerSalarySnapshot::query()
            ->where('trainer_profile_id', $trainer->id)
            ->max('id');

        $this->get(route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => $snapshotId]))
            ->assertOk()
            ->assertSee('По тренеру', false)
            ->assertSee('v1', false);
    }

    public function test_foreign_snapshot_show_returns_404(): void
    {
        $foreignTrainer = $this->makeTrainerProfile('Чужой', $this->foreignPartner->id);

        $period = TrainerSalaryPeriod::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'year' => 2026,
            'month' => 8,
        ]);

        $snapshot = TrainerSalarySnapshot::query()->create([
            'trainer_salary_period_id' => $period->id,
            'trainer_profile_id' => $foreignTrainer->id,
            'version' => 1,
            'batch_id' => null,
            'base_salary' => 1000,
            'rate_per_training' => 100,
            'trainings_count' => 1,
            'trainings_amount' => 100,
            'bonuses' => 0,
            'deductions' => 0,
            'comment' => null,
            'total' => 1100,
            'formed_by_user_id' => $this->foreignUser->id,
            'formed_at' => now(),
        ]);

        $this->get(route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => $snapshot->id]))
            ->assertNotFound();
    }
}
