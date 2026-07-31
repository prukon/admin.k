<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\UserTeamScheduleSlot;
use App\Services\TeamUserSyncService;

/**
 * [P2] E2E smoke журнала /schedule: страница → модалка (context) → AJAX submit →
 * данные видны через повторный GET page/cell-context без «белого экрана».
 *
 * Реальный браузер не используется: HTTP-цепочка повторяет AJAX-поток из schedule.js.
 *
 * @see \Tests\Feature\Crm\LessonPackages\LessonPackageAssignmentEndsAtWorkflowFeatureTest
 */
final class ScheduleJournalWorkflowFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_place_fixed_workflow_page_modal_submit_visible_without_reload(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $student->update(['name' => 'Workflow', 'lastname' => 'Раскладка']);
        $this->attachWeekdays($team, [1]);
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);
        $startDate = '2026-08-03';

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('abonementPlaceModal', false)
            ->assertSee('abonementPlaceForm', false)
            ->assertSee('journal-abonement-btn', false)
            ->assertSee('btnAbonementPlace', false)
            ->assertSee('cellEditModal', false)
            ->assertSee('cellEditForm', false)
            ->assertSee($student->full_name, false)
            ->assertSee('data-user-id="'.$student->id.'"', false);

        $openModal = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student));
        $openModal->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $student->id);
        $this->assertNotSame('', trim((string) $openModal->getContent()));

        $assignments = collect($openModal->json('assignments') ?? []);
        $row = $assignments->first(fn ($a) => (int) ($a['id'] ?? 0) === (int) $ulp->id);
        $this->assertIsArray($row, 'Назначение должно быть в контексте модалки');
        $this->assertTrue((bool) ($row['placeable'] ?? false));

        $submit = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'start_date' => $startDate,
                'weekdays' => [1],
            ]);
        $submit->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Абонемент разложен в расписание.')
            ->assertJsonPath('result.linked_count', 2);
        $this->assertNotSame('', trim((string) $submit->getContent()));

        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());

        // Как после window.location.reload() в schedule.js — страница не пустая, маркеры занятий видны.
        $pageAfter = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('data-occurrence-count="1"', false)
            ->assertSee('abonementPlaceModal', false);

        $cell = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $startDate,
            ]));
        $cell->assertOk()
            ->assertJsonStructure(['occurrences', 'selected', 'current_status_id']);
        $this->assertNotEmpty($cell->json('occurrences'));
        $this->assertSame((int) $ulp->id, (int) ($cell->json('occurrences.0.user_lesson_package_id') ?? 0));

        $contextAfter = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.abonement.context', $student));
        $contextAfter->assertOk();
        $placed = collect($contextAfter->json('assignments') ?? [])
            ->first(fn ($a) => (int) ($a['id'] ?? 0) === (int) $ulp->id);
        $this->assertIsArray($placed);
        $this->assertFalse((bool) ($placed['placeable'] ?? true));
    }

    public function test_status_update_workflow_page_modal_submit_visible_without_reload(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);
        $student->update(['name' => 'Workflow', 'lastname' => 'Статус']);
        $date = '2026-08-03';
        $utss = $this->createTrialUtss($student, $team, $date);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('cellEditModal', false)
            ->assertSee('cellEditForm', false)
            ->assertSee('id="cell-status-error"', false)
            ->assertSee('id="status-'.$this->visitedStatusId.'"', false)
            ->assertSee($student->full_name, false)
            ->assertSee('data-occurrence-count="1"', false);

        $openCell = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]));
        $openCell->assertOk()
            ->assertJsonPath('selected.utss_id', $utss->id);
        $this->assertNotSame('', trim((string) $openCell->getContent()));

        $comment = 'E2E smoke статус без F5';
        $submit = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => $date,
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'trainer_profile_id' => $trainer->id,
                'comment' => $comment,
            ]);
        $submit->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус занятия сохранён.');
        $this->assertNotSame('', trim((string) $submit->getContent()));

        $cellAfter = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('schedule.cell-context', [
                'user_id' => $student->id,
                'date' => $date,
                'utss_id' => $utss->id,
            ]));
        $cellAfter->assertOk()
            ->assertJsonPath('current_status_id', $this->visitedStatusId)
            ->assertJsonPath('comment', $comment)
            ->assertJsonPath('trainer_profile_id_for_select', (string) $trainer->id);

        // Повторная загрузка страницы (как reload в schedule.js) — не белый экран, статус на ячейке.
        $pageAfter = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee($student->full_name, false)
            ->assertSee('data-occurrence-count="1"', false)
            ->assertSee('data-status-id="'.$this->visitedStatusId.'"', false);

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'comment' => $comment,
            'trainer_profile_id' => $trainer->id,
        ]);
    }

    public function test_place_fixed_ajax_validation_keeps_page_usable_not_white_screen(): void
    {
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeFixedAssignment($student, lessons: 2, durationDays: 14);

        $this->get(route('schedule.index'))->assertOk()->assertSee('abonementPlaceModal', false);

        $fail = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-fixed', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
            ]);
        $fail->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
        $this->assertNotSame('', trim((string) $fail->getContent()));

        // Страница раздела по-прежнему отдаёт полноценный HTML.
        $page = $this->get(route('schedule.index'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('abonementPlaceForm', false)
            ->assertSee($student->full_name, false);
    }
}
