<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\ScheduleJournalMonthService;
use App\Services\TeamUserSyncService;

/**
 * Колонка «кол-во тренировок» в журнале /schedule:
 * счётчик UTSS с актуальным consumes_lesson, пусто при 0.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalConsumingCountColumnFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_header_icon_and_empty_cell_when_scheduled_status(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus(
            $utss,
            $this->occurrenceStatusIdByCode(LessonOccurrenceStatus::CODE_SCHEDULED)
        );

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringNotContainsString('Whoops', $html);

        $this->assertStringContainsString('fa-person-circle-check', $html);
        $this->assertStringContainsString('schedule-consuming-count', $html);
        $this->assertStringContainsString('title="Кол-во посещений"', $html);
        $this->assertStringContainsString('title="Статус оплаты"', $html);
        $this->assertStringContainsString('title="Название абонемента"', $html);
        $this->assertStringContainsString('journal-col-header-hint', $html);
        $this->assertStringContainsString('data-kids-tooltip-hint', $html);

        $cell = $this->journalConsumingCellHtml($html, (int) $student->id);
        $this->assertNotSame('', $cell);
        $this->assertStringContainsString('data-journal-consuming-count="0"', $cell);
        $this->assertSame('', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_empty_cell_when_occurrence_has_no_status(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->createTrialUtss($student, $team, '2026-08-03');

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame('', $this->journalConsumingCellText($html, (int) $student->id));
        $this->assertStringContainsString(
            'data-journal-consuming-count="0"',
            $this->journalConsumingCellHtml($html, (int) $student->id)
        );
    }

    public function test_cancelled_and_frozen_statuses_are_not_counted(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $cancelled = $this->createTrialUtss($student, $team, '2026-08-03');
        $frozen = $this->createTrialUtss($student, $team, '2026-08-04');
        $this->markUtssOccurrenceStatus($cancelled, $this->occurrenceStatusIdByCode('cancelled'));
        $this->markUtssOccurrenceStatus($frozen, $this->occurrenceStatusIdByCode('frozen'));

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame('', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_counts_attended_and_not_attended_but_not_scheduled(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $attended = $this->createTrialUtss($student, $team, '2026-08-03');
        $notAttended = $this->createTrialUtss($student, $team, '2026-08-04');
        $scheduled = $this->createTrialUtss($student, $team, '2026-08-05');

        $this->markUtssOccurrenceStatus($attended, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($notAttended, $this->occurrenceStatusIdByCode('not_attended'));
        $this->markUtssOccurrenceStatus(
            $scheduled,
            $this->occurrenceStatusIdByCode(LessonOccurrenceStatus::CODE_SCHEDULED)
        );

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $cell = $this->journalConsumingCellHtml($html, (int) $student->id);
        $this->assertStringContainsString('data-journal-consuming-count="2"', $cell);
        $this->assertSame('2', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_custom_consumes_lesson_status_is_counted(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $custom = $this->createCustomOccurrenceStatus('Списание');
        $custom->consumes_lesson = true;
        $custom->save();

        $utss = $this->createTrialUtss($student, $team, '2026-08-10');
        $this->markUtssOccurrenceStatus($utss, (int) $custom->id);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_occurrences_in_other_month_are_not_counted(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $july = $this->createTrialUtss($student, $team, '2026-07-20');
        $august = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($july, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($august, (int) $this->visitedStatusId);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_multiple_occurrences_on_same_day_are_counted_separately(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $first = $this->createTrialUtss($student, $team, '2026-08-03');
        $secondSlot = \App\Models\TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '18:00:00',
            'time_end' => '19:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => true,
        ]);
        $second = UserTeamScheduleSlot::query()->create([
            'partner_id' => $student->partner_id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $secondSlot->id,
            'starts_at' => '2026-08-03',
            'ends_at' => '2026-08-03',
            'is_trial_lesson' => true,
            'trial_lessons_total' => 1,
            'trial_lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);
        $this->markUtssOccurrenceStatus($first, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($second, (int) $this->visitedStatusId);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();

        $this->assertSame('2', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_team_filter_counts_only_that_team(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $utssA = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $utssB = $this->createTrialUtss($student, $teamB, '2026-08-04');
        $this->markUtssOccurrenceStatus($utssA, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($utssB, (int) $this->visitedStatusId);

        $allHtml = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertSame('2', $this->journalConsumingCellText($allHtml, (int) $student->id));

        $teamHtml = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $teamA->id,
        ]))->assertOk()->getContent();
        $this->assertSame('1', $this->journalConsumingCellText($teamHtml, (int) $student->id));
    }

    public function test_service_counts_only_consumes_lesson_flag(): void
    {
        $counts = app(ScheduleJournalMonthService::class)->consumingCountsByUser([
            '1_2026-08-01' => [
                ['user_id' => 1, 'consumes_lesson' => true],
                ['user_id' => 1, 'consumes_lesson' => false],
                ['user_id' => 1, 'consumes_lesson' => true],
            ],
            '2_2026-08-01' => [
                ['user_id' => 2, 'consumes_lesson' => false],
            ],
        ]);

        $this->assertSame(2, $counts[1]);
        $this->assertArrayNotHasKey(2, $counts);
    }

    /**
     * P2: страница → AJAX update «Посетил» → JSON consuming_count без reload,
     * повторный GET показывает «1».
     */
    public function test_consuming_count_workflow_page_ajax_update_visible_without_reload(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['name' => 'Счётчик', 'lastname' => 'Workflow']);
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('id="cellEditModal"', false)
            ->assertSee('id="cellEditForm"', false)
            ->assertSee('js/schedule-journal.js', false)
            ->assertSee('schedule-consuming-count', false)
            ->assertSee($student->full_name, false);
        $this->assertSame('', $this->journalConsumingCellText($html, (int) $student->id));

        $save = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ]);
        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 1)
            ->assertJsonPath('result.utss_id', (int) $utss->id);
        $this->assertNotSame('', (string) $save->json('message'));

        $pageAfter = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $pageAfter->assertOk();
        $afterHtml = (string) $pageAfter->getContent();
        $this->assertNotSame('', trim($afterHtml));
        $this->assertSame('1', $this->journalConsumingCellText($afterHtml, (int) $student->id));
        $pageAfter->assertSee($student->full_name, false);
    }
}
