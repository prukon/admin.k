<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\TeamScheduleSlot;
use App\Support\LessonPackageAutoAttendancePermission;
use App\Support\LessonPackageFreezePermission;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Permissions\Concerns\SchoolScheduleHiddenDefaultPermissionsTestHelpers;

/**
 * HTTP-доступ группы schoolSchedule: новый admin без шести прав, гость, 403, не 500.
 *
 * @see SchoolSchedulePermissionGroupCatalogFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolScheduleHiddenDefaultPermissionsAccessFeatureTest extends CrmTestCase
{
    use SchoolScheduleHiddenDefaultPermissionsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_is_denied_on_school_schedule_surface_and_role_endpoints(): void
    {
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();
        Auth::logout();

        foreach ($this->schoolScheduleSurfaceCalls($ctx) as $item) {
            $json = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json']
            );
            $this->assertDeniedNotEmpty200Or500(
                $json,
                'Гость JSON '.$item['method'].' '.$item['url']
            );

            $web = $this->call(
                $item['method'],
                $item['url'],
                array_merge(['_token' => csrf_token()], $item['data'] ?? [])
            );
            $this->assertDeniedNotEmpty200Or500(
                $web,
                'Гость web '.$item['method'].' '.$item['url']
            );
        }
    }

    public function test_new_partner_admin_opens_calendar_and_packages_but_gated_endpoints_are_403(): void
    {
        $this->asAdmin();
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();

        $calendar = $this->get(route('admin.lesson-packages.school-schedule'));
        $calendar->assertOk();
        $this->assertNotSame('', trim((string) $calendar->getContent()));
        $this->assertNotSame(500, $calendar->getStatusCode());

        $packages = $this->get(route('admin.lesson-packages.index'));
        $packages->assertOk();
        $this->assertNotSame('', trim((string) $packages->getContent()));

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => '2026-05-04',
        ]))
            ->assertOk()
            ->assertJsonStructure(['week_start', 'occurrences']);

        $this->get(route('admin.lesson-packages.school-schedule.export', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.export', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))->assertForbidden();

        $this->get(route('admin.lesson-packages.team-schedule-slots'))->assertForbidden();
        $this->get(route('admin.team-schedule-slots.index'))->assertForbidden();
        $this->getJson(route('admin.team-schedule-slots.show', $ctx['slot']))->assertForbidden();
        $this->postJson(route('admin.team-schedule-slots.store'), $ctx['storePayload'])
            ->assertForbidden();
        $this->putJson(route('admin.team-schedule-slots.update', $ctx['slot']), $ctx['updatePayload'])
            ->assertForbidden();
        $this->deleteJson(route('admin.team-schedule-slots.destroy', $ctx['slot']))
            ->assertForbidden();
        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $ctx['slot']), [
            'occurrence_date' => '2026-05-04',
        ])->assertForbidden();
        $this->postJson(route('admin.team-schedule-slots.truncate-from-date', $ctx['slot']), [
            'occurrence_date' => '2026-05-04',
        ])->assertForbidden();

        $this->get(route('admin.lesson-packages.assignments'))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertForbidden();
        $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $ctx['package']->id,
            'fee_amount' => '100.00',
        ])->assertForbidden();
        $this->putJson(route('admin.lesson-packages.assignments.update', $ctx['assignment']), [
            'fee_amount' => '111.00',
        ])->assertForbidden();
        $this->postJson(route('admin.lesson-packages.assignments.manual-paid', $ctx['assignment']), [
            'mode' => 'paid',
            'comment' => 'Нельзя',
        ])->assertForbidden();

        $this->assertDatabaseHas('team_schedule_slots', ['id' => $ctx['slot']->id]);
        $this->assertSame(
            1,
            TeamScheduleSlot::query()->where('partner_id', $this->partner->id)->count()
        );
        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'is_paid' => 0,
        ]);
    }

    public function test_user_without_lesson_packages_view_gets_403_on_calendar(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => '2026-05-04',
        ]))->assertForbidden();
        $this->get(route('admin.lesson-packages.index'))->assertForbidden();
    }

    public function test_manager_without_roles_view_gets_403_on_role_create_and_toggle(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Запрещено',
        ], $this->ajaxHeaders())->assertForbidden();

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('scheduleSlots.view'),
            'value' => 'true',
        ], $this->ajaxHeaders())->assertForbidden();
    }

    public function test_after_grant_new_partner_admin_can_use_gated_school_schedule_endpoints(): void
    {
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions();
        $this->grantAllSchoolSchedulePermissions();
        $ctx = $this->seedSchoolScheduleHiddenDefaultContext();

        $this->get(route('admin.lesson-packages.school-schedule'))->assertOk();
        $this->get(route('admin.lesson-packages.team-schedule-slots'))->assertOk();
        $this->get(route('admin.lesson-packages.assignments'))->assertOk();

        $this->getJson(route('admin.team-schedule-slots.show', $ctx['slot']))
            ->assertOk()
            ->assertJsonPath('id', $ctx['slot']->id);

        $store = $this->postJson(route('admin.team-schedule-slots.store'), $ctx['storePayload']);
        $store->assertOk()
            ->assertJsonPath('message', 'Слот создан')
            ->assertJsonStructure(['message', 'slot' => ['id', 'team_id']]);
        $this->assertNotSame('', trim((string) $store->getContent()));
        $createdId = (int) $store->json('slot.id');
        $this->assertGreaterThan(0, $createdId);

        $this->putJson(route('admin.team-schedule-slots.update', $ctx['slot']), $ctx['updatePayload'])
            ->assertOk()
            ->assertJsonPath('message', 'Слот обновлён');

        $this->deleteJson(route('admin.team-schedule-slots.destroy', TeamScheduleSlot::query()->findOrFail($createdId)))
            ->assertOk()
            ->assertJsonPath('message', 'Слот удалён');

        $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $ctx['package']->id,
            'fee_amount' => '333.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $ctx['package']->id,
            'fee_amount_cents' => 33300,
        ]);

        $this->postJson(route('admin.lesson-packages.assignments.manual-paid', $ctx['assignment']), [
            'mode' => 'paid',
            'comment' => 'После выдачи прав',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_tampered_freeze_and_auto_attendance_posts_are_not_empty_200_for_new_admin(): void
    {
        $this->asAdmin();
        $this->grantLessonPackageTypePermissions();

        $freeze = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->packageStorePayload([
                'name' => 'Tamper freeze admin',
                'freeze_enabled' => 1,
                'freeze_days' => 7,
            ]),
            $this->ajaxHeaders()
        );
        $freeze->assertStatus(422)
            ->assertJsonValidationErrors(['freeze_enabled'])
            ->assertJsonPath('errors.freeze_enabled.0', LessonPackageFreezePermission::DENY_ENABLE);
        $this->assertNotSame(500, $freeze->getStatusCode());
        $this->assertNotSame(200, $freeze->getStatusCode());

        $auto = $this->postJson(
            route('admin.lesson-packages.store'),
            $this->packageStorePayload([
                'name' => 'Tamper auto admin',
                'auto_attendance_enabled' => 1,
            ]),
            $this->ajaxHeaders()
        );
        $auto->assertStatus(422)
            ->assertJsonValidationErrors(['auto_attendance_enabled'])
            ->assertJsonPath(
                'errors.auto_attendance_enabled.0',
                LessonPackageAutoAttendancePermission::DENY_ENABLE
            );

        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Tamper freeze admin',
        ]);
        $this->assertDatabaseMissing('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Tamper auto admin',
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function schoolScheduleSurfaceCalls(array $ctx): array
    {
        return [
            ['method' => 'GET', 'url' => route('admin.lesson-packages.school-schedule')],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.school-schedule.week', ['week' => '2026-05-04'])],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.school-schedule.export', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ])],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.team-schedule-slots')],
            ['method' => 'GET', 'url' => route('admin.team-schedule-slots.show', $ctx['slot'])],
            ['method' => 'POST', 'url' => route('admin.team-schedule-slots.store'), 'data' => $ctx['storePayload']],
            ['method' => 'PUT', 'url' => route('admin.team-schedule-slots.update', $ctx['slot']), 'data' => $ctx['updatePayload']],
            ['method' => 'DELETE', 'url' => route('admin.team-schedule-slots.destroy', $ctx['slot'])],
            ['method' => 'GET', 'url' => route('admin.lesson-packages.assignments')],
            ['method' => 'POST', 'url' => route('admin.lesson-packages.assignments.store'), 'data' => [
                'user_id' => $ctx['student']->id,
                'lesson_package_id' => $ctx['package']->id,
                'fee_amount' => '100.00',
            ]],
            ['method' => 'POST', 'url' => route('admin.lesson-packages.assignments.manual-paid', $ctx['assignment']), 'data' => [
                'mode' => 'paid',
            ]],
            ['method' => 'GET', 'url' => route('admin.setting.rule')],
            ['method' => 'POST', 'url' => route('admin.setting.role.create'), 'data' => ['name' => 'Гость']],
            ['method' => 'POST', 'url' => route('admin.lesson-packages.store'), 'data' => $this->packageStorePayload()],
        ];
    }
}
