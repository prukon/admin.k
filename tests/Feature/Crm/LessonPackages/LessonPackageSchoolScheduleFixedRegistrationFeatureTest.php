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
 * Inline-привязка фиксированного абонемента в модалке слота календаря школы:
 * slot-user-bind-actions (fixed.existing_assignments), assign-fixed (AJAX и non-AJAX), контроль доступа.
 */
final class LessonPackageSchoolScheduleFixedRegistrationFeatureTest extends CrmTestCase
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
            'name' => 'Фикс'.$suffix,
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

    private function fixedTemplate(string $name = 'Фикс тест', int $lessonsCount = 1): LessonPackage
    {
        return LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $name,
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => $lessonsCount,
            'price_cents' => 500000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    /**
     * @return array{ulp: UserLessonPackage, slot: TeamScheduleSlot, student: User}
     */
    private function fixedBindContext(string $packageName = 'Фикс тест', string $timeStart = '14:00', string $timeEnd = '15:00'): array
    {
        $student = $this->studentUser();
        $slot = $this->mondaySlot($timeStart, $timeEnd);
        $package = $this->fixedTemplate($packageName);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '50.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return ['ulp' => $ulp, 'slot' => $slot, 'student' => $student];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignFixedPayload(User $student, UserLessonPackage $ulp, TeamScheduleSlot $slot): array
    {
        return [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => [
                [
                    'weekday' => 1,
                    'time_start' => substr((string) $slot->time_start, 0, 5),
                    'time_end' => substr((string) $slot->time_end, 0, 5),
                ],
            ],
        ];
    }

    public function test_school_schedule_page_contains_fixed_inline_ui_markers(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalOpenFixed', false)
            ->assertSee('schoolCalSlotFixedFormWrap', false)
            ->assertSee('schoolCalSlotFixedUlp', false)
            ->assertSee('schoolCalSlotFixedSubmit', false)
            ->assertSee('submitSchoolCalSlotFixedRegistration', false)
            ->assertSee('schoolCalFixedButtonDefaultLabel', false)
            ->assertDontSee('id="schoolCalFixedModal"', false);
    }

    public function test_guest_is_denied_on_fixed_endpoints(): void
    {
        Auth::logout();

        $routes = [
            ['GET', route('admin.lesson-packages.school-schedule'), []],
            ['GET', route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
                'user_id' => 1,
                'team_schedule_slot_id' => 1,
                'occurrence_date' => self::WEEK_MONDAY,
            ]), []],
            ['POST', route('admin.lesson-packages.school-schedule.assign-fixed'), [
                'user_id' => 1,
                'user_lesson_package_id' => 1,
                'team_schedule_slot_id' => 1,
                'anchor_date' => self::WEEK_MONDAY,
                'patterns' => [
                    ['weekday' => 1, 'time_start' => '14:00', 'time_end' => '15:00'],
                ],
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

    public function test_user_without_permission_gets_403_on_fixed_endpoints(): void
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

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => 1,
            'user_lesson_package_id' => 1,
            'team_schedule_slot_id' => 1,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '14:00', 'time_end' => '15:00'],
            ],
        ])->assertForbidden();
    }

    public function test_slot_bind_actions_fixed_json_structure_with_invalid_params(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'fixed' => ['allowed', 'reason', 'existing_assignments'],
            ])
            ->assertJsonPath('fixed.allowed', false)
            ->assertJsonPath('fixed.existing_assignments', []);
    }

    public function test_slot_bind_actions_fixed_blocked_without_assignment(): void
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
            ->assertJsonPath('fixed.allowed', false)
            ->assertJsonPath('fixed.existing_assignments', []);
    }

    public function test_slot_bind_actions_fixed_one_existing_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->fixedTemplate('Фикс 8 занятий', 8);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
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
            ->assertJsonPath('fixed.allowed', true)
            ->assertJsonPath('fixed.existing_assignments.0.id', $ulp->id)
            ->assertJsonPath('fixed.existing_assignments.0.label', 'Фикс 8 занятий №'.$ulp->id.' — осталось 5')
            ->assertJsonCount(1, 'fixed.existing_assignments');
    }

    public function test_slot_bind_actions_fixed_multiple_existing_assignments(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $packageA = $this->fixedTemplate('Фикс A');
        $packageB = $this->fixedTemplate('Фикс B');

        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $packageA->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 3,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);
        UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $packageB->id,
            'starts_at' => null,
            'ends_at' => null,
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
            ->assertJsonPath('fixed.allowed', true)
            ->assertJsonCount(2, 'fixed.existing_assignments');
    }

    public function test_slot_bind_actions_fixed_blocked_when_calendar_row_exists(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot, 'student' => $student] = $this->fixedBindContext();

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => self::WEEK_MONDAY,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('fixed.allowed', false)
            ->assertJsonPath('fixed.existing_assignments', []);
    }

    public function test_fixed_assignments_endpoint_returns_unified_label_format(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = $this->fixedTemplate('Единый label fixed');

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 3,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', [
            'user_id' => $student->id,
        ]))
            ->assertOk()
            ->assertJsonPath('assignments.0.id', $ulp->id)
            ->assertJsonPath('assignments.0.label', 'Единый label fixed №'.$ulp->id.' — осталось 2');
    }

    public function test_assign_fixed_ajax_success_returns_message_json_and_creates_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot, 'student' => $student] = $this->fixedBindContext('Ajax fixed');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => [
                [
                    'weekday' => 1,
                    'time_start' => '14:00',
                    'time_end' => '15:00',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);

        $ulp->refresh();
        $this->assertNotNull($ulp->starts_at);
        $this->assertNotNull($ulp->ends_at);
    }

    public function test_assign_fixed_ajax_validation_returns_422_with_errors(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_id',
                'user_lesson_package_id',
                'team_schedule_slot_id',
                'anchor_date',
                'patterns',
            ]);
    }

    public function test_assign_fixed_ajax_returns_422_when_assignment_already_has_period(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot, 'student' => $student] = $this->fixedBindContext('Already bound');

        $ulp->update([
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '14:00', 'time_end' => '15:00'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'У этого назначения уже задан период действия.')
            ->assertJsonPath('errors.user_lesson_package_id.0', 'Назначение уже имеет даты периода — повторная привязка недоступна.');
    }

    public function test_assign_fixed_non_ajax_redirects_and_creates_calendar_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['ulp' => $ulp, 'slot' => $slot, 'student' => $student] = $this->fixedBindContext('Non-Ajax fixed');

        $this->post(route('admin.lesson-packages.school-schedule.assign-fixed'), $this->assignFixedPayload($student, $ulp, $slot))
            ->assertRedirect(route('admin.lesson-packages.school-schedule'))
            ->assertSessionHas('status', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
    }

    public function test_assign_fixed_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.lesson-packages.school-schedule.assign-fixed'), [
                '_token' => csrf_token(),
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors([
                'user_id',
                'user_lesson_package_id',
                'team_schedule_slot_id',
                'anchor_date',
                'patterns',
            ]);

        $this->assertSame(0, UserTeamScheduleSlot::query()->count());
    }
}
