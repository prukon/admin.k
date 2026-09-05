<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageDurationPermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Поле «Срок действия (дни)»: право scheduleSlots.view, фон 30 на create, preserve на update.
 *
 * @see LessonPackageFreezePermissionFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageDurationPermissionFeatureTest extends CrmTestCase
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
    private function flexiblePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Гибкий срок',
            'schedule_type' => 'flexible',
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_ajax_without_schedule_slots_view_sets_duration_30_when_field_omitted(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload([
            'name' => 'Фон 30 без поля',
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Фон 30 без поля',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
        ]);
    }

    public function test_store_ajax_without_schedule_slots_view_ignores_client_duration_and_sets_30(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload([
            'name' => 'Фон 30 игнор 90',
            'duration_days' => 90,
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Фон 30 игнор 90',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
        ]);
    }

    public function test_store_ajax_without_schedule_slots_view_forces_no_schedule_duration_1(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Разовое без срока в форме',
            'schedule_type' => 'no_schedule',
            'lessons_count' => 1,
            'price' => '500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Разовое без срока в форме',
            'duration_days' => LessonPackageDurationPermission::NO_SCHEDULE_DAYS,
        ]);
    }

    public function test_store_ajax_without_schedule_slots_view_forces_postpay_duration_31(): void
    {
        $actor = $this->actingAsPackagesManagerWithoutScheduleSlots();
        $this->grantPermissionToUser($actor, 'lessonPackages.type.postpay');

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Постоплата без срока в форме',
            'schedule_type' => 'postpay',
            'price' => '500.00',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Постоплата без срока в форме',
            'duration_days' => LessonPackageDurationPermission::POSTPAY_DAYS,
        ]);
    }

    public function test_update_ajax_without_schedule_slots_view_preserves_existing_duration(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Preserve duration',
            'schedule_type' => 'fixed',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'Preserve duration renamed',
            'schedule_type' => 'fixed',
            'duration_days' => 90,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $package->refresh();
        $this->assertSame('Preserve duration renamed', $package->name);
        $this->assertSame(45, (int) $package->duration_days);
    }

    public function test_update_ajax_without_schedule_slots_view_omitting_duration_preserves_existing(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Preserve omit duration',
            'schedule_type' => 'flexible',
            'duration_days' => 14,
            'lessons_count' => 4,
            'price_cents' => 50000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'Preserve omit duration renamed',
            'schedule_type' => 'flexible',
            'lessons_count' => 4,
            'price' => '500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(14, (int) $package->fresh()->duration_days);
    }

    public function test_store_ajax_with_schedule_slots_view_saves_custom_duration(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload([
            'name' => 'Срок 14 с правом',
            'duration_days' => 14,
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Срок 14 с правом',
            'duration_days' => 14,
        ]);
    }

    public function test_store_ajax_with_schedule_slots_view_requires_duration(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload([
            'name' => 'Без срока с правом',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration_days'])
            ->assertJsonPath('errors.duration_days.0', 'Укажите длительность в днях.');
    }

    public function test_update_ajax_without_schedule_slots_view_forces_no_schedule_duration_1(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Был фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'Стал разовым',
            'schedule_type' => 'no_schedule',
            'lessons_count' => 1,
            'price' => '500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ])
            ->assertOk();

        $this->assertSame(1, (int) $package->fresh()->duration_days);
    }

    public function test_store_non_ajax_without_schedule_slots_view_sets_duration_30(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $response = $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->flexiblePayload([
                'name' => 'Non-AJAX фон 30',
            ]));

        $response->assertStatus(302)
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX фон 30',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
        ]);
    }
}
