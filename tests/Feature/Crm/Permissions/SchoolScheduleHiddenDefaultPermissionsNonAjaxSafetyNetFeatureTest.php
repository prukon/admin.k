<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\TeamScheduleSlot;
use App\Support\LessonPackageAutoAttendancePermission;
use App\Support\LessonPackageDurationPermission;
use App\Support\LessonPackageFreezePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Permissions\Concerns\SchoolScheduleHiddenDefaultPermissionsTestHelpers;

/**
 * Native POST без X-Requested-With: 302 / не пустой 200, запись в БД, ошибки под полями.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see SuccessToastInsteadOfModalNonAjaxSafetyNetFeatureTest::test_non_ajax_role_create_persists_and_is_not_empty_200
 */
final class SchoolScheduleHiddenDefaultPermissionsNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_non_ajax_custom_role_create_persists_without_school_schedule_permissions(): void
    {
        $label = 'NonAjax роль расписание '.uniqid('', true);

        $response = $this->from(route('admin.setting.rule'))
            ->post(route('admin.setting.role.create'), [
                '_token' => csrf_token(),
                'name' => $label,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
            $this->assertPermissionIdsDoNotIncludeSchoolSchedule($response->json('permission_ids') ?? []);
        }

        $this->assertDatabaseHas('roles', ['label' => $label]);
        $roleId = (int) DB::table('roles')->where('label', $label)->value('id');
        $this->assertGreaterThan(0, $roleId);

        foreach ($this->schoolSchedulePermissionNames() as $name) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $this->partner->id)
                    ->where('role_id', $roleId)
                    ->where('permission_id', $this->permissionId($name))
                    ->exists(),
                "Non-AJAX create не должен выдавать {$name}"
            );
        }
    }

    public function test_non_ajax_role_create_empty_name_redirects_with_name_field_error(): void
    {
        $response = $this->from(route('admin.setting.rule'))
            ->post(route('admin.setting.role.create'), [
                '_token' => csrf_token(),
                'name' => '',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация роли не должна давать успешный 200');
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    public function test_non_ajax_package_create_without_checkbox_redirects_and_stores_defaults(): void
    {
        $this->grantLessonPackageTypePermissions();

        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), array_merge(
                ['_token' => csrf_token()],
                $this->packageStorePayload([
                    'name' => 'NonAjax дефолты admin',
                    'duration_days' => 90,
                ])
            ));

        $response->assertRedirect(route('admin.lesson-packages.index'))
            ->assertSessionHas('success');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'NonAjax дефолты admin',
            'duration_days' => LessonPackageDurationPermission::DEFAULT_CREATE_DAYS,
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ]);
    }

    public function test_non_ajax_enabling_freeze_or_auto_redirects_with_field_error(): void
    {
        $this->grantLessonPackageTypePermissions();

        $freeze = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), array_merge(
                ['_token' => csrf_token()],
                $this->packageStorePayload([
                    'name' => 'NonAjax freeze deny',
                    'freeze_enabled' => 1,
                    'freeze_days' => 7,
                ])
            ));
        $freeze->assertStatus(302)
            ->assertSessionHasErrors([
                'freeze_enabled' => LessonPackageFreezePermission::DENY_ENABLE,
            ]);
        $this->assertNotSame(200, $freeze->getStatusCode());

        $auto = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.store'), array_merge(
                ['_token' => csrf_token()],
                $this->packageStorePayload([
                    'name' => 'NonAjax auto deny',
                    'auto_attendance_enabled' => 1,
                ])
            ));
        $auto->assertStatus(302)
            ->assertSessionHasErrors([
                'auto_attendance_enabled' => LessonPackageAutoAttendancePermission::DENY_ENABLE,
            ]);

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'NonAjax freeze deny',
        ]);
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'NonAjax auto deny',
        ]);
    }

    public function test_non_ajax_slot_store_is_forbidden_and_does_not_create_row(): void
    {
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();
        $before = TeamScheduleSlot::query()->where('partner_id', $this->partner->id)->count();

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.team-schedule-slots.store'), array_merge(
                ['_token' => csrf_token()],
                $ctx['storePayload']
            ));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Без manage store слота не должен быть успешный 200');
        $response->assertForbidden();

        $this->assertSame(
            $before,
            TeamScheduleSlot::query()->where('partner_id', $this->partner->id)->count()
        );
    }

    public function test_non_ajax_assignment_store_is_forbidden_and_does_not_create_row(): void
    {
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();
        $before = DB::table('user_lesson_packages')->count();

        $response = $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $ctx['student']->id,
                'lesson_package_id' => $ctx['package']->id,
                'fee_amount' => '444.00',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertSame($before, DB::table('user_lesson_packages')->count());
    }

    public function test_non_ajax_slot_store_after_grant_persists_and_is_not_empty_200(): void
    {
        $this->grantAllSchoolSchedulePermissions();
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.team-schedule-slots.store'), array_merge(
                ['_token' => csrf_token()],
                $ctx['storePayload']
            ));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Текущий контракт store слота — JSON, не redirect');
        $response->assertOk();
        $response->assertJsonPath('message', 'Слот создан');
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->assertDatabaseHas('team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'team_id' => $ctx['team']->id,
            'weekday' => 3,
            'time_start' => '11:00',
        ]);
    }

    public function test_non_ajax_assignment_store_after_grant_redirects_and_creates_row(): void
    {
        $this->grantLessonPackageTypePermissions();
        $this->grantAllSchoolSchedulePermissions();
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();

        $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $ctx['student']->id,
                'lesson_package_id' => $ctx['package']->id,
                'fee_amount' => '444.00',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $ctx['package']->id,
            'fee_amount_cents' => 44400,
        ]);
    }
}
