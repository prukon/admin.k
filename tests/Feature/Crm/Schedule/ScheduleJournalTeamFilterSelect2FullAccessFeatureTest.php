<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;

/**
 * Полный доступ: страница и AJAX журнала с team_ids[] / journal_team_ids[] → 200, не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalTeamFilterSelect2FullAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_schedule_index_with_team_ids_combinations_returns_ok(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'is_enabled' => 1]);
        $student = $this->makeStudent($teamA->id);
        $student->update(['lastname' => 'СмокФильтр', 'name' => 'Ученик']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);

        $queries = [
            [],
            ['team_ids' => [$teamA->id]],
            ['team_ids' => [$teamA->id, $teamB->id]],
            ['team_ids' => ['none']],
            ['team_ids' => ['none', $teamA->id]],
            ['team' => $teamA->id],
            ['team' => 'none'],
            ['year' => 2026, 'month' => '08', 'team_ids' => [$teamA->id], 'q' => 'Смок'],
        ];

        foreach ($queries as $query) {
            $response = $this->get(route('schedule.index', array_merge(
                ['year' => 2026, 'month' => '08'],
                $query
            )));
            $response->assertOk();
            $html = (string) $response->getContent();
            $this->assertNotSame('', trim($html), 'query='.json_encode($query));
            $this->assertStringContainsString('id="filter-team"', $html);
            $this->assertStringNotContainsString('Whoops', $html);
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_cell_and_update_endpoints_accept_journal_team_ids(): void
    {
        [$student, $teamA] = $this->makeStudentWithTeam();
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $utss = $this->createTrialUtss($student, $teamA, '2026-08-03');

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => '2026-08-03',
            'context_team_id' => $teamA->id,
        ]))->assertOk()->assertJsonStructure(['team_id', 'team_ids']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_ids' => [(string) $teamA->id, (string) $teamB->id],
                'journal_team_filter' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'result' => ['consuming_count']]);
    }

    public function test_guest_full_access_paths_are_not_empty_ok(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        Auth::logout();

        foreach ([
            ['GET', route('schedule.index', ['team_ids' => [$team->id]])],
            ['GET', route('schedule.index', ['team_ids' => ['none', $team->id]])],
        ] as [$method, $url]) {
            $response = $this->call($method, $url);
            $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
            $this->assertNotSame(200, $response->getStatusCode());
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }
}
