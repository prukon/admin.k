<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Location;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка модалки слота (заголовок под название группы, строка локации по locations.view),
 * поле team_title в JSON недели для JS и сквозная проверка 200 для страницы и read-API календаря.
 */
final class LessonPackageSchoolScheduleUiAndCalendarAccessFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
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

    private function grantPermissionToUser(User $user, string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_slot_modal_markup_includes_title_element_date_block_and_location_when_locations_view(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('locations.view');
        $this->grantPermission('scheduleSlots.manage');

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="schoolCalSlotModalTitle"', $html);
        $this->assertStringContainsString('id="schoolCalSlotSummaryWhen"', $html);
        $this->assertStringContainsString('school-cal-slot-summary__when', $html);
        $this->assertStringContainsString('id="schoolCalLocation"', $html);
        $this->assertStringContainsString('>Все</option>', $html);
        $this->assertStringContainsString('value="none"', $html);
        $this->assertStringContainsString('Без объекта</option>', $html);
        $this->assertStringContainsString('id="schoolCalSlotSummaryLoc"', $html);
        $this->assertStringNotContainsString('id="schoolCalSlotSummaryTeam"', $html);

        $createFormPos = strpos($html, 'id="slotCreateForm"');
        $this->assertNotFalse($createFormPos);
        $createFormHtml = substr($html, $createFormPos, 4000);
        $locationPos = strpos($createFormHtml, 'js-slot-location-select');
        $teamSelectPos = strpos($createFormHtml, 'js-slot-team-select');
        $this->assertNotFalse($locationPos);
        $this->assertNotFalse($teamSelectPos);
        $this->assertLessThan($teamSelectPos, $locationPos, 'В модалке «Добавить слот» объект должен быть выше группы');
    }

    public function test_week_json_location_filter_none_returns_only_slots_without_object(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $teamWithLocation = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => $location->id,
        ]);

        $teamWithoutLocation = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'location_id' => null,
        ]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamWithLocation->id,
            'location_id' => $location->id,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamWithoutLocation->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $all = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk()->json('occurrences');

        $none = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
            'location_id' => 'none',
        ]))->assertOk()->json('occurrences');

        $this->assertGreaterThanOrEqual(2, count($all));
        $this->assertNotEmpty($none);
        foreach ($none as $occurrence) {
            $this->assertNull($occurrence['location_id']);
        }
    }

    public function test_slot_modal_and_toolbar_hide_location_markup_without_locations_view(): void
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');

        $html = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="schoolCalLocation"', $html);
        $this->assertStringNotContainsString('id="schoolCalSlotSummaryLoc"', $html);
        $this->assertStringContainsString('id="schoolCalSlotModalTitle"', $html);
        $this->assertStringContainsString('id="schoolCalSlotSummaryWhen"', $html);
    }

    public function test_school_schedule_week_json_includes_team_title_for_slot_modal_title_binding(): void
    {
        $this->grantPermission('lessonPackages.view');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа для заголовка модалки',
        ]);

        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $occurrences = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->json('occurrences');

        $this->assertNotEmpty($occurrences);
        $first = $occurrences[0];
        $this->assertArrayHasKey('team_title', $first);
        $this->assertSame('Группа для заголовка модалки', $first['team_title']);
    }

    public function test_school_schedule_page_loads_with_teams_bound_to_locations(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Календарь объект',
            'is_enabled' => true,
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа с объектом',
            'location_id' => $location->id,
        ]);

        $response = $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertViewHas('teams');

        $teams = $response->viewData('teams');
        $bound = $teams->firstWhere('title', 'Группа с объектом');
        $this->assertNotNull($bound);
        $this->assertSame($location->id, (int) $bound->location_id);
    }

    public function test_school_schedule_page_team_schedule_tab_and_all_calendar_read_endpoints_return_200(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.table');

        $this->get(route('admin.lesson-packages.school-schedule'))->assertOk();

        $this->get(route('admin.lesson-packages.team-schedule-slots'))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertJsonStructure(['view_start_min', 'view_end_min']);

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 480,
            'view_end_min' => 1200,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))
            ->assertOk()
            ->assertJsonStructure(['flexible', 'fixed', 'single_lesson']);

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
            'location_id' => $loc->id,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
            'location_id' => 'none',
        ]))->assertOk();

        $studentId = $this->user->id;

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))
            ->assertOk()
            ->assertJsonStructure(['packages']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => 0,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => $studentId,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', [
            'user_id' => 0,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', [
            'user_id' => $studentId,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', [
            'q' => '',
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', [
            'user_id' => 0,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', [
            'user_id' => $studentId,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-users-search', [
            'q' => '',
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.assignments.users-search', [
            'q' => '',
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))->assertOk();
    }

    public function test_school_schedule_surface_forbidden_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();

        $this->get(route('admin.lesson-packages.team-schedule-slots'))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertForbidden();
    }
}
