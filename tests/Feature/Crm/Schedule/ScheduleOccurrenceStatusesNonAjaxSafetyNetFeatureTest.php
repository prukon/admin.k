<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use Database\Seeders\LessonOccurrenceStatusesSeeder;

/**
 * Non-AJAX safety-net для CRUD статусов занятий (общий API вкладки /schedule и абонементов).
 * store/update/destroy без X-Requested-With → redirect 302, запись в БД создана/обновлена/удалена.
 * Не допускаем пустой 200.
 */
final class ScheduleOccurrenceStatusesNonAjaxSafetyNetFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Non-AJAX статус',
            'color' => '#abcdef',
            'icon' => 'fa-solid fa-star',
            'consumes_lesson' => 0,
            'is_active' => 1,
        ], $overrides);
    }

    public function test_store_non_ajax_redirects_and_creates_status(): void
    {
        $this->post(
            route('admin.lesson-packages.occurrence-statuses.store'),
            $this->validStorePayload(['title' => 'Non-AJAX создан'])
        )
            ->assertRedirect(route('admin.lesson-packages.occurrence-statuses.index'));

        $this->assertDatabaseHas('lesson_occurrence_statuses', [
            'partner_id' => $this->partner->id,
            'title' => 'Non-AJAX создан',
            'color' => '#abcdef',
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('schedule.occurrence-statuses'))
            ->post(route('admin.lesson-packages.occurrence-statuses.store'), [
                'color' => 'bad',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['title', 'color']);

        $this->assertDatabaseMissing('lesson_occurrence_statuses', [
            'partner_id' => $this->partner->id,
            'title' => '',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_status(): void
    {
        $status = $this->createCustomOccurrenceStatus('До non-ajax update');

        $this->put(route('admin.lesson-packages.occurrence-statuses.update', $status->id), [
            'title' => 'После non-ajax update',
            'color' => '#112233',
            'icon' => 'fa-solid fa-bell',
            'sort_order' => 42,
            'consumes_lesson' => 1,
            'is_active' => 1,
        ])
            ->assertRedirect(route('admin.lesson-packages.occurrence-statuses.index'));

        $this->assertDatabaseHas('lesson_occurrence_statuses', [
            'id' => $status->id,
            'title' => 'После non-ajax update',
            'color' => '#112233',
            'sort_order' => 42,
            'consumes_lesson' => 1,
        ]);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $status = $this->createCustomOccurrenceStatus('Валидация non-ajax');

        $this->from(route('schedule.occurrence-statuses'))
            ->put(route('admin.lesson-packages.occurrence-statuses.update', $status->id), [
                'title' => '',
                'color' => 'xx',
                'sort_order' => 1,
                'consumes_lesson' => 0,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['title', 'color']);

        $this->assertSame('Валидация non-ajax', $status->fresh()->title);
    }

    public function test_destroy_non_ajax_redirects_and_deletes_custom_status(): void
    {
        $status = $this->createCustomOccurrenceStatus('На удаление non-ajax');

        $this->delete(route('admin.lesson-packages.occurrence-statuses.destroy', $status->id))
            ->assertRedirect(route('admin.lesson-packages.occurrence-statuses.index'));

        $this->assertDatabaseMissing('lesson_occurrence_statuses', ['id' => $status->id]);
    }

    public function test_ajax_store_returns_json_contract_not_empty_200(): void
    {
        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), $this->validStorePayload([
            'title' => 'AJAX create contract',
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Статус создан')
            ->assertJsonPath('status.title', 'AJAX create contract')
            ->assertJsonStructure([
                'message',
                'status' => ['id', 'title', 'color', 'code', 'partner_id', 'is_system', 'is_active', 'consumes_lesson'],
            ]);
    }

    public function test_ajax_update_returns_json_contract_not_empty_200(): void
    {
        $status = $this->createCustomOccurrenceStatus('AJAX update before');

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $status->id), [
            'title' => 'AJAX update after',
            'color' => '#00aa00',
            'icon' => 'fa-solid fa-star',
            'sort_order' => 33,
            'consumes_lesson' => false,
            'is_active' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Статус обновлён')
            ->assertJsonStructure(['message']);

        $this->assertSame('AJAX update after', $status->fresh()->title);
    }

    public function test_ajax_store_validation_returns_422_with_field_errors(): void
    {
        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => '',
            'color' => 'not-hex',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'color']);
    }
}
