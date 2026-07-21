<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\Partner;
use App\Models\ScheduleUser;
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
            ->assertSee('Посетил', false)
            ->assertSee('Не посетил', false)
            ->assertSee('Запись', false)
            ->assertSee('Списывает', false)
            ->assertSee('Активен', false)
            ->assertSee('Добавить статус', false);
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
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-18';

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'description' => 'Был на занятии',
            'trainer_profile_id' => $trainer->id,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $entry = ScheduleUser::query()
            ->where('user_id', $student->id)
            ->whereDate('date', $date)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame((int) $this->visitedStatusId, (int) $entry->lesson_occurrence_status_id);
        $this->assertSame((int) $trainer->id, (int) $entry->trainer_profile_id);
        $this->assertSame('Был на занятии', $entry->description);
    }

    public function test_journal_update_non_attended_clears_trainer(): void
    {
        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-19';
        $notAttended = LessonOccurrenceStatus::query()
            ->forPartner((int) $this->partner->id)
            ->where('code', 'not_attended')
            ->firstOrFail();

        ScheduleUser::query()->create([
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ]);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'lesson_occurrence_status_id' => $notAttended->id,
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->assertDatabaseHas('schedule_users', [
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $notAttended->id,
            'trainer_profile_id' => null,
        ]);
    }

    public function test_inactive_status_rejected_on_schedule_update(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();
        $status = $this->createCustomOccurrenceStatus('Неактивный');
        $status->update(['is_active' => false]);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => '2026-05-15',
            'lesson_occurrence_status_id' => $status->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);
    }

    public function test_foreign_partner_status_rejected_on_schedule_update(): void
    {
        [$student] = $this->makeStudentTeamAndTrainer();
        $foreignPartner = Partner::factory()->create();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $foreignPartner->id);
        $foreignAttended = LessonOccurrenceStatus::attendedIdForPartner((int) $foreignPartner->id);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => '2026-05-15',
            'lesson_occurrence_status_id' => $foreignAttended,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);
    }

    public function test_schedule_tab_seeds_system_statuses_if_missing(): void
    {
        LessonOccurrenceStatus::query()->where('partner_id', $this->partner->id)->delete();

        $this->get(route('schedule.occurrence-statuses'))
            ->assertOk()
            ->assertSee('Посетил', false);

        $this->assertSame(
            5,
            LessonOccurrenceStatus::query()->where('partner_id', $this->partner->id)->count()
        );
    }

    public function test_consumes_lesson_flag_visible_on_schedule_tab_but_journal_does_not_change_package_balance(): void
    {
        // UI показывает колонку; журнал не списывает абонемент (нет вызова UserLessonOccurrenceStatusService).
        $this->get(route('schedule.occurrence-statuses'))
            ->assertOk()
            ->assertSee('Списывает занятие с абонемента', false);

        [$student, , $trainer] = $this->makeStudentTeamAndTrainer();

        $beforeEvents = DB::table('user_lesson_occurrence_status_events')->count();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => '2026-05-21',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
            'trainer_profile_id' => $trainer->id,
        ])->assertOk();

        $this->assertSame(
            $beforeEvents,
            DB::table('user_lesson_occurrence_status_events')->count(),
            'Журнал /schedule не должен писать события статусов абонементов'
        );
    }
}
