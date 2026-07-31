<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Partner;
use App\Models\UserLessonOccurrenceStatusEvent;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Функциональные сценарии: вкладка «Статусы занятий» на /schedule,
 * общий справочник lesson_occurrence_statuses и ячейки журнала.
 */
final class ScheduleStatusesFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_schedule_occurrence_statuses_tab_renders_shared_ui_and_system_statuses(): void
    {
        $this->get(route('schedule.occurrence-statuses'))
            ->assertOk()
            ->assertSee('Статусы занятий', false)
            ->assertSee('losCreateModal', false)
            ->assertSee('losEditModal', false)
            ->assertSee('Списывает', false)
            ->assertSee('Активен', false)
            ->assertSee('payments-report-surface', false)
            ->assertSee('>История</span>', false)
            ->assertSee('>Фильтры</span>', false)
            ->assertSee('>Колонки</span>', false)
            ->assertSee('los-statuses-table', false)
            ->assertSee('historyModal', false);

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Посетил'])
            ->assertJsonFragment(['title' => 'Не посетил'])
            ->assertJsonFragment(['title' => 'Запись']);
    }

    public function test_journal_shows_active_occurrence_statuses_tab_link_and_hides_legacy_settings_modal(): void
    {
        $inactive = $this->createCustomOccurrenceStatus('Скрытый неактивный');
        $inactive->update(['is_active' => false]);

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('Статусы занятий', false)
            ->assertSee('Посетил', false)
            ->assertSee('id="status-' . $this->visitedStatusId . '"', false)
            ->assertDontSee('id="status-' . $inactive->id . '"', false)
            ->assertDontSee('id="btn-settings"', false)
            ->assertDontSee('id="settingsModal"', false)
            ->assertDontSee('/schedule/statuses', false);
    }

    public function test_shared_api_create_update_destroy_with_schedule_view(): void
    {
        $create = $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Кастом журнал',
            'color' => '#abcdef',
            'icon' => 'fa-solid fa-star',
            'consumes_lesson' => 0,
            'is_active' => 1,
        ])->assertOk();

        $id = (int) $create->json('status.id');
        $this->assertDatabaseHas('lesson_occurrence_statuses', [
            'id' => $id,
            'partner_id' => $this->partner->id,
            'title' => 'Кастом журнал',
            'consumes_lesson' => 0,
        ]);

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $id), [
            'title' => 'Кастом журнал (изм.)',
            'color' => '#112233',
            'icon' => 'fa-solid fa-star',
            'sort_order' => 55,
            'consumes_lesson' => true,
            'is_active' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('lesson_occurrence_statuses', [
            'id' => $id,
            'title' => 'Кастом журнал (изм.)',
            'consumes_lesson' => 1,
        ]);

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $id))
            ->assertOk();

        $this->assertDatabaseMissing('lesson_occurrence_statuses', ['id' => $id]);
    }

    public function test_system_status_cannot_be_destroyed(): void
    {
        $attended = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->where('code', LessonOccurrenceStatus::CODE_ATTENDED)
            ->firstOrFail();

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $attended->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);

        $this->assertDatabaseHas('lesson_occurrence_statuses', ['id' => $attended->id]);
    }

    public function test_reorder_persists_sort_order_for_partner_statuses(): void
    {
        $rows = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->ordered()
            ->get();

        $items = $rows->values()->map(fn (LessonOccurrenceStatus $row, int $i) => [
            'id' => $row->id,
            'sort_order' => 1000 - ($i * 10),
        ])->all();

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.reorder'), [
            'items' => $items,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Порядок сохранён');

        foreach ($items as $item) {
            $this->assertDatabaseHas('lesson_occurrence_statuses', [
                'id' => $item['id'],
                'sort_order' => $item['sort_order'],
            ]);
        }
    }

    public function test_journal_update_saves_lesson_occurrence_status_and_attended_keeps_trainer(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-18';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'comment' => 'Был на занятии',
            'trainer_profile_id' => $trainer->id,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $entry = UserLessonOccurrenceStatusEvent::query()
            ->where('user_id', $student->id)
            ->whereDate('occurrence_date', $date)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame((int) $this->visitedStatusId, (int) $entry->lesson_occurrence_status_id);
        $this->assertSame((int) $trainer->id, (int) $entry->trainer_profile_id);
        $this->assertSame('Был на занятии', $entry->comment);
    }

    public function test_journal_update_non_attended_clears_trainer(): void
    {
        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-19';
        $utss = $this->createTrialUtss($student, $team, $date);
        $notAttended = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->where('code', 'not_attended')
            ->firstOrFail();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $notAttended->id,
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $notAttended->id,
            'trainer_profile_id' => null,
        ]);
    }

    public function test_inactive_status_rejected_on_schedule_update(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();
        $status = $this->createCustomOccurrenceStatus('Неактивный');
        $status->update(['is_active' => false]);
        $date = '2026-05-15';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $status->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);
    }

    public function test_foreign_partner_status_rejected_on_schedule_update(): void
    {
        [$student, $team] = $this->makeStudentTeamAndTrainer();
        $foreignPartner = Partner::factory()->create();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $foreignPartner->id);
        $foreignAttended = LessonOccurrenceStatus::attendedIdForPartner((int) $foreignPartner->id);
        $date = '2026-05-15';
        $utss = $this->createTrialUtss($student, $team, $date);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $foreignAttended,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);
    }

    public function test_schedule_tab_seeds_system_statuses_if_missing(): void
    {
        LessonOccurrenceStatus::query()->where('partner_id', $this->partner->id)->delete();

        $this->get(route('schedule.occurrence-statuses'))
            ->assertOk()
            ->assertSee('Статусы занятий', false)
            ->assertSee('los-statuses-table', false);

        $this->assertSame(
            5,
            LessonOccurrenceStatus::query()->where('partner_id', $this->partner->id)->count()
        );

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Посетил']);
    }

    public function test_consumes_lesson_flag_visible_on_schedule_tab_and_journal_writes_occurrence_event(): void
    {
        // UI показывает колонку "Списывает"; в едином контуре журнал теперь пишет событие статуса
        // через UserLessonOccurrenceStatusService (для UTSS без абонемента корректирует пробный остаток).
        $this->get(route('schedule.occurrence-statuses'))
            ->assertOk()
            ->assertSee('Списывает занятие с абонемента', false);

        [$student, $team, $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-21';
        $utss = $this->createTrialUtss($student, $team, $date);

        $beforeEvents = DB::table('user_lesson_occurrence_status_events')->count();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        // Занятие пробное (без абонемента) — журнал пишет статус, но не должен трогать баланс абонемента.
        $this->assertSame(
            $beforeEvents + 1,
            DB::table('user_lesson_occurrence_status_events')->count(),
            'Журнал /schedule должен писать событие статуса занятия'
        );

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $utss->team_schedule_slot_id,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
        ]);
    }
}
