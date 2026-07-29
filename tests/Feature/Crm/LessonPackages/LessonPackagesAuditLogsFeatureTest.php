<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\MyLog;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * История шаблонов абонементов: audit write + logs-data + UI markers.
 *
 * @see SportTypesAuditLogsFeatureTest
 */
final class LessonPackagesAuditLogsFeatureTest extends CrmTestCase
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

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Audit пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_logs_data_returns_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('logs.data.lesson-package', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('logs.data.lesson-package', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.directories.lesson-packages.index'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false)
            ->assertSee('lesson-packages\/logs-data', false);
    }

    public function test_store_writes_lesson_package_created_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => 'Футбол абонемент',
            'auto_attendance_enabled' => 1,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $log = $this->latestLog(AuditEvent::LessonPackageCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::LessonPackageCreated->level(), $log->level);
        $this->assertStringContainsString('Название: Футбол абонемент', (string) $log->description);
        $this->assertStringContainsString('Тип: Фиксированный', (string) $log->description);
        $this->assertStringContainsString('Автосписание: Да', (string) $log->description);
        $this->assertSame('Футбол абонемент', $log->target_label);
    }

    public function test_update_writes_lesson_package_updated_log_with_field_diffs(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый абонемент',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Новый абонемент',
                'schedule_type' => 'flexible',
                'duration_days' => 14,
                'lessons_count' => 4,
                'price' => '500.00',
                'auto_attendance_enabled' => 1,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $log = $this->latestLog(AuditEvent::LessonPackageUpdated);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Название: Старый абонемент → Новый абонемент', (string) $log->description);
        $this->assertStringContainsString('Тип: Фиксированный → Гибкий', (string) $log->description);
        $this->assertStringContainsString('Срок действия (дни): 30 → 14', (string) $log->description);
        $this->assertStringContainsString('Занятий: 8 → 4', (string) $log->description);
        $this->assertStringContainsString('Автосписание: Нет → Да', (string) $log->description);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Без изменений',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::LessonPackageUpdated->value)
            ->count();

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Без изменений',
                'price' => '1500.00',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $afterCount = MyLog::query()
            ->where('event', AuditEvent::LessonPackageUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_destroy_writes_lesson_package_deleted_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'На удаление',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $package->id]), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::LessonPackageDeleted);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::LessonPackageDeleted->level(), $log->level);
        $this->assertStringContainsString('Абонемент удалён: На удаление', (string) $log->description);
        $this->assertSame('На удаление', $log->target_label);
    }

    public function test_logs_data_returns_written_lesson_package_event_in_table(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'В таблице логов',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'После изменения',
                'price' => '1000.00',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.lesson-package', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(
                fn (string $d): bool => str_contains($d, 'В таблице логов → После изменения')
            ),
            'Ожидалась запись lesson_package.updated в logs-data.'
        );
    }
}
