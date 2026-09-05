<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageFreezePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Чекбокс «Разрешена заморозка»: право scheduleSlots.view, 422, preserve на update.
 *
 * @see LessonPackageAutoAttendanceCrudFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageFreezePermissionFeatureTest extends CrmTestCase
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

    /**
     * @return array<string, mixed>
     */
    private function flexiblePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Гибкий заморозка',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 1,
            'freeze_days' => 7,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_store_ajax_rejects_freeze_without_schedule_slots_view(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_enabled'])
            ->assertJsonPath(
                'errors.freeze_enabled.0',
                LessonPackageFreezePermission::DENY_ENABLE
            );

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий заморозка',
        ]);
    }

    public function test_store_ajax_without_schedule_slots_view_creates_with_freeze_off(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload([
            'name' => 'Без заморозки',
            'freeze_enabled' => 0,
            'freeze_days' => '',
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Без заморозки',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);
    }

    public function test_update_ajax_without_schedule_slots_view_preserves_existing_freeze(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Preserve freeze',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'Preserve freeze renamed',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'freeze_days' => '',
            'auto_attendance_enabled' => 0,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $package->refresh();
        $this->assertSame('Preserve freeze renamed', $package->name);
        $this->assertTrue((bool) $package->freeze_enabled);
        $this->assertSame(14, (int) $package->freeze_days);
    }

    public function test_update_ajax_rejects_enabling_freeze_without_schedule_slots_view(): void
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Cannot enable freeze',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), [
            'name' => 'Cannot enable freeze',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 1,
            'freeze_days' => 7,
            'auto_attendance_enabled' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_enabled'])
            ->assertJsonPath(
                'errors.freeze_enabled.0',
                LessonPackageFreezePermission::DENY_ENABLE
            );

        $this->assertFalse((bool) $package->fresh()->freeze_enabled);
        $this->assertSame(0, (int) $package->fresh()->freeze_days);
    }

    public function test_store_ajax_with_schedule_slots_view_saves_freeze(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(route('admin.lesson-packages.store'), $this->flexiblePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий заморозка',
            'freeze_enabled' => 1,
            'freeze_days' => 7,
        ]);
    }

    public function test_store_non_ajax_without_schedule_slots_view_rejects_enable_under_field(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $response = $this->from(route('admin.directories.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), $this->flexiblePayload([
                'name' => 'Non-AJAX freeze deny',
            ]));

        $response->assertStatus(302)
            ->assertRedirect(route('admin.directories.lesson-packages.index'))
            ->assertSessionHasErrors(['freeze_enabled']);

        $this->assertSame(
            LessonPackageFreezePermission::DENY_ENABLE,
            session('errors')->first('freeze_enabled')
        );

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Non-AJAX freeze deny',
        ]);
    }
}
