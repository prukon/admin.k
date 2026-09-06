<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\LessonPackage;
use App\Support\LessonPackageAutoAttendancePermission;
use App\Support\LessonPackageDurationPermission;
use App\Support\LessonPackageFreezePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Permissions\Concerns\SchoolScheduleHiddenDefaultPermissionsTestHelpers;

/**
 * AJAX JSON-контракт: новый admin, кастомная роль без шести прав, 422 errors[field].
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SuccessToastInsteadOfModalAjaxContractFeatureTest::test_ajax_role_create_returns_success_json_and_empty_name_422
 */
final class SchoolScheduleHiddenDefaultPermissionsAjaxContractFeatureTest extends CrmTestCase
{
    use SchoolScheduleHiddenDefaultPermissionsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    public function test_custom_role_ajax_create_does_not_copy_school_schedule_permissions(): void
    {
        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Кастомная без расписания',
        ], $this->ajaxHeaders());

        $create->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'role', 'permission_ids']);
        $this->assertNotSame('', trim((string) $create->getContent()));

        $roleId = (int) $create->json('role.id');
        $this->assertGreaterThan(0, $roleId);
        $permissionIds = $create->json('permission_ids');
        $this->assertIsArray($permissionIds);
        $this->assertPermissionIdsDoNotIncludeSchoolSchedule($permissionIds);

        foreach ($this->schoolSchedulePermissionNames() as $name) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $this->partner->id)
                    ->where('role_id', $roleId)
                    ->where('permission_id', $this->permissionId($name))
                    ->exists(),
                "permission_role не должен содержать {$name} у новой роли"
            );
        }
    }

    public function test_ajax_role_create_empty_name_returns_422_under_name(): void
    {
        $this->postJson(route('admin.setting.role.create'), [
            'name' => '',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_ajax_toggle_without_fields_returns_422_under_each_required_field(): void
    {
        $this->postJson(route('admin.setting.rule.toggle'), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id', 'permission_id', 'value'])
            ->assertJsonStructure([
                'message',
                'errors' => ['role_id', 'permission_id', 'value'],
            ]);
    }

    public function test_ajax_package_create_without_school_schedule_rights_stores_defaults_not_client_overrides(): void
    {
        $this->grantLessonPackageTypePermissions();

        $response = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->packageStorePayload([
                'name' => 'Ajax дефолты admin',
                'duration_days' => 90,
                'freeze_enabled' => 0,
                'freeze_days' => 14,
                'auto_attendance_enabled' => 0,
            ]),
            $this->ajaxHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('errors');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax дефолты admin',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
        ]);
    }

    public function test_ajax_package_create_omitting_gated_fields_does_not_force_freeze_or_auto(): void
    {
        $this->grantLessonPackageTypePermissions();

        $payload = $this->packageStorePayload(['name' => 'Ajax omit gated']);
        unset($payload['duration_days'], $payload['freeze_enabled'], $payload['freeze_days'], $payload['auto_attendance_enabled']);

        $this->postJson(route('admin.lesson-packages.store'), $payload, $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax omit gated',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ]);
    }

    public function test_ajax_enabling_freeze_or_auto_returns_422_under_the_field(): void
    {
        $this->grantLessonPackageTypePermissions();

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->packageStorePayload([
                'name' => 'Ajax freeze deny',
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_enabled'])
            ->assertJsonPath('errors.freeze_enabled.0', LessonPackageFreezePermission::DENY_ENABLE);

        $this->postJson(
            route('admin.lesson-packages.store'),
            $this->packageStorePayload([
                'name' => 'Ajax auto deny',
                'auto_attendance_enabled' => 1,
            ]),
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auto_attendance_enabled'])
            ->assertJsonPath(
                'errors.auto_attendance_enabled.0',
                LessonPackageAutoAttendancePermission::DENY_ENABLE
            );

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax freeze deny',
        ]);
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Ajax auto deny',
        ]);
    }

    public function test_ajax_edit_without_gated_fields_does_not_reset_existing_freeze_duration_or_auto(): void
    {
        $this->grantLessonPackageTypePermissions();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax уже с extras',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 120000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
            'auto_attendance_enabled' => 1,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', $package),
            [
                'name' => 'Ajax уже с extras',
                'schedule_type' => 'flexible',
                'lessons_count' => 8,
                'price' => '1200.00',
            ],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $package->fresh();
        $this->assertSame(45, (int) $fresh->duration_days);
        $this->assertTrue((bool) $fresh->freeze_enabled);
        $this->assertSame(14, (int) $fresh->freeze_days);
        $this->assertTrue((bool) $fresh->auto_attendance_enabled);
    }

    public function test_ajax_slot_and_assignment_mutations_are_403_not_422_for_new_admin(): void
    {
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();

        $slotStore = $this->postJson(
            route('admin.team-schedule-slots.store'),
            $ctx['storePayload'],
            $this->ajaxHeaders()
        );
        $slotStore->assertForbidden();
        $this->assertNotSame(422, $slotStore->getStatusCode());
        $this->assertNotSame(200, $slotStore->getStatusCode());

        $assign = $this->putJson(
            route('admin.lesson-packages.assignments.update', $ctx['assignment']),
            ['fee_amount' => '111.00'],
            $this->ajaxHeaders()
        );
        $assign->assertForbidden();
        $this->assertNotSame(422, $assign->getStatusCode());

        $paid = $this->postJson(
            route('admin.lesson-packages.assignments.manual-paid', $ctx['assignment']),
            ['mode' => 'paid', 'comment' => 'Ajax deny'],
            $this->ajaxHeaders()
        );
        $paid->assertForbidden();

        $this->grantAllSchoolSchedulePermissions();

        $this->postJson(route('admin.team-schedule-slots.store'), $ctx['storePayload'], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Слот создан')
            ->assertJsonStructure(['message', 'slot' => ['id']]);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', $ctx['assignment']),
            ['fee_amount' => '155.00'],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'fee_amount_cents' => 15500,
        ]);
    }
}
