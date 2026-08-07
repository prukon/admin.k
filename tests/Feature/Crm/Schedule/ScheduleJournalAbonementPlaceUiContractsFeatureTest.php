<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\Auth;

/**
 * UX модалки «Разложить абонемент» (шапка ФИО/группа, подсказка дней, читаемое превью)
 * + маркеры «Занятие из гибкого».
 *
 * P1: UI на index + AJAX preview (preview_dates) + non-AJAX safety-net + доступ.
 * P2: страница → preview → place → повторный GET без пустого 200.
 *
 * @see ScheduleJournalFixedPlaceTeamContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalAbonementPlaceUiContractsFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_index_shows_abonement_modal_ui_markers_hint_and_preview_hooks(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('abonementPlaceModal', false)
            ->assertSee('cell-edit-context__name', false)
            ->assertSee('id="abonement-user-name"', false)
            ->assertSee('id="abonement-team-display"', false)
            ->assertSee('cell-edit-context__teams', false)
            ->assertSee('Выберите группу', false)
            ->assertSee('id="abonement-weekdays-legend"', false)
            ->assertSee('abonement-weekdays-legend__title', false)
            ->assertSee('Подсказка', false)
            ->assertSee('день недели согласно расписанию', false)
            ->assertSee('на этот день недели вы установите расписание', false)
            ->assertSee('id="abonement-preview-text"', false)
            ->assertSee('abonement-preview-text', false)
            ->assertSee('id="btnAbonementPreview"', false)
            ->assertDontSee('Для абонемента из установки цен — последний день месяца начисления.', false)
            ->getContent();

        $this->assertNotSame('', trim($html));
    }

    public function test_index_shows_flexible_modal_team_display_markers(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertOk()
            ->assertSee('flexiblePlaceModal', false)
            ->assertSee('id="flexible-user-name"', false)
            ->assertSee('id="flexible-team-display"', false)
            ->assertSee('Выберите группу', false)
            ->assertSee('cell-edit-context__teams', false);
    }

    public function test_place_ajax_preview_returns_dates_for_readable_ui_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1, 6, 7]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 4);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-01',
                'weekdays' => [6, 7, 1],
                'preview' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview', true)
            ->assertJsonPath('result.linked_count', 4)
            ->assertJsonPath('result.starts_at', '2026-08-01')
            ->assertJsonPath('result.ends_at', '2026-08-31')
            ->assertJsonStructure([
                'success',
                'message',
                'preview',
                'result' => ['linked_count', 'starts_at', 'ends_at', 'preview_dates'],
            ]);

        $dates = $response->json('result.preview_dates');
        $this->assertIsArray($dates);
        $this->assertCount(4, $dates);
        $this->assertSame('2026-08-01', $dates[0]);
        $this->assertSame('2026-08-02', $dates[1]);
        $this->assertSame('2026-08-03', $dates[2]);
        $this->assertSame('2026-08-08', $dates[3]);
        $this->assertNotSame('', (string) $response->json('message'));

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $ulp->refresh();
        $this->assertNull($ulp->starts_at);
    }

    public function test_place_ajax_preview_validation_returns_422_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-01',
                'weekdays' => [],
                'preview' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['weekdays']);
    }

    public function test_place_non_ajax_preview_redirects_without_writing_utss_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $response = $this->from(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
                'preview' => 1,
            ]);

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->status());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_place_non_ajax_creates_utss_redirect_not_empty_200(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $response = $this->from(route('schedule.index'))
            ->post(route('schedule.abonement.place-fixed', $student), [
                '_token' => csrf_token(),
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
            ]);

        $response->assertStatus(302)
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHas('status');
        $this->assertNotSame(200, $response->status());
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_guest_denied_index_and_place_preview(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);
        Auth::logout();

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertRedirect();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
                'preview' => 1,
            ])
            ->assertUnauthorized();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_without_schedule_view_index_and_place_return_403(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-03',
                'weekdays' => [1],
                'preview' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    /**
     * P2: index (UI) → AJAX preview (даты для читаемого текста) → place → GET с занятиями.
     */
    public function test_abonement_preview_then_place_workflow_visible_without_white_screen(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['name' => 'Превью', 'lastname' => 'Уи']);
        $this->attachWeekdays($team, [1, 6, 7]);
        $ulp = $this->makeMonthlyFixedAssignment($student, (int) $team->id, '2026-08-01', lessons: 4);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('abonementPlaceModal', false)
            ->assertSee('Подсказка', false)
            ->assertSee('btnAbonementPreview', false)
            ->assertSee($student->full_name, false);

        $preview = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-01',
                'weekdays' => [6, 7, 1],
                'preview' => 1,
            ]);
        $preview->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('result.linked_count', 4);
        $this->assertCount(4, $preview->json('result.preview_dates'));
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());

        $place = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => '2026-08-01',
                'weekdays' => [6, 7, 1],
            ]);
        $place->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview', false)
            ->assertJsonPath('result.linked_count', 4);
        $this->assertNotSame('', (string) $place->json('message'));

        $pageAfter = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('abonementPlaceModal', false)
            ->assertSee('Подсказка', false)
            ->assertDontSee('journal-abonement-btn', false);

        $this->assertSame(4, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }
}
