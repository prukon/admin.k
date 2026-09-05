<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Directories;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для модалок шаблонов абонементов со страницы
 * /admin/directories/lesson-packages (общий store/update API).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackagesNonAjaxSafetyNetFeatureTest
 */
final class DirectoriesLessonPackagesNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
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
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dirs Non-AJAX пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ], $overrides);
    }

    public function test_store_non_ajax_from_directories_page_redirects_and_creates_package(): void
    {
        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload())
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Dirs Non-AJAX пакет',
            'schedule_type' => 'fixed',
            'auto_attendance_enabled' => 1,
        ]);
    }

    public function test_store_non_ajax_validation_failure_from_directories_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), [
                'schedule_type' => 'fixed',
                'auto_attendance_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name', 'duration_days', 'lessons_count', 'price']);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => '',
        ]);
    }

    public function test_update_non_ajax_from_directories_page_redirects_and_updates_package(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs до non-ajax',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
                'name' => 'Dirs после non-ajax',
                'schedule_type' => 'flexible',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1000.00',
                'freeze_enabled' => 0,
                'auto_attendance_enabled' => 1,
            ])
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHas('success');

        $package->refresh();
        $this->assertSame('Dirs после non-ajax', $package->name);
        $this->assertTrue((bool) $package->auto_attendance_enabled);
    }

    public function test_update_non_ajax_validation_failure_from_directories_redirects_with_errors_not_empty_200(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs валидация non-ajax',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
                'name' => '',
                'schedule_type' => 'fixed',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1000.00',
                'freeze_enabled' => 0,
                'auto_attendance_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertSame('Dirs валидация non-ajax', $package->fresh()->name);
        $this->assertFalse((bool) $package->fresh()->auto_attendance_enabled);
    }
}
