<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageFreezePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net заморозки: 302, запись в БД, ошибки под полем, UX без чекбокса.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackageFreezePermissionFeatureTest
 */
final class LessonPackageFreezeNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
            'name' => 'Non-AJAX заморозка',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'freeze_days' => '',
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_without_schedule_slots_view_rejects_enabling_freeze_and_redirects_with_field_error(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Нельзя включить заморозку',
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]));

        $response->assertStatus(302)
            ->assertSessionHasErrors([
                'freeze_enabled' => LessonPackageFreezePermission::DENY_ENABLE,
            ]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Нельзя включить заморозку',
        ]);
    }

    public function test_store_without_schedule_slots_view_creates_package_with_freeze_off(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Создан без галочки заморозки',
                'freeze_enabled' => 0,
            ]))
            ->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Создан без галочки заморозки',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);
    }

    public function test_directories_store_without_checkbox_returns_to_directories_with_freeze_off(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Dirs без заморозки',
            ]))
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Dirs без заморозки',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);
    }

    public function test_edit_save_without_checkbox_does_not_turn_off_existing_freeze(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Уже с заморозкой',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $response = $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Переименован без галочки',
                'schedule_type' => 'flexible',
                'freeze_enabled' => 0,
                'freeze_days' => '',
            ]));

        $response->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $package->refresh();
        $this->assertSame('Переименован без галочки', $package->name);
        $this->assertTrue((bool) $package->freeze_enabled);
        $this->assertSame(14, (int) $package->freeze_days);
    }

    public function test_directories_edit_save_without_checkbox_keeps_freeze_and_returns_to_directories(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs уже с заморозкой',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 1,
            'freeze_days' => 21,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Dirs переименован',
                'freeze_enabled' => 0,
                'freeze_days' => '',
            ]))
            ->assertRedirect(route('admin.directories.lesson-packages.index'));

        $this->assertTrue((bool) $package->fresh()->freeze_enabled);
        $this->assertSame(21, (int) $package->fresh()->freeze_days);
        $this->assertSame('Dirs переименован', $package->fresh()->name);
    }

    public function test_update_without_schedule_slots_view_rejects_enabling_freeze_with_field_error(): void
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
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors([
                'freeze_enabled' => LessonPackageFreezePermission::DENY_ENABLE,
            ]);

        $this->assertFalse((bool) $package->fresh()->freeze_enabled);
        $this->assertSame(0, (int) $package->fresh()->freeze_days);
    }

    public function test_store_with_schedule_slots_view_creates_freeze_and_redirects(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Non-AJAX с заморозкой',
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]))
            ->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX с заморозкой',
            'freeze_enabled' => 1,
            'freeze_days' => 7,
        ]);
    }

    public function test_store_with_schedule_slots_view_redirects_back_with_freeze_days_error_when_enabled_without_days(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Нет дней non-ajax',
                'freeze_enabled' => 1,
                'freeze_days' => '',
            ]));

        $response->assertStatus(302)
            ->assertSessionHasErrors(['freeze_days']);
        $this->assertSame(
            'Укажите количество дней заморозки.',
            session('errors')->first('freeze_days')
        );
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Нет дней non-ajax',
        ]);
    }
}
