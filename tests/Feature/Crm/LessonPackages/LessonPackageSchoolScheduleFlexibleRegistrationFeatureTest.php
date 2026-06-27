<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Inline-привязка гибкого абонемента в модалке слота календаря школы:
 * slot-user-bind-actions, assign-flexible (AJAX и non-AJAX), контроль доступа.
 */
final class LessonPackageSchoolScheduleFlexibleRegistrationFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::WEEK_MONDAY)->format('N'),
            'Тестовая дата должна быть понедельником (ISO weekday 1).'
        );
    }

    private function grantPermission(string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function studentUser(string $suffix = ''): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'Гибкий'.$suffix,
            'lastname' => 'Ученик',
            'is_enabled' => 1,
        ]);
    }

    private function mondaySlot(string $timeStart = '14:00', string $timeEnd = '15:00'): TeamScheduleSlot
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        return TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
    }

    private function flexibleTemplate(string $name = 'Гибкий тест'): LessonPackage
    {
        return LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $name,
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
            'price_cents' => 500000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    /**
     * @return array{ulp: UserLessonPackage, slot: TeamScheduleSlot}
     */
    private function flexibleBindContext(string $packageName = 'Гибкий тест'): array
    {
        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->flexibleTemplate($packageName);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 5,
            'lessons_remaining' => 5,
            'created_by' => $this->user->id,
        ]);

        return ['ulp' => $ulp, 'slot' => $slot];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignFlexiblePayload(UserLessonPackage $ulp, TeamScheduleSlot $slot): array
    {
        return [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ];
    }

    public function test_school_schedule_page_contains_flexible_inline_ui_markers(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalOpenFlexible', false)
            ->assertSee('schoolCalSlotFlexFormWrap', false)
            ->assertSee('schoolCalSlotFlexUlp', false)
            ->assertSee('submitSchoolCalSlotFlexibleRegistration', false);
    }

    public function test_guest_is_denied_on_flexible_endpoints(): void
    {
        Auth::logout();

        $routes = [
            ['GET', route('admin.lesson-packages.school-schedule'), []],
            ['GET', route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
                'user_id' => 1,
                'team_schedule_slot_id' => 1,
                'occurrence_date' => self::WEEK_MONDAY,
            ]), []],
            ['POST', route('admin.lesson-packages.school-schedule.assign-flexible'), [
                'user_lesson_package_id' => 1,
                'team_schedule_slot_id' => 1,
                'occurrence_date' => self::WEEK_MONDAY,
            ]],
        ];

        foreach ($routes as [$method, $url, $data]) {
            $response = $this->call($method, $url, $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "{$method} {$url} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_permission_gets_403_on_flexible_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertForbidden();
    }

    public function test_slot_bind_actions_flexible_json_structure_with_invalid_params(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'flexible' => ['allowed', 'reason', 'existing_assignments'],
            ])
            ->assertJsonPath('flexible.allowed', false)
            ->assertJsonPath('flexible.existing_assignments', []);
    }

    public function test_slot_bind_actions_flexible_blocked_without_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('flexible.allowed', false)
            ->assertJsonPath('flexible.existing_assignments', []);
    }

    public function test_slot_bind_actions_flexible_one_existing_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->flexibleTemplate('Гибкий 8 занятий');

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 5,
            'lessons_remaining' => 5,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('flexible.allowed', true)
            ->assertJsonPath('flexible.existing_assignments.0.id', $ulp->id)
            ->assertJsonPath('flexible.existing_assignments.0.label', '"Гибкий 8 занятий" (остаток 5)')
            ->assertJsonCount(1, 'flexible.existing_assignments');
    }

    public function test_slot_bind_actions_flexible_multiple_existing_assignments(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $packageA = $this->flexibleTemplate('Гибкий A');
        $packageB = $this->flexibleTemplate('Гибкий B');

        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $packageA->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 3,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $packageB->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('flexible.allowed', true)
            ->assertJsonCount(2, 'flexible.existing_assignments');
    }

    public function test_slot_bind_actions_flexible_filters_by_occurrence_date(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $inPeriod = $this->flexibleTemplate('В периоде');
        $outOfPeriod = $this->flexibleTemplate('Вне периода');

        $ulpIn = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $inPeriod->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 2,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $outOfPeriod->id,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 2,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('flexible.allowed', true)
            ->assertJsonCount(1, 'flexible.existing_assignments')
            ->assertJsonPath('flexible.existing_assignments.0.id', $ulpIn->id);
    }

    public function test_slot_bind_actions_flexible_blocked_when_calendar_row_exists(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot] = $this->flexibleBindContext();

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $ulp->user_id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => self::WEEK_MONDAY,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $ulp->user_id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('flexible.allowed', false)
            ->assertJsonPath('flexible.existing_assignments', []);
    }

    public function test_flexible_assignments_endpoint_returns_unified_label_format(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = $this->flexibleTemplate('Единый label');

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 3,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => $student->id,
        ]))
            ->assertOk()
            ->assertJsonPath('assignments.0.id', $ulp->id)
            ->assertJsonPath('assignments.0.label', '"Единый label" (остаток 2)');
    }

    public function test_assign_flexible_ajax_success_returns_message_json_and_creates_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot] = $this->flexibleBindContext();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])
            ->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', 'Занятие привязано к абонементу.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'user_id' => $ulp->user_id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
    }

    public function test_assign_flexible_ajax_validation_returns_422_with_errors(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_lesson_package_id',
                'team_schedule_slot_id',
                'occurrence_date',
            ]);
    }

    public function test_assign_flexible_ajax_returns_422_when_cell_already_occupied(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot] = $this->flexibleBindContext();

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $ulp->user_id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => self::WEEK_MONDAY,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Это занятие уже привязано к ученику.')
            ->assertJsonPath('errors.occurrence_date.0', 'Это занятие уже привязано к ученику.');
    }

    public function test_assign_flexible_non_ajax_redirects_and_creates_calendar_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot] = $this->flexibleBindContext('Non-Ajax flex');

        $this->post(route('admin.lesson-packages.school-schedule.assign-flexible'), $this->assignFlexiblePayload($ulp, $slot))
            ->assertRedirect(route('admin.lesson-packages.school-schedule'))
            ->assertSessionHas('status', 'Занятие привязано к абонементу.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $ulp->user_id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
    }

    public function test_assign_flexible_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.lesson-packages.school-schedule.assign-flexible'), [
                '_token' => csrf_token(),
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors([
                'user_lesson_package_id',
                'team_schedule_slot_id',
                'occurrence_date',
            ]);

        $this->assertSame(0, UserTeamScheduleSlot::query()->count());
    }
}
