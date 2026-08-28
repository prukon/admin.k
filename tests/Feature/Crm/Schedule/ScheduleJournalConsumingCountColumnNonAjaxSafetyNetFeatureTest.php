<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\Auth;

/**
 * P1: non-AJAX safety-net колонки «кол-во тренировок»:
 * POST/DELETE без X-Requested-With → 302 на /schedule, запись в БД, не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalConsumingCountColumnNonAjaxSafetyNetFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_update_non_ajax_redirects_persists_status_and_column_shows_count(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'journal_team_filter' => 'all',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Статус занятия сохранён.');
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.update'), [
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
    }

    public function test_destroy_non_ajax_redirects_and_column_becomes_empty(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);

        $response = $this->delete(route('schedule.occurrence.destroy', $utss), [
            '_token' => csrf_token(),
            'occurrence_date' => '2026-08-03',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Занятие удалено.');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utss->id]);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertSame('', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_place_flexible_non_ajax_redirects_and_column_shows_count(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $response = $this->post(route('schedule.abonement.place-flexible', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-10',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Занятие из гибкого абонемента поставлено в журнал.');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(1, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_place_trial_non_ajax_redirects_and_column_shows_count(): void
    {
        $this->grantLessonPackagesView();
        [$student, $team] = $this->makeStudentWithTeam();

        $response = $this->post(
            route('schedule.empty-cell.place-trial', $student),
            array_merge(
                $this->placeTrialPayload((int) $team->id, '2026-08-10', [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                ]),
                ['_token' => csrf_token()]
            )
        );

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Пробное занятие записано в журнал.');
        $this->assertNotSame(200, $response->getStatusCode());

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_place_single_non_ajax_redirects_and_column_shows_count(): void
    {
        $this->grantLessonPackagesView();
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();

        $response = $this->post(
            route('schedule.empty-cell.place-single', $student),
            array_merge(
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-08-11', 1000, [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                ]),
                ['_token' => csrf_token()]
            )
        );

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Разовое занятие записано в журнал.');
        $this->assertNotSame(200, $response->getStatusCode());

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]))->assertOk()->getContent();
        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_guest_non_ajax_mutations_redirect_not_empty_200(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $update = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);
        $this->assertNotSame(500, $update->getStatusCode());
        $this->assertNotSame(200, $update->getStatusCode());
        $update->assertRedirect();

        $destroy = $this->delete(route('schedule.occurrence.destroy', $utss), [
            '_token' => csrf_token(),
            'occurrence_date' => '2026-08-03',
        ]);
        $this->assertNotSame(500, $destroy->getStatusCode());
        $this->assertNotSame(200, $destroy->getStatusCode());
        $destroy->assertRedirect();

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_without_schedule_view_non_ajax_update_is_403_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $response = $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
    }
}
