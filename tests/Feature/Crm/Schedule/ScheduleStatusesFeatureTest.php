<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\Status;

/**
 * Функциональные тесты статусов журнала /schedule (системные и кастомные).
 */
final class ScheduleStatusesFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
        $this->grantScheduleView();
    }

    public function test_index_includes_global_system_statuses(): void
    {
        $response = $this->getJson(route('statuses.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'statuses' => [
                    '*' => [
                        'id',
                        'partner_id',
                        'name',
                        'icon',
                        'color',
                        'is_system',
                        'sort_order',
                    ],
                ],
            ]);

        $statuses = collect($response->json('statuses'));

        $visited = $statuses->firstWhere('name', Status::VISITED_NAME);
        $absent = $statuses->firstWhere('name', 'Не был');

        $this->assertNotNull($visited);
        $this->assertTrue($visited['is_system']);
        $this->assertNull($visited['partner_id']);

        $this->assertNotNull($absent);
        $this->assertTrue($absent['is_system']);
        $this->assertNull($absent['partner_id']);
    }

    public function test_index_includes_partner_custom_status_and_hides_foreign_custom(): void
    {
        $own = $this->createCustomScheduleStatus('Заморозка тест');
        $foreign = Status::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой статус',
            'icon' => 'fas fa-star',
            'color' => '#eeeeee',
            'is_system' => false,
            'sort_order' => 77,
        ]);

        $ids = collect($this->getJson(route('statuses.index'))->json('statuses'))
            ->pluck('id')
            ->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_creates_custom_status_with_ajax_json_contract(): void
    {
        $response = $this->postJson(route('statuses.store'), [
            'name' => 'Болезнь тест',
            'icon' => 'fas fa-user-injured',
            'color' => '#fadffb',
            'sort_order' => 45,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'status' => ['id', 'name', 'icon', 'color', 'is_system', 'sort_order'],
            ])
            ->assertJsonPath('status.name', 'Болезнь тест')
            ->assertJsonPath('status.is_system', false);

        $statusId = (int) $response->json('status.id');

        $this->assertDatabaseHas('statuses', [
            'id' => $statusId,
            'partner_id' => $this->partner->id,
            'name' => 'Болезнь тест',
            'is_system' => 0,
            'sort_order' => 45,
        ]);
    }

    public function test_store_assigns_auto_sort_order_when_omitted(): void
    {
        $first = $this->postJson(route('statuses.store'), [
            'name' => 'Первый авто',
            'color' => '#dffbe2',
        ])->assertOk();

        $second = $this->postJson(route('statuses.store'), [
            'name' => 'Второй авто',
            'color' => '#dffbe2',
        ])->assertOk();

        $this->assertGreaterThan(
            (int) $first->json('status.sort_order'),
            (int) $second->json('status.sort_order')
        );
    }

    public function test_store_validates_required_name(): void
    {
        $this->postJson(route('statuses.store'), [
            'color' => '#75eb81',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_non_ajax_redirects_and_creates_status(): void
    {
        $this->post(route('statuses.store'), [
            'name' => 'Статус без ajax',
            'icon' => 'fas fa-check',
            'color' => '#75eb81',
            'sort_order' => 55,
        ])
            ->assertRedirect(route('schedule.index'));

        $this->assertDatabaseHas('statuses', [
            'partner_id' => $this->partner->id,
            'name' => 'Статус без ajax',
            'sort_order' => 55,
            'is_system' => 0,
        ]);
    }

    public function test_update_custom_status_with_ajax_json_contract(): void
    {
        $status = $this->createCustomScheduleStatus('До изменения');

        $this->patchJson(route('statuses.update', $status->id), [
            'name' => 'После изменения',
            'icon' => 'fas fa-bell',
            'color' => '#112233',
            'sort_order' => 60,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status.name', 'После изменения');

        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'name' => 'После изменения',
            'sort_order' => 60,
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_status(): void
    {
        $status = $this->createCustomScheduleStatus('Патч без ajax');

        $this->patch(route('statuses.update', $status->id), [
            'name' => 'Патч без ajax (изм.)',
            'icon' => 'fas fa-star',
            'color' => '#abcdef',
            'sort_order' => 61,
        ])
            ->assertRedirect(route('schedule.index'));

        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'name' => 'Патч без ajax (изм.)',
            'sort_order' => 61,
        ]);
    }

    public function test_update_validates_sort_order(): void
    {
        $status = $this->createCustomScheduleStatus('Валидация сортировки');

        $this->patchJson(route('statuses.update', $status->id), [
            'name' => 'Валидация сортировки',
            'sort_order' => 'abc',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_order']);
    }

    public function test_destroy_custom_status_soft_deletes(): void
    {
        $status = $this->createCustomScheduleStatus('На удаление');

        $this->deleteJson(route('statuses.destroy', $status->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('statuses', ['id' => $status->id]);
    }

    public function test_cannot_update_global_system_status(): void
    {
        $systemId = Status::globalVisitedId();
        $this->assertNotNull($systemId);

        $this->patchJson(route('statuses.update', $systemId), [
            'name' => 'Взлом',
            'icon' => 'fas fa-check',
            'color' => '#000000',
            'sort_order' => 1,
        ])->assertNotFound();
    }

    public function test_cannot_destroy_global_system_status(): void
    {
        $systemId = Status::globalVisitedId();
        $this->assertNotNull($systemId);

        $this->deleteJson(route('statuses.destroy', $systemId))
            ->assertNotFound();
    }

    public function test_cannot_update_partner_scoped_system_status(): void
    {
        $legacySystem = Status::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Системный legacy',
            'icon' => 'fas fa-check',
            'color' => '#75eb81',
            'is_system' => true,
            'sort_order' => 5,
        ]);

        $this->patchJson(route('statuses.update', $legacySystem->id), [
            'name' => 'Взлом legacy',
            'icon' => 'fas fa-check',
            'color' => '#000000',
            'sort_order' => 6,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Системные статусы нельзя редактировать.');
    }

    public function test_cannot_destroy_partner_scoped_system_status(): void
    {
        $legacySystem = Status::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Системный legacy delete',
            'icon' => 'fas fa-check',
            'color' => '#75eb81',
            'is_system' => true,
            'sort_order' => 7,
        ]);

        $this->deleteJson(route('statuses.destroy', $legacySystem->id))
            ->assertForbidden()
            ->assertJsonPath('error', 'Системные статусы нельзя удалять.');
    }

    public function test_cannot_update_or_destroy_foreign_partner_custom_status(): void
    {
        $foreign = Status::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой для CRUD',
            'icon' => 'fas fa-star',
            'color' => '#eeeeee',
            'is_system' => false,
            'sort_order' => 80,
        ]);

        $this->patchJson(route('statuses.update', $foreign->id), [
            'name' => 'Взлом',
            'icon' => 'fas fa-star',
            'color' => '#ff0000',
            'sort_order' => 81,
        ])->assertNotFound();

        $this->deleteJson(route('statuses.destroy', $foreign->id))
            ->assertNotFound();
    }

    public function test_schedule_page_renders_status_settings_modal_markers(): void
    {
        $this->withoutVite();

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('id="settingsModal"', false)
            ->assertSee('id="statuses-table-body"', false)
            ->assertSee('id="editStatusModal"', false)
            ->assertSee('id="createStatusModal"', false)
            ->assertSee('id="btn-settings"', false)
            ->assertSee('reloadScheduleStatusesTable', false)
            ->assertSee(Status::VISITED_NAME, false);
    }
}
