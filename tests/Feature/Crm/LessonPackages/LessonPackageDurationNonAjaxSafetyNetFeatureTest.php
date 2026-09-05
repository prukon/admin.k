<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageDurationPermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net срока действия: 302, запись в БД, ошибки под полем, UX пустой строки.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackageDurationPermissionFeatureTest
 */
final class LessonPackageDurationNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
            'name' => 'Non-AJAX срок',
            'schedule_type' => 'fixed',
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_without_schedule_slots_view_creates_package_with_duration_30_and_redirects(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Создан без поля срока',
            ]))
            ->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Создан без поля срока',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
        ]);
    }

    public function test_directories_store_without_duration_field_returns_to_directories_with_duration_30(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Dirs фон 30',
            ]))
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Dirs фон 30',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
        ]);
    }

    public function test_edit_save_without_duration_field_does_not_reset_existing_value(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Уже 45 дней',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $response = $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Переименован без срока',
                'schedule_type' => 'flexible',
                'duration_days' => '',
            ]));

        $response->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $package->refresh();
        $this->assertSame('Переименован без срока', $package->name);
        $this->assertSame(45, (int) $package->duration_days);
    }

    public function test_directories_edit_save_without_duration_keeps_value_and_returns_to_directories(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Dirs 45',
            'schedule_type' => 'fixed',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->from(route('admin.directories.lesson-packages.index'))
            ->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->validPayload([
                'name' => 'Dirs переименован',
                'duration_days' => '',
            ]))
            ->assertRedirect(route('admin.directories.lesson-packages.index'));

        $this->assertSame(45, (int) $package->fresh()->duration_days);
        $this->assertSame('Dirs переименован', $package->fresh()->name);
    }

    public function test_store_with_schedule_slots_view_redirects_back_with_duration_field_error_when_omitted(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->validPayload([
                'name' => 'Нет срока non-ajax',
            ]));

        $response->assertStatus(302)
            ->assertSessionHasErrors(['duration_days']);
        $this->assertSame(
            'Укажите длительность в днях.',
            session('errors')->first('duration_days')
        );
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Нет срока non-ajax',
        ]);
    }
}
