<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Location;
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
 * Автоподстановка шаблона привязки фикс-абона из слотов группы (fixed.group_patterns):
 * GET slot-user-bind-actions, UI-маркеры, assign-fixed (AJAX + non-AJAX) с несколькими patterns,
 * контроль доступа.
 *
 * @see docs/documentation/school-schedule-calendar.html
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageSchoolScheduleFixedGroupPatternsFeatureTest extends CrmTestCase
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
            'name' => 'Группа'.$suffix,
            'lastname' => 'Фикс',
            'is_enabled' => 1,
        ]);
    }

    private function fixedTemplate(string $name = 'Фикс группа', int $lessonsCount = 8): LessonPackage
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

    private function createUlp(User $student, LessonPackage $package, int $lessons = 8): UserLessonPackage
    {
        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount_cents' => 5000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * @return array{team: Team, location: Location, mon: TeamScheduleSlot, wed: TeamScheduleSlot, friAm: TeamScheduleSlot, friPm: TeamScheduleSlot}
     */
    private function createGroupSlotsOnLocation(): array
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->forPartner($this->partner->id)->create();

        $base = [
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ];

        $mon = TeamScheduleSlot::query()->create($base + [
            'weekday' => 1,
            'time_start' => '14:00',
            'time_end' => '15:00',
        ]);
        $wed = TeamScheduleSlot::query()->create($base + [
            'weekday' => 3,
            'time_start' => '16:00',
            'time_end' => '17:00',
        ]);
        // Два слота в один день (пятница) — оба должны попасть в group_patterns.
        $friAm = TeamScheduleSlot::query()->create($base + [
            'weekday' => 5,
            'time_start' => '10:00',
            'time_end' => '11:00',
        ]);
        $friPm = TeamScheduleSlot::query()->create($base + [
            'weekday' => 5,
            'time_start' => '18:00',
            'time_end' => '19:00',
        ]);

        return [
            'team' => $team,
            'location' => $location,
            'mon' => $mon,
            'wed' => $wed,
            'friAm' => $friAm,
            'friPm' => $friPm,
        ];
    }

    public function test_school_schedule_page_contains_group_patterns_autofill_ui_markers(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('seedSchoolCalSlotFixedPatternsFromOccurrence', false)
            ->assertSee('addSchoolCalFixedPatternRow', false)
            ->assertSee('fillSchoolCalFixedPatternRow', false)
            ->assertSee('group_patterns', false)
            ->assertSee('schoolCalFixedPatternsHost', false)
            ->assertSee('schoolCalFixedAddPattern', false);
    }

    public function test_guest_is_denied_on_group_patterns_endpoints(): void
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
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_permission_gets_403_on_group_patterns_endpoints(): void
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

    public function test_viewer_with_permission_gets_200_on_page_and_bind_actions_with_group_patterns(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $ctx = $this->createGroupSlotsOnLocation();

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalOpenFixed', false);

        $res = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'fixed' => [
                    'allowed',
                    'reason',
                    'existing_assignments',
                    'group_patterns' => [
                        ['id', 'weekday', 'time_start', 'time_end'],
                    ],
                ],
            ]);

        $this->assertNotEmpty($res->json('fixed.group_patterns'));
        $this->assertNotSame('', (string) $res->getContent());
    }

    public function test_group_patterns_include_same_location_slots_including_same_weekday_different_times(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $ctx = $this->createGroupSlotsOnLocation();

        // Слот другой локации той же группы — не должен попасть.
        $otherLoc = Location::factory()->forPartner($this->partner->id)->create();
        $otherLocSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $ctx['team']->id,
            'location_id' => $otherLoc->id,
            'weekday' => 2,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        // Слот без объекта (location_id NULL) — не должен попасть при якоре с объектом.
        $nullLocSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $ctx['team']->id,
            'location_id' => null,
            'weekday' => 4,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $res = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertOk();

        $patterns = $res->json('fixed.group_patterns');
        $ids = array_column($patterns, 'id');

        $this->assertCount(4, $patterns);
        $this->assertContains($ctx['mon']->id, $ids);
        $this->assertContains($ctx['wed']->id, $ids);
        $this->assertContains($ctx['friAm']->id, $ids);
        $this->assertContains($ctx['friPm']->id, $ids);
        $this->assertNotContains($otherLocSlot->id, $ids);
        $this->assertNotContains($nullLocSlot->id, $ids);

        // Сортировка: weekday ASC, time_start ASC.
        $this->assertSame(
            [$ctx['mon']->id, $ctx['wed']->id, $ctx['friAm']->id, $ctx['friPm']->id],
            $ids
        );
    }

    public function test_group_patterns_include_siblings_whose_date_start_is_after_anchor_weekday(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->forPartner($this->partner->id)->create();

        // Типичный кейс после split/создания: у каждого дня свой date_start = первый день слота.
        // Раньше проверка slotActiveOnDate(sibling, понедельник) отсекала ср/пт.
        $mon = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '13:30',
            'time_end' => '14:30',
            'date_start' => '2026-08-03',
            'date_end' => '2027-08-08',
            'is_enabled' => 1,
        ]);
        $wed = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 3,
            'time_start' => '13:30',
            'time_end' => '14:30',
            'date_start' => '2026-08-05',
            'date_end' => '2027-08-08',
            'is_enabled' => 1,
        ]);
        $fri = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 5,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-08-07',
            'date_end' => '2027-08-08',
            'is_enabled' => 1,
        ]);

        $ids = array_column(
            $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
                'user_id' => $student->id,
                'team_schedule_slot_id' => $mon->id,
                'occurrence_date' => '2026-08-03',
            ]))->assertOk()->json('fixed.group_patterns'),
            'id'
        );

        $this->assertContains($mon->id, $ids);
        $this->assertContains($wed->id, $ids);
        $this->assertContains($fri->id, $ids);
        $this->assertCount(3, $ids);
    }

    public function test_group_patterns_exclude_disabled_and_out_of_range_sibling_slots(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->forPartner($this->partner->id)->create();

        $anchor = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $disabled = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 3,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 0,
        ]);

        $expired = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 5,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2020-01-01',
            'date_end' => '2020-12-31',
            'is_enabled' => 1,
        ]);

        $softDeleted = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 2,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $softDeleted->delete();

        $activeSibling = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 4,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $ids = array_column(
            $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
                'user_id' => $student->id,
                'team_schedule_slot_id' => $anchor->id,
                'occurrence_date' => self::WEEK_MONDAY,
            ]))->assertOk()->json('fixed.group_patterns'),
            'id'
        );

        $this->assertContains($anchor->id, $ids);
        $this->assertContains($activeSibling->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
        $this->assertNotContains($expired->id, $ids);
        $this->assertNotContains($softDeleted->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_group_patterns_present_when_fixed_allowed_and_when_calendar_row_blocks(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $ctx = $this->createGroupSlotsOnLocation();
        $package = $this->fixedTemplate();
        $ulp = $this->createUlp($student, $package, 4);

        $allowed = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('fixed.allowed', true)
            ->assertJsonPath('fixed.existing_assignments.0.id', $ulp->id)
            ->assertJsonCount(4, 'fixed.group_patterns');

        $this->assertNotEmpty($allowed->json('fixed.group_patterns'));

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => self::WEEK_MONDAY,
            'created_by' => $this->user->id,
        ]);

        // Даже при blocked allowed=false group_patterns остаются для UI (шаблон всё равно можно показать).
        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('fixed.allowed', false)
            ->assertJsonCount(4, 'fixed.group_patterns');
    }

    public function test_group_patterns_match_null_location_exactly(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $location = Location::factory()->forPartner($this->partner->id)->create();

        $anchorNull = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $siblingNull = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 3,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $withLocation = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => $location->id,
            'weekday' => 5,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $ids = array_column(
            $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
                'user_id' => $student->id,
                'team_schedule_slot_id' => $anchorNull->id,
                'occurrence_date' => self::WEEK_MONDAY,
            ]))->assertOk()->json('fixed.group_patterns'),
            'id'
        );

        $this->assertContains($anchorNull->id, $ids);
        $this->assertContains($siblingNull->id, $ids);
        $this->assertNotContains($withLocation->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_assign_fixed_ajax_with_group_patterns_creates_chain_rows_and_returns_message(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $ctx = $this->createGroupSlotsOnLocation();
        $ulp = $this->createUlp($student, $this->fixedTemplate('Ajax multi', 8), 8);

        $bind = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertOk();

        $patterns = collect($bind->json('fixed.group_patterns'))
            ->map(fn (array $p) => [
                'weekday' => $p['weekday'],
                'time_start' => $p['time_start'],
                'time_end' => $p['time_end'],
            ])
            ->values()
            ->all();

        $this->assertCount(4, $patterns);

        $response = $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => $patterns,
        ])
            ->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $this->assertNotSame('', (string) $response->getContent());

        $created = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->count();
        $this->assertSame(8, $created);

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['mon']->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['wed']->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => '2026-05-06',
        ]);

        $ulp->refresh();
        $this->assertNotNull($ulp->starts_at);
        $this->assertNotNull($ulp->ends_at);
    }

    public function test_assign_fixed_non_ajax_with_group_patterns_redirects_and_creates_rows_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $ctx = $this->createGroupSlotsOnLocation();
        $ulp = $this->createUlp($student, $this->fixedTemplate('Non-Ajax multi', 8), 8);

        $patterns = [
            ['weekday' => 1, 'time_start' => '14:00', 'time_end' => '15:00'],
            ['weekday' => 3, 'time_start' => '16:00', 'time_end' => '17:00'],
            ['weekday' => 5, 'time_start' => '10:00', 'time_end' => '11:00'],
            ['weekday' => 5, 'time_start' => '18:00', 'time_end' => '19:00'],
        ];

        $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(route('admin.lesson-packages.school-schedule.assign-fixed'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'user_lesson_package_id' => $ulp->id,
                'team_schedule_slot_id' => $ctx['mon']->id,
                'anchor_date' => self::WEEK_MONDAY,
                'patterns' => $patterns,
            ])
            ->assertRedirect(route('admin.lesson-packages.school-schedule'))
            ->assertSessionHas('status', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $this->assertSame(
            8,
            UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count()
        );
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['friAm']->id,
            'user_lesson_package_id' => $ulp->id,
        ]);
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $ctx['friPm']->id,
            'user_lesson_package_id' => $ulp->id,
        ]);
    }

    public function test_assign_fixed_ajax_validation_returns_422_with_errors_not_500(): void
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
