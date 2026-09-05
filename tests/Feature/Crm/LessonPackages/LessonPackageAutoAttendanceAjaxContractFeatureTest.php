<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Support\LessonPackageAutoAttendancePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт автосписания: JSON 200/422, errors по полю, UX «модалка без чекбокса шлёт 0».
 *
 * @see LessonPackageAutoAttendanceCrudFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageAutoAttendanceAjaxContractFeatureTest extends CrmTestCase
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

    private function actingAsPackagesManagerWithoutScheduleSlots(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ajax автосписание',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1200.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_ajax_enable_without_schedule_slots_view_returns_422_under_auto_attendance_field(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $response = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload(['auto_attendance_enabled' => 1]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['auto_attendance_enabled']])
            ->assertJsonPath(
                'errors.auto_attendance_enabled.0',
                LessonPackageAutoAttendancePermission::DENY_ENABLE
            );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_ajax_edit_payload_with_auto_attendance_zero_does_not_reset_existing_flag(): void
    {
        $this->actingAsPackagesManagerWithoutScheduleSlots();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax уже включено',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 1,
            'is_active' => 1,
        ]);

        $response = $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Ajax переименован',
                'schedule_type' => 'fixed',
                'auto_attendance_enabled' => 0,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $package->refresh();
        $this->assertSame('Ajax переименован', $package->name);
        $this->assertTrue((bool) $package->auto_attendance_enabled);
    }

    public function test_ajax_create_omitting_auto_attendance_field_stores_false(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $payload = $this->validPayload(['name' => 'Без ключа автосписания']);
        unset($payload['auto_attendance_enabled']);

        $this->postJson(route('admin.lesson-packages.store'), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Без ключа автосписания',
            'auto_attendance_enabled' => 0,
        ]);
    }

    public function test_ajax_manager_with_schedule_slots_view_can_enable_and_disable_auto_attendance(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->validPayload([
                'name' => 'Включаем',
                'auto_attendance_enabled' => 1,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Включаем')
            ->firstOrFail();
        $this->assertTrue((bool) $package->auto_attendance_enabled);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->validPayload([
                'name' => 'Включаем',
                'auto_attendance_enabled' => 0,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFalse((bool) $package->fresh()->auto_attendance_enabled);
    }

    public function test_show_json_returns_auto_attendance_flag_for_edit_modal(): void
    {
        $this->grantPermission('scheduleSlots.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Show flag',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 10,
            'price_cents' => 250000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 1,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.auto_attendance_enabled', true)
            ->assertJsonStructure([
                'success',
                'lesson_package' => ['id', 'name', 'schedule_type', 'auto_attendance_enabled'],
            ]);
    }
}
