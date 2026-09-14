<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Services\TeamUserSyncService;

/**
 * Native GET/POST без X-Requested-With: HTML/302, запись в БД, не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalTeamFilterSelect2NonAjaxSafetyNetFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_native_get_with_several_team_ids_returns_html_journal(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $studentA = $this->makeStudent($teamA->id);
        $studentA->update(['lastname' => 'НативОр', 'name' => 'А']);
        $studentB = $this->makeStudent($teamB->id);
        $studentB->update(['lastname' => 'НативОр', 'name' => 'Б']);

        $response = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id, $teamB->id],
        ]));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringContainsString($studentA->full_name, $html);
        $this->assertStringContainsString($studentB->full_name, $html);
        $this->assertStringNotContainsString('"success":true', $html);
        $this->assertStringNotContainsString('Whoops', $html);
    }

    public function test_native_search_get_keeps_hidden_team_ids_and_filters(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $match = $this->makeStudent($teamA->id);
        $match->update(['lastname' => 'НативПоискГруппа', 'name' => 'А']);
        $other = $this->makeStudent($teamB->id);
        $other->update(['lastname' => 'НативПоискГруппа', 'name' => 'Б']);

        $html = (string) $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$teamA->id],
            'q' => 'НативПоискГруппа',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString($match->full_name, $html);
        $this->assertStringNotContainsString($other->full_name, $html);
        $this->assertTrue(
            (bool) preg_match(
                '/<form method="get"[^>]*>[\s\S]*?id="table-search"[\s\S]*?<\/form>/',
                $html,
                $form
            )
        );
        $this->assertStringContainsString('name="team_ids[]" value="'.$teamA->id.'"', $form[0]);
        $this->assertStringNotContainsString('name="page"', $form[0]);
    }

    public function test_native_unknown_team_ids_redirects_with_field_error_not_other_students(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent($team->id);
        $student->update(['lastname' => 'НативЧужая', 'name' => 'Ученик']);

        $response = $this->from(route('schedule.index'))
            ->get(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'team_ids' => [999999],
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Невалидный team_ids не должен открывать журнал');
        $response->assertRedirect();
        $response->assertSessionHasErrors(['team_ids.0']);
        $this->assertStringNotContainsString($student->lastname, (string) $response->getContent());
    }

    public function test_update_non_ajax_with_journal_team_ids_redirects_and_persists(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $utss = $this->createTrialUtss($student, $teamA, '2026-08-03');

        $response = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'journal_team_filter' => 'all',
            'journal_team_ids' => [(string) $teamA->id, (string) $teamB->id],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ]);
        $this->assertSame(
            1,
            UserLessonOccurrenceStatusEvent::query()->where('user_id', $student->id)->count()
        );
    }
}
