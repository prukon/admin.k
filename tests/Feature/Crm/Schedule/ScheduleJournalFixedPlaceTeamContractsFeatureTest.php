<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Team;
use App\Models\UserTeamScheduleSlot;
use App\Services\Schedule\ScheduleJournalMonthService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Новый UX модалки «Разложить абонемент»: только fixed из установки цен,
 * группа → абонемент, team_locked / context_team_id, без классики.
 *
 * P1: доступ + AJAX-контракт context/place + non-AJAX safety-net + UI-маркеры.
 * P2: page → context (фильтр группы) → place → данные без «белого экрана».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalMonthlyUlpContractsFeatureTest
 */
final class ScheduleJournalFixedPlaceTeamContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_index_shows_plus_only_for_setting_prices_placeable(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 4);
        $hover = ScheduleJournalMonthService::fixedAbonementPlaceButtonHoverLine(
            (string) $ulp->lessonPackage?->name,
            4,
            4,
            (int) $ulp->fee_amount_cents,
        );

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('journal-abonement-btn', false)
            ->assertSee('abonement-team-readonly', false)
            ->assertSee('abonement-team-display', false)
            ->assertSee('Выберите группу', false)
            ->assertSee('cell-edit-context__name', false)
            ->assertSee('data-kids-tooltip-hint="1"', false)
            ->assertSee($hover, false)
            ->assertDontSee('title="Разложить абонемент"', false)
            ->getContent();

        $this->assertNotSame('', trim($html));
    }

    public function test_index_hides_plus_for_classic_fixed_only(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeFixedAssignment($student, lessons: 4, durationDays: 30);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertOk()
            ->assertDontSee('journal-abonement-btn', false)
            ->assertSee('abonementPlaceModal', false);
    }

    public function test_context_ajax_returns_setting_prices_structure_and_locks_single_team(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1, 3]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $team->id)
            ->assertJsonPath('teams.0.id', (int) $team->id)
            ->assertJsonStructure([
                'success',
                'user' => ['id', 'name', 'team_ids', 'teams_label'],
                'teams' => [['id', 'title', 'weekdays']],
                'team_id',
                'team_locked',
                'assignments',
                'default_start_date',
            ]);
        $this->assertNotSame('', (string) $response->getContent());
        $this->assertCount(1, $response->json('teams') ?? []);

        $row = collect($response->json('assignments') ?? [])->firstWhere('id', (int) $ulp->id);
        $this->assertIsArray($row);
        $this->assertTrue((bool) ($row['from_setting_prices'] ?? false));
        $this->assertTrue((bool) ($row['placeable'] ?? false));
        $this->assertSame((int) $team->id, (int) ($row['team_id'] ?? 0));
        $this->assertSame([1, 3], $response->json('teams.0.weekdays'));
    }

    public function test_context_ajax_excludes_classic_and_teams_without_placeable(): void
    {
        $teamWith = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamEmpty = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent((int) $teamWith->id);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamEmpty->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $monthly = $this->makeMonthlyFixedAssignment($student, (int) $teamWith->id, '2026-08-01', lessons: 2);
        $classic = $this->makeFixedAssignment($student, lessons: 3, durationDays: 21);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student))
            ->assertOk()
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $teamWith->id);

        $assignmentIds = collect($response->json('assignments') ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $monthly->id, $assignmentIds);
        $this->assertNotContains((int) $classic->id, $assignmentIds);

        $teamIds = collect($response->json('teams') ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([(int) $teamWith->id], $teamIds);
    }

    public function test_context_ajax_two_teams_unlocked_until_filter(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent((int) $teamA->id);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ulpA = $this->makeMonthlyFixedAssignment($student, (int) $teamA->id, '2026-08-01', lessons: 2);
        $ulpB = $this->makeMonthlyFixedAssignment($student, (int) $teamB->id, '2026-08-01', lessons: 3);

        $all = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student))
            ->assertOk()
            ->assertJsonPath('team_locked', false);
        $this->assertNull($all->json('team_id'));
        $this->assertEqualsCanonicalizing(
            [(int) $teamA->id, (int) $teamB->id],
            collect($all->json('teams') ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertEqualsCanonicalizing(
            [(int) $ulpA->id, (int) $ulpB->id],
            collect($all->json('assignments') ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $filtered = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student).'?'.http_build_query([
                'context_team_id' => $teamB->id,
            ]))
            ->assertOk()
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $teamB->id)
            ->assertJsonPath('teams.0.id', (int) $teamB->id);
        $this->assertCount(1, $filtered->json('teams') ?? []);
        // assignments остаются полным списком setting-prices; UI фильтрует по выбранной группе.
        $this->assertCount(2, $filtered->json('assignments') ?? []);
    }

    public function test_context_ajax_invalid_context_team_id_returns_422(): void
    {
        [$student] = $this->makeStudentWithTeam();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student).'?context_team_id=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['context_team_id']);
    }

    public function test_place_ajax_wrong_team_for_setting_prices_ulp_returns_422(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent((int) $teamA->id);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $this->attachWeekdays($teamA, [1]);
        $this->attachWeekdays($teamB, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $teamA->id, '2026-08-01', lessons: 2);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $teamB->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_id'])
            ->assertJsonStructure(['message', 'errors']);
        $this->assertStringContainsString(
            'группу',
            (string) ($response->json('errors.team_id.0') ?? '')
        );
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_ajax_success_returns_json_and_creates_utss(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview', false)
            ->assertJsonPath('message', 'Абонемент разложен в расписание.')
            ->assertJsonStructure([
                'success',
                'message',
                'preview',
                'result' => ['linked_count', 'starts_at', 'ends_at'],
            ])
            ->assertJsonPath('result.linked_count', 2)
            ->assertJsonPath('result.starts_at', '2026-08-03')
            ->assertJsonPath('result.ends_at', '2026-08-31');
        $this->assertNotSame('', (string) $response->getContent());
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_non_ajax_redirects_and_creates_utss_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $response = $this->post(route('schedule.abonement.place-fixed', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Абонемент разложен в расписание.');
        $this->assertNotSame(200, $response->status());
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_non_ajax_wrong_team_redirects_with_errors_not_empty_200(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent((int) $teamA->id);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $this->attachWeekdays($teamB, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $teamA->id, '2026-08-01', lessons: 2);

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $teamB->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['team_id']);
        $this->assertNotSame(200, $response->status());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_guest_denied_context_and_place(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 1);
        Auth::logout();

        $this->get(route('schedule.index'))->assertStatus(302);
        $this->getJson(route('schedule.abonement.context', $student))->assertStatus(401);
        $this->postJson(route('schedule.abonement.place-fixed', $student), [
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertStatus(401);
        $this->post(route('schedule.abonement.place-fixed', $student), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_id' => $team->id,
            'start_date' => '2026-08-03',
            'weekdays' => [1],
        ])->assertStatus(302);
    }

    public function test_without_schedule_view_context_and_place_return_403(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 1);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index'))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.abonement.context', $student))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ])
            ->assertStatus(403);
    }

    /**
     * P2: страница с фильтром группы → context (team_locked) → place → повторный GET.
     */
    public function test_place_workflow_with_team_filter_visible_without_reload(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id]);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->makeStudent((int) $teamA->id);
        $student->update(['name' => 'Фильтр', 'lastname' => 'Группы']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $teamA->id, (int) $teamB->id]);
        $this->attachWeekdays($teamB, [1]);
        // Placeable только в B — группа A в модалке не предлагается; фильтр B → team_locked.
        $ulpB = $this->makeMonthlyFixedAssignment($student, (int) $teamB->id, '2026-08-01', lessons: 2);

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $teamB->id,
        ]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('journal-abonement-btn', false)
            ->assertSee('abonementPlaceForm', false)
            ->assertSee($student->full_name, false);

        $ctx = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student).'?'.http_build_query([
                'context_team_id' => $teamB->id,
            ]));
        $ctx->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team_locked', true)
            ->assertJsonPath('team_id', (int) $teamB->id);
        $this->assertNotSame('', (string) $ctx->getContent());

        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulpB->id,
                'team_id' => $teamB->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ]);
        $place->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.linked_count', 2);
        $this->assertNotSame('', (string) $place->getContent());

        $pageAfter = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $teamB->id,
        ]));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('data-occurrence-count="1"', false)
            ->assertDontSee('journal-abonement-btn', false);
    }
}
