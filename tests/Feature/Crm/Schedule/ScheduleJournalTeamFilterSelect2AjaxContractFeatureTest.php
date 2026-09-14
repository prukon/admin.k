<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Services\TeamUserSyncService;

/**
 * AJAX-контракт фильтра групп журнала: journal_team_ids[] важнее legacy journal_team_filter,
 * JSON 422 под полем, не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalTeamFilterSelect2AjaxContractFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_ajax_index_with_team_ids_returns_html_not_empty_json(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent($team->id);
        $student->update(['lastname' => 'AjaxФильтр', 'name' => 'Ученик']);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'team_ids' => [$team->id],
            ]));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringContainsString($student->full_name, $html);
        $this->assertStringNotContainsString('"success":true', $html);
        $this->assertStringNotContainsString('Whoops', $html);
    }

    public function test_update_ajax_uses_journal_team_ids_not_legacy_all_when_several_groups_selected(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [
            (int) $teamA->id,
            (int) $teamB->id,
            (int) $teamC->id,
        ]);

        $utssA = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $utssB = $this->createTrialUtss($student, $teamB, '2026-08-04');
        $utssC = $this->createTrialUtss($student, $teamC, '2026-08-05');
        $this->markUtssOccurrenceStatus($utssA, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($utssB, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($utssC, (int) $this->visitedStatusId);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssA->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
                'journal_team_ids' => [(string) $teamA->id, (string) $teamB->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 2);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssA->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $teamA->id,
                'journal_team_ids' => [(string) $teamB->id, (string) $teamC->id],
            ])
            ->assertOk()
            ->assertJsonPath('result.consuming_count', 2);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utssA->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('result.consuming_count', 3);
    }

    public function test_destroy_ajax_respects_journal_team_ids(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $utssA = $this->createTrialUtss($student, $teamA, '2026-08-03');
        $utssB = $this->createTrialUtss($student, $teamB, '2026-08-04');
        $this->markUtssOccurrenceStatus($utssA, (int) $this->visitedStatusId);
        $this->markUtssOccurrenceStatus($utssB, (int) $this->visitedStatusId);

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utssA), [
                'occurrence_date' => '2026-08-03',
                'journal_team_filter' => 'all',
                'journal_team_ids' => [(string) $teamB->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 1);
    }

    public function test_invalid_team_ids_ajax_index_returns_422_under_team_ids(): void
    {
        $errors = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'team_ids' => ['not-a-team'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_ids.0'])
            ->json('errors');
        $this->assertSame('Выберите группу из списка.', $errors['team_ids.0'][0] ?? null);
    }
}
