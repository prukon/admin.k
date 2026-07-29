<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonOccurrenceStatus;
use App\Models\MyLog;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Тулбар / DataTables / logs-data / audit для справочника статусов занятий.
 */
final class LessonOccurrenceStatusesToolbarFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function latestLog(AuditEvent $event): ?MyLog
    {
        return MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', $event->value)
            ->latest('id')
            ->first();
    }

    public function test_index_renders_toolbar_history_filters_columns(): void
    {
        $this->grantPermission('lessonPackages.view');

        $html = $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('payments-report-surface', $html);
        $this->assertStringContainsString('admin-list-toolbar', $html);
        $this->assertStringContainsString('>История</span>', $html);
        $this->assertStringContainsString('>Фильтры</span>', $html);
        $this->assertStringContainsString('>Колонки</span>', $html);
        $this->assertStringContainsString('id="los-statuses-table"', $html);
        $this->assertStringContainsString('historyModal', $html);
        $this->assertStringContainsString('showLogModal', $html);
        $this->assertStringContainsString('KidsCrmDataTable.create', $html);

        $historyPos = strpos($html, '>История</span>');
        $filtersPos = strpos($html, '>Фильтры</span>');
        $columnsPos = strpos($html, '>Колонки</span>');

        $this->assertNotFalse($historyPos);
        $this->assertNotFalse($filtersPos);
        $this->assertNotFalse($columnsPos);
        $this->assertTrue($historyPos < $filtersPos);
        $this->assertTrue($filtersPos < $columnsPos);
    }

    public function test_data_endpoint_returns_statuses_and_supports_filters(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $response = $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'type' => 'system',
            'status' => 'active',
        ]))->assertOk();

        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'id',
                    'sort_order',
                    'title',
                    'color',
                    'icon',
                    'type_label',
                    'consumes_lesson_label',
                    'is_active_label',
                ],
            ],
        ]);

        $this->assertGreaterThanOrEqual(5, (int) $response->json('recordsFiltered'));

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Посетил', $titles);

        $filtered = $this->getJson(route('admin.lesson-packages.occurrence-statuses.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'title' => 'Заморозка',
        ]))->assertOk();

        $this->assertSame(1, (int) $filtered->json('recordsFiltered'));
        $this->assertSame('Заморозка', $filtered->json('data.0.title'));
    }

    public function test_columns_settings_get_and_save(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.save'), [
            'columns' => [
                'sort_order' => true,
                'title' => true,
                'icon' => false,
                'type_label' => true,
                'consumes_lesson_label' => true,
                'is_active_label' => true,
                'actions' => true,
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $this->getJson(route('admin.lesson-packages.occurrence-statuses.columns-settings.get'))
            ->assertOk()
            ->assertJson([
                'icon' => false,
                'title' => true,
            ]);
    }

    public function test_logs_data_returns_200_with_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('logs.data.lesson-occurrence-status', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_permission(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('logs.data.lesson-occurrence-status', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertStatus(403);
    }

    public function test_store_update_destroy_write_audit_logs(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $this->postJson(route('admin.lesson-packages.occurrence-statuses.store'), [
            'title' => 'Аудит статус',
            'color' => '#112233',
            'icon' => 'fa-solid fa-star',
            'consumes_lesson' => 0,
            'is_active' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $created = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Аудит статус')
            ->firstOrFail();

        $createdLog = $this->latestLog(AuditEvent::LessonOccurrenceStatusCreated);
        $this->assertNotNull($createdLog);
        $this->assertSame(AuditEvent::LessonOccurrenceStatusCreated->level(), $createdLog->level);
        $this->assertStringContainsString('Название: Аудит статус', (string) $createdLog->description);
        $this->assertSame('Аудит статус', $createdLog->target_label);

        $this->putJson(route('admin.lesson-packages.occurrence-statuses.update', $created->id), [
            'title' => 'Аудит статус (изм.)',
            'color' => '#445566',
            'icon' => 'fa-solid fa-star',
            'sort_order' => 42,
            'consumes_lesson' => 1,
            'is_active' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $updatedLog = $this->latestLog(AuditEvent::LessonOccurrenceStatusUpdated);
        $this->assertNotNull($updatedLog);
        $this->assertStringContainsString('Название:', (string) $updatedLog->description);
        $this->assertStringContainsString('Цвет:', (string) $updatedLog->description);

        $this->deleteJson(route('admin.lesson-packages.occurrence-statuses.destroy', $created->id), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $deletedLog = $this->latestLog(AuditEvent::LessonOccurrenceStatusDeleted);
        $this->assertNotNull($deletedLog);
        $this->assertSame(AuditEvent::LessonOccurrenceStatusDeleted->level(), $deletedLog->level);
        $this->assertStringContainsString('Аудит статус (изм.)', (string) $deletedLog->description);
    }
}
