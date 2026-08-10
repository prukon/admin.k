<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фича «Прошедшие» + красная дата последнего занятия на /admin/lesson-packages/assignments.
 *
 * Правила:
 * - по умолчанию (чекбокс выключен) скрываются абоны с MAX(starts_at) строго &lt; сегодня;
 * - filter_past_lessons=1 — прошедшие тоже видны;
 * - без слотов — всегда в списке;
 * - last_lesson_is_past=true только если дата строго раньше сегодня (сегодня — не прошедшая);
 * - UI: text-danger в ulpLessonsRender при last_lesson_is_past.
 *
 * Фикстуры past/today/future завязаны на «сегодня» = 2026-08-10 (см. setUp).
 */
final class LessonPackageAssignmentsPastLessonsFilterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-10 12:00:00');

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function grantAssignmentsAccess(): void
    {
        foreach (['lessonPackages.view', 'setPrices.packageAssignments.view'] as $permissionName) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permissionName),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Контроль доступа
    // -------------------------------------------------------------------------

    public function test_guest_cannot_open_assignments_with_past_lessons_filter(): void
    {
        Auth::logout();

        $page = $this->get(route('admin.lesson-packages.assignments', [
            'filter_past_lessons' => '1',
        ]));
        $this->assertContains($page->getStatusCode(), [302, 401, 403]);

        $data = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_past_lessons' => '1',
        ]));
        $this->assertContains($data->getStatusCode(), [302, 401, 403]);
    }

    public function test_user_without_package_assignments_view_gets_403_on_past_lessons_data(): void
    {
        $this->seedPastFutureAndNoSlots();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($this->user);

        $this->get(route('admin.lesson-packages.assignments', [
            'filter_past_lessons' => '1',
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_past_lessons' => '1',
        ]))->assertForbidden();
    }

    public function test_user_without_lesson_packages_view_gets_403_on_past_lessons_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('admin.lesson-packages.assignments', [
            'filter_past_lessons' => '1',
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter_past_lessons' => '1',
        ]))->assertForbidden();
    }

    public function test_admin_can_load_assignments_data_with_past_lessons_filter_variants(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $this->seedPastFutureAndNoSlots();

        foreach (['', '0', '1', 'true', 'on', 'yes', 'no', 'false'] as $value) {
            $params = [
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'status' => '',
            ];
            if ($value !== '') {
                $params['filter_past_lessons'] = $value;
            }

            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('admin.lesson-packages.assignments.data', $params))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_superadmin_can_open_assignments_page_with_past_lessons_checked(): void
    {
        $this->asSuperadmin();

        $this->get(route('admin.lesson-packages.assignments', [
            'filter_past_lessons' => '1',
        ]))
            ->assertOk()
            ->assertSee('Назначение абонементов', false)
            ->assertSee('id="ulp-filter-past-lessons"', false);
    }

    // -------------------------------------------------------------------------
    // UI / Blade
    // -------------------------------------------------------------------------

    public function test_assignments_page_renders_past_lessons_checkbox_unchecked_by_default(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('name="filter_past_lessons"', false)
            ->assertSee('>Прошедшие<', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="ulp-filter-past-lessons"[^>]*\bchecked\b/',
            $html,
            'По умолчанию чекбокс «Прошедшие» выключен'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/class="collapse\s+show[^"]*"\s+id="ulpAssignmentsFiltersCollapse"/',
            $html,
            'Без активных фильтров панель не раскрыта'
        );
    }

    public function test_assignments_page_checks_past_lessons_and_expands_filters_when_query_set(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $html = $this->get(route('admin.lesson-packages.assignments', [
            'filter_past_lessons' => '1',
        ]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/class="collapse\s+show[^"]*"\s+id="ulpAssignmentsFiltersCollapse"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="ulp-filter-past-lessons"[^>]*\bchecked\b/',
            $html
        );
    }

    public function test_assignments_page_does_not_treat_zero_as_past_lessons_active_filter(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $html = $this->get(route('admin.lesson-packages.assignments', [
            'filter_past_lessons' => '0',
        ]))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="ulp-filter-past-lessons"[^>]*\bchecked\b/',
            $html
        );
    }

    // -------------------------------------------------------------------------
    // Логика фильтрации DataTable (дефолт / включён / границы)
    // -------------------------------------------------------------------------

    public function test_default_list_hides_past_keeps_future_and_no_slots(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedPastFutureAndNoSlots();

        $ids = $this->assignmentIds([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ]);

        $this->assertNotContains($ctx['past']->id, $ids, 'Прошедший абон скрыт по умолчанию');
        $this->assertContains($ctx['future']->id, $ids);
        $this->assertContains($ctx['today']->id, $ids, 'Последнее занятие = сегодня не считается прошедшим');
        $this->assertContains($ctx['noSlots']->id, $ids, 'Без слотов всегда виден');
    }

    public function test_past_lessons_checkbox_shows_past_without_hiding_others(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedPastFutureAndNoSlots();

        $ids = $this->assignmentIds([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
            'filter_past_lessons' => '1',
        ]);

        $this->assertContains($ctx['past']->id, $ids);
        $this->assertContains($ctx['future']->id, $ids);
        $this->assertContains($ctx['today']->id, $ids);
        $this->assertContains($ctx['noSlots']->id, $ids);
    }

    public function test_truthy_past_lessons_param_variants_include_past_assignment(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedPastFutureAndNoSlots();

        foreach (['1', 'true', 'on', 'yes'] as $value) {
            $ids = $this->assignmentIds([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'status' => '',
                'filter_past_lessons' => $value,
            ]);
            $this->assertContains(
                $ctx['past']->id,
                $ids,
                "filter_past_lessons={$value} должен показывать прошедшие"
            );
        }
    }

    public function test_falsy_past_lessons_param_variants_still_hide_past_assignment(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedPastFutureAndNoSlots();

        foreach (['0', 'false', 'no', 'off', ''] as $value) {
            $params = [
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'status' => '',
            ];
            if ($value !== '') {
                $params['filter_past_lessons'] = $value;
            }

            $ids = $this->assignmentIds($params);
            $this->assertNotContains(
                $ctx['past']->id,
                $ids,
                "filter_past_lessons={$value} не должен показывать прошедшие"
            );
        }
    }

    public function test_assignment_with_mixed_past_and_future_slots_stays_visible_by_default(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $mixed = $this->createAssignmentWithSlots('mixed', ['2026-08-01', '2026-08-14']);

        $ids = $this->assignmentIds([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ]);

        $this->assertContains($mixed->id, $ids);

        $row = $this->assignmentRow($mixed->id, [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ]);
        $this->assertNotNull($row);
        $this->assertSame('2026-08-14', $row['last_lesson_date']);
        $this->assertFalse((bool) $row['last_lesson_is_past']);
    }

    public function test_records_filtered_grows_when_past_lessons_enabled(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $this->seedPastFutureAndNoSlots();

        $base = [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ];

        $defaultFiltered = (int) $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.data', $base))
            ->assertOk()
            ->json('recordsFiltered');

        $withPastFiltered = (int) $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.data', $base + [
                'filter_past_lessons' => '1',
            ]))
            ->assertOk()
            ->json('recordsFiltered');

        $this->assertSame(3, $defaultFiltered, 'future + today + noSlots');
        $this->assertSame(4, $withPastFiltered, 'плюс past');
        $this->assertGreaterThan($defaultFiltered, $withPastFiltered);
    }

    // -------------------------------------------------------------------------
    // last_lesson_is_past (серверный флаг для красной даты)
    // -------------------------------------------------------------------------

    public function test_last_lesson_is_past_true_only_when_date_strictly_before_today(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();
        $ctx = $this->seedPastFutureAndNoSlots();

        $pastRow = $this->assignmentRow($ctx['past']->id, [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
            'filter_past_lessons' => '1',
        ]);
        $this->assertNotNull($pastRow);
        $this->assertSame('2026-08-09', $pastRow['last_lesson_date']);
        $this->assertTrue((bool) $pastRow['last_lesson_is_past']);

        $todayRow = $this->assignmentRow($ctx['today']->id, [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ]);
        $this->assertNotNull($todayRow);
        $this->assertSame('2026-08-10', $todayRow['last_lesson_date']);
        $this->assertFalse((bool) $todayRow['last_lesson_is_past']);

        $futureRow = $this->assignmentRow($ctx['future']->id, [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ]);
        $this->assertNotNull($futureRow);
        $this->assertSame('2026-08-14', $futureRow['last_lesson_date']);
        $this->assertFalse((bool) $futureRow['last_lesson_is_past']);

        $noSlotsRow = $this->assignmentRow($ctx['noSlots']->id, [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
        ]);
        $this->assertNotNull($noSlotsRow);
        $this->assertFalse((bool) $noSlotsRow['has_lessons']);
        $this->assertNull($noSlotsRow['last_lesson_date']);
        $this->assertFalse((bool) $noSlotsRow['last_lesson_is_past']);
    }

    public function test_other_partner_past_assignment_is_not_visible_even_with_filter(): void
    {
        $this->asAdmin();
        $this->grantAssignmentsAccess();

        $otherPartner = Partner::factory()->create();
        $otherStudent = User::factory()->create([
            'partner_id' => $otherPartner->id,
            'is_enabled' => 1,
        ]);
        $otherPackage = LessonPackage::factory()->create([
            'partner_id' => $otherPartner->id,
            'schedule_type' => 'flexible',
        ]);
        $otherAssignment = UserLessonPackage::query()->create([
            'user_id' => $otherStudent->id,
            'lesson_package_id' => $otherPackage->id,
            'lessons_total' => 2,
            'lessons_remaining' => 0,
            'fee_amount_cents' => 10000,
            'is_paid' => true,
            'created_by' => $this->user->id,
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'title' => 'Чужая группа past',
        ]);
        $otherSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $otherPartner->id,
            'team_id' => $otherTeam->id,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $otherPartner->id,
            'user_id' => $otherStudent->id,
            'user_lesson_package_id' => $otherAssignment->id,
            'team_schedule_slot_id' => $otherSlot->id,
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-31',
            'created_by' => $this->user->id,
        ]);

        $ids = $this->assignmentIds([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => '',
            'filter_past_lessons' => '1',
        ]);

        $this->assertNotContains($otherAssignment->id, $ids);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{past: UserLessonPackage, future: UserLessonPackage, today: UserLessonPackage, noSlots: UserLessonPackage}
     */
    private function seedPastFutureAndNoSlots(): array
    {
        return [
            'past' => $this->createAssignmentWithSlots('past', ['2026-08-09']),
            'future' => $this->createAssignmentWithSlots('future', ['2026-08-14']),
            'today' => $this->createAssignmentWithSlots('today', ['2026-08-10']),
            'noSlots' => $this->createAssignmentWithoutSlots('noslots'),
        ];
    }

    /**
     * @param  list<string>  $datesYmd
     */
    private function createAssignmentWithSlots(string $suffix, array $datesYmd): UserLessonPackage
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет '.$suffix,
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 600000,
            'is_active' => 1,
        ]);
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-31',
            'lessons_total' => 4,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 600000,
            'is_paid' => true,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа '.$suffix,
        ]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        foreach ($datesYmd as $date) {
            UserTeamScheduleSlot::query()->create([
                'partner_id' => $this->partner->id,
                'user_id' => $student->id,
                'user_lesson_package_id' => $assignment->id,
                'team_schedule_slot_id' => $slot->id,
                'starts_at' => $date,
                'ends_at' => '2026-08-31',
                'created_by' => $this->user->id,
            ]);
        }

        return $assignment;
    }

    private function createAssignmentWithoutSlots(string $suffix): UserLessonPackage
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::factory()->create([
            'partner_id' => $this->partner->id,
            'schedule_type' => 'no_schedule',
            'name' => 'Без слотов '.$suffix,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 100000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<int>
     */
    private function assignmentIds(array $params): array
    {
        return collect(
            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('admin.lesson-packages.assignments.data', $params))
                ->assertOk()
                ->json('data')
        )->pluck('id')->map(static fn ($id) => (int) $id)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function assignmentRow(int $assignmentId, array $params): ?array
    {
        $row = collect(
            $this->withHeaders($this->ajaxHeaders())
                ->getJson(route('admin.lesson-packages.assignments.data', $params))
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $assignmentId);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }
}
