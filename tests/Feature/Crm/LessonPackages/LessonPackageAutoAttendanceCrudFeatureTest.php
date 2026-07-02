<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * CRUD шаблона абонемента: флаг auto_attendance_enabled (AJAX-контракт и валидация).
 */
final class LessonPackageAutoAttendanceCrudFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
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

    /**
     * @return array<string, mixed>
     */
    private function flexiblePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Гибкий автосписание',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ], $overrides);
    }

    public function test_index_shows_auto_attendance_ui_markers(): void
    {
        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('Автосписание', false)
            ->assertSee('create_auto_attendance_enabled', false)
            ->assertSee('edit_auto_attendance_enabled', false);
    }

    public function test_store_ajax_creates_package_with_auto_attendance_enabled(): void
    {
        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий автосписание',
            'schedule_type' => 'flexible',
            'auto_attendance_enabled' => 1,
        ]);
    }

    public function test_store_ajax_rejects_auto_attendance_for_no_schedule(): void
    {
        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price' => '500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auto_attendance_enabled']);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Разовое',
        ]);
    }

    public function test_show_json_includes_auto_attendance_enabled(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show auto',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 1,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.auto_attendance_enabled', true);
    }

    public function test_update_ajax_toggles_auto_attendance_enabled(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Update auto',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'Update auto',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, (int) $package->fresh()->auto_attendance_enabled);
    }

    public function test_update_ajax_forces_auto_attendance_off_for_no_schedule(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 50000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'Разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price' => '500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auto_attendance_enabled']);
    }
}
