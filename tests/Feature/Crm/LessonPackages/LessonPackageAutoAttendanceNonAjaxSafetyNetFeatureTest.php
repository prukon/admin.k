<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageAutoAttendancePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для автосписания шаблона: 302, ошибки по полю, UX-баг «сохранение без чекбокса».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackagesNonAjaxSafetyNetFeatureTest
 */
final class LessonPackageAutoAttendanceNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermissionToUser(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingAsPackagesManagerWithoutScheduleSlots(): User
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        return $actor;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Non-AJAX автосписание',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_without_schedule_slots_view_rejects_enabling_auto_attendance_and_redirects_with_field_error(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Нельзя включить',
                'auto_attendance_enabled' => 1,
            ]));

        $response->assertStatus(302)
            ->assertSessionHasErrors([
                'auto_attendance_enabled' => LessonPackageAutoAttendancePermission::DENY_ENABLE,
            ]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Нельзя включить',
        ]);
    }

    public function test_store_without_schedule_slots_view_creates_package_with_auto_attendance_off(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Создан без галочки',
                'auto_attendance_enabled' => 0,
            ]))
            ->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Создан без галочки',
            'auto_attendance_enabled' => 0,
        ]);
    }

    public function test_edit_save_without_checkbox_does_not_turn_off_existing_auto_attendance(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Уже с автосписанием',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 1,
            'is_active' => 1,
        ]);

        $response = $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Переименован без галочки',
                'schedule_type' => 'flexible',
                'auto_attendance_enabled' => 0,
            ]));

        $response->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $package->refresh();
        $this->assertSame('Переименован без галочки', $package->name);
        $this->assertTrue(
            (bool) $package->auto_attendance_enabled,
            'JS без чекбокса шлёт 0; сервер не должен сбрасывать уже включённое автосписание.'
        );
    }

    public function test_directories_edit_save_without_checkbox_keeps_auto_attendance_and_returns_to_directories(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs уже с автосписанием',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 1,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Dirs переименован',
                'auto_attendance_enabled' => 0,
            ]))
            ->assertRedirect(route('admin.directories.lesson-packages.index'));

        $this->assertTrue((bool) $package->fresh()->auto_attendance_enabled);
        $this->assertSame('Dirs переименован', $package->fresh()->name);
    }

    public function test_update_without_schedule_slots_view_rejects_enabling_auto_attendance_with_field_error(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Выключено',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Выключено',
                'auto_attendance_enabled' => 1,
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors([
                'auto_attendance_enabled' => LessonPackageAutoAttendancePermission::DENY_ENABLE,
            ]);

        $this->assertFalse((bool) $package->fresh()->auto_attendance_enabled);
    }
}
