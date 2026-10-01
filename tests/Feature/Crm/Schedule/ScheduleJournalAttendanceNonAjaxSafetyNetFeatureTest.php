<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserLessonOccurrenceStatusEvent;

/**
 * Non-AJAX safety-net средней посещаемости: POST без X-Requested-With → 302, запись в БД,
 * страница журнала показывает среднее и «Итого».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalAttendanceSummaryFeatureTest
 */
final class ScheduleJournalAttendanceNonAjaxSafetyNetFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_saving_visited_without_ajax_redirects_and_journal_shows_the_average(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'journal_team_filter' => (string) $team->id,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Статус занятия сохранён.');
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);

        $html = $this->journalPage(2026, '08', $team->id);
        $this->assertStringContainsString('>1,0<', $html);
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-03'));
    }

    public function test_saving_without_status_redirects_with_field_error_and_does_not_count_a_visit(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->from(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]))->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['lesson_occurrence_status_id']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(
            0,
            UserLessonOccurrenceStatusEvent::query()->where('user_id', $student->id)->count()
        );

        $html = $this->journalPage(2026, '08', $team->id);
        $this->assertStringContainsString('>—<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
    }

    public function test_deleting_visit_without_ajax_redirects_and_journal_shows_dash(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);

        $response = $this->delete(route('schedule.occurrence.destroy', $utss), [
            '_token' => csrf_token(),
            'occurrence_date' => '2026-08-03',
            'journal_team_filter' => (string) $team->id,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());

        $html = $this->journalPage(2026, '08', $team->id);
        $this->assertStringContainsString('>—<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
    }

    public function test_placing_flexible_visit_without_ajax_redirects_and_journal_shows_the_average(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $scheduled = $this->post(route('schedule.abonement.place-flexible', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id),
        ]);
        $scheduled->assertStatus(302);
        $scheduled->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $scheduled->getStatusCode());

        $afterScheduled = $this->journalPage(2026, '08', $team->id);
        $this->assertStringContainsString('>—<', $afterScheduled);
        $this->assertSame('', $this->attendanceDayText($afterScheduled, '2026-08-03'));

        $visited = $this->post(route('schedule.abonement.place-flexible', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-04',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);
        $visited->assertStatus(302);
        $visited->assertRedirect(route('schedule.index'));

        $html = $this->journalPage(2026, '08', $team->id);
        $this->assertStringContainsString('>1,0<', $html);
        $this->assertSame('', $this->attendanceDayText($html, '2026-08-03'));
        $this->assertSame('1', $this->attendanceDayText($html, '2026-08-04'));
    }

    public function test_invalid_journal_month_redirects_with_field_error(): void
    {
        $response = $this->from(route('schedule.index'))
            ->get(route('schedule.index', ['year' => 'нет', 'month' => '13']));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['year', 'month']);
    }

    private function journalPage(int $year, string $month, int|string $team): string
    {
        $html = (string) $this->get(route('schedule.index', [
            'year' => $year,
            'month' => $month,
            'team' => $team,
        ]))->assertOk()->getContent();
        $this->assertStringNotContainsString('Whoops', $html);

        return $html;
    }

    private function attendanceDayText(string $html, string $date): string
    {
        if (! preg_match('/<tfoot\b[^>]*>[\s\S]*?<\/tfoot>/', $html, $foot)) {
            return '';
        }

        if (! preg_match(
            '/<td\b[^>]*data-date="'.preg_quote($date, '/').'"[^>]*>([\s\S]*?)<\/td>/',
            $foot[0],
            $cell
        )) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($cell[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
