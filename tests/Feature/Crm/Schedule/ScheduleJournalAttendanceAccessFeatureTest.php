<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use Illuminate\Support\Facades\Auth;

/**
 * Доступ к средней посещаемости и строке «Итого»: гость, без schedule.view, с правом.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalAttendanceSummaryFeatureTest
 */
final class ScheduleJournalAttendanceAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_is_redirected_from_journal_and_unauthorized_on_json(): void
    {
        Auth::logout();

        $web = $this->journalIndex( ['year' => 2026, 'month' => '08', 'team' => 'all']);
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $web->assertStatus(302);

        $json = $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(401);
    }

    public function test_guest_cannot_change_or_delete_a_visit_that_feeds_the_average(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->post(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertRedirect();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertUnauthorized();

        $this->delete(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-03',
        ])->assertRedirect();

        $this->deleteJson(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-03',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);
    }

    public function test_manager_without_schedule_view_gets_403_on_journal_and_visit_mutations(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $web = $this->actingAs($actor)->withSession($session)
            ->journalIndex( ['year' => 2026, 'month' => '08', 'team' => $team->id]);
        $this->assertNotSame(500, $web->getStatusCode());
        $web->assertForbidden();
        $this->assertStringNotContainsString('Средняя посещаемость:', (string) $web->getContent());

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-03',
            ])
            ->assertForbidden();
    }

    public function test_user_with_schedule_view_sees_average_and_day_totals(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantScheduleView($actor);

        [$student, $team] = $this->makeStudentWithTeam();
        $this->markUtssOccurrenceStatus(
            $this->createTrialUtss($student, $team, '2026-08-03'),
            (int) $this->visitedStatusId
        );

        $page = $this->journalIndex( [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]);

        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-attendance-average-value"', $html);
        $this->assertStringContainsString('>1,0<', $html);
        $this->assertStringContainsString('data-date="2026-08-03"', $html);
        $this->assertStringContainsString('>1<', $html);
    }
}
