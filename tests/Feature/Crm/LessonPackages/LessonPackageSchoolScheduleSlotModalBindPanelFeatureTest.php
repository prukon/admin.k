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
 * Inline-модалка слота: панели привязки абонемента (фокус, отмена, Esc, кэш bind-actions),
 * UI-маркеры, AJAX/non-AJAX контракты assign-flexible / assign-fixed, контроль доступа.
 */
final class LessonPackageSchoolScheduleSlotModalBindPanelFeatureTest extends CrmTestCase
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
            'name' => 'Модалка'.$suffix,
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

    private function flexibleTemplate(string $name = 'Гибкий модалка'): LessonPackage
    {
        return LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $name,
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 8,
            'price_cents' => 500000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    private function singleTemplate(string $name = 'Разовое модалка', int $priceCents = 150000): LessonPackage
    {
        return LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $name,
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => $priceCents,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    /**
     * @return array{student: User, slot: TeamScheduleSlot, flexUlps: list<UserLessonPackage>, singleUlps: list<UserLessonPackage>}
     */
    private function multiBindContext(): array
    {
        $student = $this->studentUser('Multi');
        $slot = $this->mondaySlot();

        $flexPackage = $this->flexibleTemplate('Гибкий A');
        $flexUlps = [];
        foreach (['Гибкий A #1', 'Гибкий A #2'] as $i => $label) {
            $flexUlps[] = UserLessonPackage::query()->create([
                'user_id' => $student->id,
                'lesson_package_id' => $flexPackage->id,
                'starts_at' => '2026-04-01',
                'ends_at' => '2026-12-31',
                'lessons_total' => 8,
                'lessons_remaining' => 8 - $i,
                'created_by' => $this->user->id,
            ]);
        }

        $singlePackage = $this->singleTemplate('Разовое B');
        $singleUlps = [];
        foreach ([1, 2] as $i) {
            $singleUlps[] = UserLessonPackage::query()->create([
                'user_id' => $student->id,
                'lesson_package_id' => $singlePackage->id,
                'starts_at' => null,
                'ends_at' => null,
                'lessons_total' => 1,
                'lessons_remaining' => 1,
                'fee_amount' => 500 + $i,
                'created_by' => $this->user->id,
            ]);
        }

        return compact('student', 'slot', 'flexUlps', 'singleUlps');
    }

    public function test_slot_modal_contains_bind_panel_ui_markers(): void
    {
        $this->grantPermission('lessonPackages.view');

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="schoolCalSlotModal"', $html);
        $this->assertStringContainsString('data-bs-backdrop="static"', $html);
        $this->assertStringContainsString('data-bs-keyboard="false"', $html);
        $this->assertStringContainsString('id="schoolCalSlotBindPanelsHost"', $html);
        $this->assertStringContainsString('school-cal-slot-bind-panel', $html);
        $this->assertStringContainsString('school-cal-slot-bind-panel__inner', $html);
        $this->assertStringContainsString('school-cal-slot-bind-dimmable', $html);
        $this->assertStringContainsString('data-school-cal-bind-cancel', $html);
        $this->assertStringContainsString('id="schoolCalSlotSingleFormWrap"', $html);
        $this->assertStringContainsString('id="schoolCalSlotFlexFormWrap"', $html);
        $this->assertStringContainsString('id="schoolCalSlotFixedFormWrap"', $html);
        $this->assertStringContainsString('id="schoolCalSlotSingleCreateFields"', $html);
        $this->assertStringContainsString('id="schoolCalSlotSingleTemplate"', $html);
        $this->assertStringContainsString('schoolCalSlotBindActionsCache', $html);
        $this->assertStringContainsString('cancelSchoolCalSlotBindPanel', $html);
        $this->assertStringContainsString('schoolCalSlotModalEscKeydown', $html);
        $this->assertStringContainsString('exitSchoolCalSlotBindFocusMode', $html);
        $this->assertStringContainsString('hideSchoolCalSlotBindPanel', $html);
        $this->assertStringContainsString('school-cal-slot-bind-panel--open', $html);
        $this->assertStringContainsString('school-cal-slot-modal--bind-focus', $html);
    }

    public function test_guest_is_denied_on_slot_modal_bind_endpoints(): void
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
            ['POST', route('admin.lesson-packages.school-schedule.assign-fixed'), [
                'user_id' => 1,
                'user_lesson_package_id' => 1,
                'team_schedule_slot_id' => 1,
                'anchor_date' => self::WEEK_MONDAY,
                'patterns' => [['weekday' => 1, 'time_start' => '14:00', 'time_end' => '15:00']],
            ]],
            ['POST', route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
                'user_id' => 1,
                'team_schedule_slot_id' => 1,
                'occurrence_date' => self::WEEK_MONDAY,
                'lesson_package_id' => 1,
                'fee_amount' => 1000,
            ]],
        ];

        foreach ($routes as [$method, $url, $data]) {
            $response = $this->call($method, $url, $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(200, $response->getStatusCode(), "Гость не должен получать пустой 200: {$method} {$url}");
        }
    }

    public function test_user_without_permission_gets_403_on_slot_modal_bind_endpoints(): void
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

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => 1,
            'fee_amount' => 1000,
        ])->assertForbidden();
    }

    public function test_slot_bind_actions_returns_independent_flexible_and_single_lesson_payloads(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['student' => $student, 'slot' => $slot, 'flexUlps' => $flexUlps, 'singleUlps' => $singleUlps] = $this->multiBindContext();

        $response = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('flexible.allowed', true)
            ->assertJsonCount(2, 'flexible.existing_assignments')
            ->assertJsonPath('single_lesson.allowed', true)
            ->assertJsonPath('single_lesson.mode', 'bind_existing')
            ->assertJsonCount(2, 'single_lesson.existing_assignments');

        $flexIds = collect($response->json('flexible.existing_assignments'))->pluck('id')->all();
        $singleIds = collect($response->json('single_lesson.existing_assignments'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$flexUlps[0]->id, $flexUlps[1]->id],
            $flexIds
        );
        $this->assertEqualsCanonicalizing(
            [$singleUlps[0]->id, $singleUlps[1]->id],
            $singleIds
        );
    }

    public function test_slot_bind_actions_json_structure_includes_all_subscription_types(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser('Struct');
        $slot = $this->mondaySlot();
        $this->singleTemplate();

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'flexible' => ['allowed', 'reason', 'existing_assignments'],
                'fixed' => ['allowed', 'reason', 'existing_assignments'],
                'single_lesson' => [
                    'allowed',
                    'reason',
                    'mode',
                    'existing_assignments',
                    'templates',
                ],
                'trial' => ['allowed', 'reason'],
            ]);
    }

    public function test_assign_flexible_ajax_json_contract_from_modal_flow(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['student' => $student, 'slot' => $slot, 'flexUlps' => $flexUlps] = $this->multiBindContext();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $flexUlps[1]->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Занятие привязано к абонементу.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'user_lesson_package_id' => $flexUlps[1]->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
    }

    public function test_assign_flexible_non_ajax_redirects_and_creates_calendar_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['flexUlps' => $flexUlps, 'slot' => $slot] = $this->multiBindContext();

        $this->post(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            '_token' => csrf_token(),
            'user_lesson_package_id' => $flexUlps[0]->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])
            ->assertRedirect(route('admin.lesson-packages.school-schedule'))
            ->assertSessionHas('status', 'Занятие привязано к абонементу.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_lesson_package_id' => $flexUlps[0]->id,
            'team_schedule_slot_id' => $slot->id,
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

    public function test_single_lesson_registration_ajax_create_new_contract_from_modal(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser('SingleAjax');
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate('Разовое AJAX', 200000);

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1999.99,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Разовое занятие записано в расписание.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
    }

    public function test_single_lesson_registration_non_ajax_returns_json_not_empty_html(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser('SingleNonAjax');
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate();

        $response = $this->post(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1200,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonPath('message', 'Разовое занятие записано в расписание.');
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
        ]);
    }

    public function test_single_lesson_registration_ajax_validation_returns_422_with_errors(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'team_schedule_slot_id', 'occurrence_date']);
    }

    public function test_viewer_with_permission_gets_200_on_school_schedule_page_and_bind_actions(): void
    {
        $this->grantPermission('lessonPackages.view');

        ['student' => $student, 'slot' => $slot] = $this->multiBindContext();

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalSlotBindPanelsHost', false)
            ->assertSee('data-school-cal-bind-cancel', false);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertOk();
    }
}
