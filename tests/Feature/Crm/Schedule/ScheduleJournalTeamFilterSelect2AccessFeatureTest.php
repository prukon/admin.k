<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к фильтру групп журнала: гость, без schedule.view, с правом.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalTeamFilterSelect2AccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    /**
     * @return list<array{method: string, url: string}>
     */
    private function filterEndpoints(): array
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return [
            ['GET', route('schedule.index')],
            ['GET', route('schedule.index', ['year' => 2026, 'month' => '08', 'team_ids' => [$team->id]])],
            ['GET', route('schedule.index', ['year' => 2026, 'month' => '08', 'team_ids' => ['none', $team->id]])],
            ['GET', route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id])],
            ['GET', route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all'])],
            ['POST', route('schedule.index', ['team_ids' => [$team->id]])],
            ['PATCH', route('schedule.index')],
            ['DELETE', route('schedule.index')],
        ];
    }

    public function test_guest_cannot_open_journal_with_team_ids(): void
    {
        Auth::logout();

        foreach ($this->filterEndpoints() as [$method, $url]) {
            $response = $this->call($method, $url);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 405, 419],
                "Гость: {$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(200, $response->getStatusCode());
            $this->assertNotSame(500, $response->getStatusCode());
        }

        $this->getJson(route('schedule.index', ['team_ids' => [1]]))->assertStatus(401);
    }

    public function test_manager_without_schedule_view_gets_403_on_team_ids_filter(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        foreach ($this->filterEndpoints() as [$method, $url]) {
            $response = $this->actingAs($actor)
                ->withSession($session)
                ->call($method, $url, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertContains(
                $response->getStatusCode(),
                [403, 405],
                "Без schedule.view: {$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(200, $response->getStatusCode());
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_viewer_with_schedule_view_can_filter_by_team_ids(): void
    {
        $this->grantScheduleView();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ДоступГруппаЖурнал',
            'is_enabled' => 1,
        ]);
        $student = $this->makeStudent($team->id);
        $student->update(['lastname' => 'ДоступФильтр', 'name' => 'Ученик']);

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team_ids' => [$team->id],
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('id="filter-team"', false);
        $page->assertSee($student->full_name, false);
        $this->assertStringNotContainsString('Whoops', $html);

        $this->postJson(route('schedule.index'), ['team_ids' => [$team->id]])->assertStatus(405);
        $this->patchJson(route('schedule.index'), ['team_ids' => [$team->id]])->assertStatus(405);
        $this->deleteJson(route('schedule.index'))->assertStatus(405);
    }
}
