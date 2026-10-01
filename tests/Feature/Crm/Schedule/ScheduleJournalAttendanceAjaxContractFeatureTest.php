<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Team;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Services\TeamUserSyncService;

/**
 * AJAX-контракт средней посещаемости: JSON result.attendance, 422 по полям, фильтр групп.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalAttendanceSummaryFeatureTest
 */
final class ScheduleJournalAttendanceAjaxContractFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_marking_visited_returns_attendance_for_the_selected_group_only(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $changing = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $otherGroup = $this->createTrialUtss($student, $teamB, '2026-08-04');
        $this->markUtssOccurrenceStatus($otherGroup, (int) $this->visitedStatusId);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $changing->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
                'journal_team_ids' => [(string) $teamA->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.')
            ->assertJsonPath('result.attendance.attended_total', 1)
            ->assertJsonPath('result.attendance.trainings_count', 1)
            ->assertJsonPath('result.attendance.average_label', '1,0')
            ->assertJsonPath('result.attendance.by_date.2026-08-03', 1)
            ->assertJsonPath('result.attendance.by_date.2026-08-04', 0);

        $this->assertEquals(1.0, $response->json('result.attendance.average'));
        $this->assertIsArray($response->json('result.attendance.by_date'));
        $this->assertCount(31, $response->json('result.attendance.by_date'));
    }

    public function test_without_team_ids_all_filter_counts_every_group(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $changing = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $otherGroup = $this->createTrialUtss($student, $teamB, '2026-08-04');
        $this->markUtssOccurrenceStatus($otherGroup, (int) $this->visitedStatusId);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $changing->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('result.attendance.attended_total', 2)
            ->assertJsonPath('result.attendance.trainings_count', 2)
            ->assertJsonPath('result.attendance.average_label', '1,0')
            ->assertJsonPath('result.attendance.by_date.2026-08-03', 1)
            ->assertJsonPath('result.attendance.by_date.2026-08-04', 1);
    }

    public function test_switching_away_from_visited_drops_the_training_in_json(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->occurrenceStatusIdByCode('not_attended'),
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.attendance.attended_total', 0)
            ->assertJsonPath('result.attendance.trainings_count', 0)
            ->assertJsonPath('result.attendance.average', null)
            ->assertJsonPath('result.attendance.average_label', '—')
            ->assertJsonPath('result.attendance.by_date.2026-08-03', 0);
    }

    public function test_deleting_the_only_visit_returns_dash_and_zero_day(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-03',
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.attendance.trainings_count', 0)
            ->assertJsonPath('result.attendance.average_label', '—')
            ->assertJsonPath('result.attendance.by_date.2026-08-03', 0);
    }

    public function test_placing_flexible_visit_returns_attendance_and_scheduled_does_not_count(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);
        $scheduledId = LessonOccurrenceStatus::scheduledIdForPartner((int) $this->partner->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $scheduledId,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.attendance.trainings_count', 0)
            ->assertJsonPath('result.attendance.average_label', '—')
            ->assertJsonPath('result.attendance.by_date.2026-08-03', 0);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-04',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.attendance.attended_total', 1)
            ->assertJsonPath('result.attendance.trainings_count', 1)
            ->assertJsonPath('result.attendance.average_label', '1,0')
            ->assertJsonPath('result.attendance.by_date.2026-08-03', 0)
            ->assertJsonPath('result.attendance.by_date.2026-08-04', 1);
    }

    public function test_missing_status_returns_422_under_the_status_field(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_occurrence_status_id'])
            ->assertJsonPath('errors.lesson_occurrence_status_id.0', 'Выберите статус.');
        $this->assertArrayNotHasKey('attendance', (array) $response->json('result'));
        $this->assertSame(
            0,
            UserLessonOccurrenceStatusEvent::query()->where('user_id', $student->id)->count()
        );
    }

    public function test_bad_date_returns_422_under_the_date_field(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '03.08.2026',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_date']);
    }

    public function test_unknown_lesson_returns_422_under_utss_id(): void
    {
        [$student] = $this->makeStudentWithTeam();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => 999999999,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(422)->assertJsonValidationErrors(['utss_id']);
    }

    public function test_journal_year_and_month_reject_invalid_values_with_field_errors(): void
    {
        $this->getJson(route('schedule.index', ['year' => 'нет', 'month' => '08']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);

        $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '13']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_wrong_methods_on_visit_endpoints_are_not_success(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        foreach (['get', 'patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('schedule.update'));
            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertNotSame(200, $response->getStatusCode(), $method);
            $this->assertContains($response->getStatusCode(), [404, 405], $method);
        }

        $getDestroy = $this->get(route('schedule.occurrence.destroy', $utss));
        $this->assertNotSame(500, $getDestroy->getStatusCode());
        $this->assertNotSame(200, $getDestroy->getStatusCode());
        $this->assertContains($getDestroy->getStatusCode(), [404, 405]);
    }
}
