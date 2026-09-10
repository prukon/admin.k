<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net: 302, запись с 12 занятиями, ошибки под полем, не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see DirectoriesLessonPackagesNonAjaxSafetyNetFeatureTest
 */
final class LessonPackageEditFillDefaultsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantLessonPackageTypePermissions();
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
            'name' => 'Non-AJAX 12 занятий',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price' => '16800.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_non_ajax_redirects_and_creates_package_with_twelve_lessons(): void
    {
        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload())
            ->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX 12 занятий',
            'lessons_count' => 12,
            'duration_days' => 120,
        ]);
    }

    public function test_store_non_ajax_from_directories_redirects_to_directories_and_keeps_twelve(): void
    {
        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Dirs Non-AJAX 12',
            ]))
            ->assertRedirect(route('admin.directories.lesson-packages.index'));

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Dirs Non-AJAX 12',
            'lessons_count' => 12,
        ]);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_lessons_count_error_not_empty_200(): void
    {
        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), [
                'name' => 'Без занятий',
                'schedule_type' => 'flexible',
                'duration_days' => 120,
                'price' => '1000.00',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['lessons_count']);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Без занятий',
        ]);
    }

    public function test_update_non_ajax_redirects_and_keeps_twelve_lessons(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'До non-ajax 12',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price_cents' => 1680000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(
                route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
                $this->validPayload(['name' => 'После non-ajax 12'])
            )
            ->assertRedirect(route('admin.directories.lesson-packages.index'));

        $package->refresh();
        $this->assertSame('После non-ajax 12', $package->name);
        $this->assertSame(12, (int) $package->lessons_count);
        $this->assertSame(120, (int) $package->duration_days);
    }

    public function test_update_non_ajax_validation_failure_keeps_twelve_and_returns_errors_not_empty_200(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Валидация 12',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price_cents' => 1680000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
                'name' => 'Валидация 12',
                'schedule_type' => 'flexible',
                'duration_days' => 120,
                'lessons_count' => '',
                'price' => '16800.00',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['lessons_count']);

        $this->assertSame(12, (int) $package->fresh()->lessons_count);
        $this->assertSame(120, (int) $package->fresh()->duration_days);
    }
}
