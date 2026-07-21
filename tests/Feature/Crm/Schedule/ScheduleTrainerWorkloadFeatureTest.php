<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\ScheduleUser;
use App\Models\LessonOccurrenceStatus;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Schedule\TrainerWorkloadReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Функциональность отчёта «Нагрузка тренеров»: агрегация, фильтры, режим групп/сумм.
 */
final class ScheduleTrainerWorkloadFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_workload_page_ok_with_default_period_and_groups_switch_off(): void
    {
        $this->makeTrainerProfile('Тренер для отчёта');

        $response = $this->get(route('schedule.trainer-workload'));

        $response->assertOk()
            ->assertSee('Нагрузка тренеров', false)
            ->assertSee('Тренер для отчёта', false)
            ->assertSee('trainer-workload-app', false)
            ->assertSee('trainer-workload-date-from', false)
            ->assertDontSee('trainer-workload-period-badge', false)
            ->assertDontSee('id="trainer-workload-show-groups" checked', false);

        $expectedFrom = Carbon::today()->subDays(29)->toDateString();
        $expectedTo = Carbon::today()->toDateString();

        $response->assertSee('value="' . $expectedFrom . '"', false)
            ->assertSee('value="' . $expectedTo . '"', false);
    }

    public function test_service_aggregates_distinct_dates_per_weekday_and_team(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Тренер А');
        $otherTrainer = $this->makeTrainerProfile('Тренер Б');

        $mondayOne = '2026-05-04';
        $mondayTwo = '2026-05-11';
        $tuesday = '2026-05-05';

        $studentTwo = $this->makeStudent($team->id);

        foreach ([$mondayOne, $mondayTwo] as $date) {
            $this->createVisitedScheduleEntry($student->id, $trainer->id, $date);
        }

        $this->createVisitedScheduleEntry($studentTwo->id, $trainer->id, $mondayOne);
        $this->createVisitedScheduleEntry($student->id, $trainer->id, $tuesday);
        $this->createVisitedScheduleEntry($student->id, $otherTrainer->id, $mondayOne);

        $notVisited = LessonOccurrenceStatus::query()
            ->forPartner($this->partner->id)
            ->where('code', 'not_attended')
            ->value('id');

        ScheduleUser::query()->create([
            'user_id' => $student->id,
            'date' => $mondayOne,
            'lesson_occurrence_status_id' => $notVisited,
            'trainer_profile_id' => $trainer->id,
        ]);

        $report = app(TrainerWorkloadReportService::class)->build(
            $this->partner->id,
            '2026-05-01',
            '2026-05-31',
            true,
        );

        $trainerCells = $report['cells'][$trainer->id];
        $this->assertCount(1, $trainerCells[1]);
        $this->assertSame($team->title, $trainerCells[1][0]['team_title']);
        $this->assertSame(2, $trainerCells[1][0]['dates_count']);

        $this->assertCount(1, $trainerCells[2]);
        $this->assertSame(1, $trainerCells[2][0]['dates_count']);

        $otherCells = $report['cells'][$otherTrainer->id];
        $this->assertCount(1, $otherCells[1]);
        $this->assertSame(1, $otherCells[1][0]['dates_count']);
    }

    public function test_only_visited_with_trainer_profile_are_counted(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-07';

        $this->createVisitedScheduleEntry($student->id, $trainer->id, $date);

        ScheduleUser::query()->create([
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => null,
        ]);

        $notVisitedId = LessonOccurrenceStatus::query()
            ->forPartner($this->partner->id)
            ->where('code', 'not_attended')
            ->value('id');

        ScheduleUser::query()->create([
            'user_id' => $student->id,
            'date' => '2026-05-08',
            'lesson_occurrence_status_id' => $notVisitedId,
            'trainer_profile_id' => $trainer->id,
        ]);

        $report = app(TrainerWorkloadReportService::class)->build(
            $this->partner->id,
            '2026-05-01',
            '2026-05-31',
            true,
        );

        $this->assertCount(1, $report['cells'][$trainer->id][4]);
        $this->assertSame(1, $report['cells'][$trainer->id][4][0]['dates_count']);
        $this->assertSame($team->title, $report['cells'][$trainer->id][4][0]['team_title']);
    }

    public function test_student_without_team_shown_as_bez_gruppy(): void
    {
        $student = $this->makeStudent(null);
        $trainer = $this->makeTrainerProfile('Тренер solo');

        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-05');

        $report = app(TrainerWorkloadReportService::class)->build(
            $this->partner->id,
            '2026-05-01',
            '2026-05-31',
            true,
        );

        $this->assertSame('Без группы', $report['cells'][$trainer->id][2][0]['team_title']);
    }

    public function test_inactive_trainer_listed_with_empty_cells(): void
    {
        $active = $this->makeTrainerProfile('Активный');
        $inactiveUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'is_enabled' => 1,
        ]);
        $inactive = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $inactiveUser->id,
            'is_enabled' => false,
        ]);

        $report = app(TrainerWorkloadReportService::class)->build(
            $this->partner->id,
            Carbon::today()->subDays(29)->toDateString(),
            Carbon::today()->toDateString(),
            true,
        );

        $ids = array_column($report['trainers'], 'id');
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);

        foreach ($report['cells'][$active->id] as $items) {
            $this->assertSame([], $items);
        }
    }

    public function test_period_boundary_excludes_out_of_range_entries(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();

        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-04');
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-04-28');

        $report = app(TrainerWorkloadReportService::class)->build(
            $this->partner->id,
            '2026-05-01',
            '2026-05-31',
            true,
        );

        $this->assertCount(1, $report['cells'][$trainer->id][1]);
        $this->assertSame(1, $report['cells'][$trainer->id][1][0]['dates_count']);
    }

    public function test_service_computes_row_column_and_grand_totals(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Тренер А');

        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-04');
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-11');
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-05');

        $report = app(TrainerWorkloadReportService::class)->build(
            $this->partner->id,
            '2026-05-01',
            '2026-05-31',
            true,
        );

        $this->assertSame(3, $report['row_totals'][$trainer->id][0]['dates_count']);
        $this->assertSame($team->title, $report['row_totals'][$trainer->id][0]['team_title']);

        $this->assertSame(2, $report['column_totals'][1][0]['dates_count']);
        $this->assertSame(1, $report['column_totals'][2][0]['dates_count']);

        $this->assertSame(3, $report['grand_total'][0]['dates_count']);
    }

    public function test_service_without_groups_collapses_cells_to_sum(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();

        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-04');
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-11');
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-05');

        $report = app(TrainerWorkloadReportService::class)->build(
            $this->partner->id,
            '2026-05-01',
            '2026-05-31',
            false,
        );

        $this->assertFalse($report['show_groups']);
        $this->assertSame(2, $report['cells'][$trainer->id][1][0]['dates_count']);
        $this->assertSame(1, $report['cells'][$trainer->id][2][0]['dates_count']);
        $this->assertSame('', $report['cells'][$trainer->id][1][0]['team_title']);
        $this->assertSame(3, $report['row_totals'][$trainer->id][0]['dates_count']);
        $this->assertSame(3, $report['grand_total'][0]['dates_count']);
    }

    public function test_validation_rejects_inverted_period(): void
    {
        $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-05-10',
            'date_to' => '2026-05-01',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    public function test_validation_rejects_period_longer_than_366_days(): void
    {
        $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2024-01-01',
            'date_to' => '2026-06-01',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    public function test_data_endpoint_returns_table_html_with_groups_and_without(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Сидоров');

        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-05');

        $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'show_groups' => '1',
        ]))
            ->assertOk()
            ->assertJsonPath('show_groups', true)
            ->assertJsonPath('date_from', '2026-05-01')
            ->assertJsonPath('date_to', '2026-05-31')
            ->assertJsonStructure(['table_html']);

        $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))
            ->assertOk()
            ->assertJsonPath('show_groups', false);

        $htmlWithGroups = (string) $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'show_groups' => '1',
        ]))->json('table_html');

        $htmlSumsOnly = (string) $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'show_groups' => '0',
        ]))->json('table_html');

        $this->assertStringContainsString('Сидоров', $htmlWithGroups);
        $this->assertStringContainsString($team->title, $htmlWithGroups);
        $this->assertStringContainsString('trainer-workload-chip', $htmlWithGroups);
        $this->assertStringContainsString('trainer-workload-sum-only', $htmlSumsOnly);
        $this->assertStringNotContainsString($team->title, $htmlSumsOnly);
    }

    public function test_data_does_not_include_foreign_partner_visits(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => (int) \App\Models\Role::query()->where('name', 'user')->value('id'),
        ]);
        $foreignTrainer = $this->makeTrainerProfile('Чужой тренер', $this->foreignPartner->id);

        $this->createVisitedScheduleEntry($foreignStudent->id, $foreignTrainer->id, '2026-05-05');

        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Свой тренер');
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-05');

        $html = (string) $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'show_groups' => '1',
        ]))->json('table_html');

        $this->assertStringContainsString('Свой тренер', $html);
        $this->assertStringContainsString($team->title, $html);
        $this->assertStringNotContainsString('Чужой тренер', $html);
    }

    public function test_workload_page_without_groups_shows_sum_only(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Петров');

        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-05');

        $this->get(route('schedule.trainer-workload', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'show_groups' => '0',
        ]))
            ->assertOk()
            ->assertSee('trainer-workload-sum-only', false)
            ->assertDontSee($team->title, false)
            ->assertSee('Показывать группы', false);
    }

    public function test_workload_page_with_groups_shows_team_chips(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer('Иванов');

        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-05');

        $this->get(route('schedule.trainer-workload', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'show_groups' => '1',
        ]))
            ->assertOk()
            ->assertSee($team->title, false)
            ->assertSee('trainer-workload-chip__count', false)
            ->assertSee('Иванов', false)
            ->assertSee('>Итого</', false);
    }

    public function test_custom_period_is_applied_on_page_and_data(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-03-15');
        $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-06-01');

        $this->get(route('schedule.trainer-workload', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'show_groups' => '0',
        ]))
            ->assertOk()
            ->assertSee('value="2026-03-01"', false)
            ->assertSee('value="2026-03-31"', false);

        $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'show_groups' => '0',
        ]))
            ->assertOk()
            ->assertJsonPath('date_from', '2026-03-01')
            ->assertJsonPath('date_to', '2026-03-31');

        $html = (string) $this->getJson(route('schedule.trainer-workload.data', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'show_groups' => '0',
        ]))->json('table_html');

        $this->assertMatchesRegularExpression('/trainer-workload-sum-only[^>]*>1</', $html);
    }

    public function test_month_preset_links_render_three_months_oldest_to_newest(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            $this->makeTrainerProfile('Тренер для пресетов');

            $presets = $this->trainerWorkloadMonthPresets();
            $this->assertCount(3, $presets);
            $this->assertSame('Март', $presets[0]['label']);
            $this->assertSame('2026-03-01', $presets[0]['date_from']);
            $this->assertSame('2026-03-31', $presets[0]['date_to']);
            $this->assertSame('Апрель', $presets[1]['label']);
            $this->assertSame('Май', $presets[2]['label']);
            $this->assertSame('2026-05-31', $presets[2]['date_to']);

            $response = $this->get(route('schedule.trainer-workload'));
            $content = (string) $response->getContent();

            $response->assertOk()
                ->assertSee('id="trainer-workload-month-presets"', false)
                ->assertSee('trainer-workload-month-presets-sep', false)
                ->assertSee('data-trainer-workload-month', false);

            foreach ($presets as $preset) {
                $response->assertSee('data-date-from="' . $preset['date_from'] . '"', false)
                    ->assertSee('data-date-to="' . $preset['date_to'] . '"', false)
                    ->assertSee('>' . $preset['label'] . '</a>', false);
            }

            $posOldest = strpos($content, '>' . $presets[0]['label'] . '</a>');
            $posMiddle = strpos($content, '>' . $presets[1]['label'] . '</a>');
            $posNewest = strpos($content, '>' . $presets[2]['label'] . '</a>');
            $this->assertNotFalse($posOldest);
            $this->assertNotFalse($posMiddle);
            $this->assertNotFalse($posNewest);
            $this->assertLessThan($posMiddle, $posOldest);
            $this->assertLessThan($posNewest, $posMiddle);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_active_month_link_when_period_matches_full_calendar_month(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            $this->makeTrainerProfile('Тренер активный месяц');

            $mayPreset = $this->trainerWorkloadMonthPresets()[2];

            $this->get(route('schedule.trainer-workload', [
                'date_from' => $mayPreset['date_from'],
                'date_to' => $mayPreset['date_to'],
                'show_groups' => '0',
            ]))
                ->assertOk()
                ->assertSee('class="trainer-workload-month-link is-active"', false)
                ->assertSee('aria-current="true"', false)
                ->assertSee('value="' . $mayPreset['date_from'] . '"', false)
                ->assertSee('value="' . $mayPreset['date_to'] . '"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_no_active_month_link_for_default_rolling_period(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            $this->makeTrainerProfile('Тренер без активного месяца');

            $this->get(route('schedule.trainer-workload'))
                ->assertOk()
                ->assertDontSee('trainer-workload-month-link is-active', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_data_endpoint_ok_for_each_month_preset_period(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        try {
            [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
            $this->createVisitedScheduleEntry($student->id, $trainer->id, '2026-05-10');

            foreach ($this->trainerWorkloadMonthPresets() as $preset) {
                $this->getJson(route('schedule.trainer-workload.data', [
                    'date_from' => $preset['date_from'],
                    'date_to' => $preset['date_to'],
                    'show_groups' => '0',
                ]))
                    ->assertOk()
                    ->assertJsonPath('date_from', $preset['date_from'])
                    ->assertJsonPath('date_to', $preset['date_to']);

                $html = (string) $this->getJson(route('schedule.trainer-workload.data', [
                    'date_from' => $preset['date_from'],
                    'date_to' => $preset['date_to'],
                    'show_groups' => '1',
                ]))
                    ->assertOk()
                    ->json('table_html');

                $this->assertStringContainsString('trainer-workload-table', $html);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_month_preset_rolls_over_year_boundary(): void
    {
        Carbon::setTestNow('2026-01-20 12:00:00');

        try {
            $presets = $this->trainerWorkloadMonthPresets();
            $this->assertSame('Ноябрь', $presets[0]['label']);
            $this->assertSame('2025-11-01', $presets[0]['date_from']);
            $this->assertSame('Декабрь', $presets[1]['label']);
            $this->assertSame('Январь', $presets[2]['label']);
            $this->assertSame('2026-01-31', $presets[2]['date_to']);

            $this->get(route('schedule.trainer-workload'))
                ->assertOk()
                ->assertSee('data-date-from="' . $presets[0]['date_from'] . '"', false)
                ->assertSee('>' . $presets[2]['label'] . '</a>', false);
        } finally {
            Carbon::setTestNow();
        }
    }
}
