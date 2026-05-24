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
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Страницы раздела «Абонементы» и read/write поверхности календаря школы возвращают 200 при наличии прав.
 */
final class LessonPackagesSectionAccessSmokeFeatureTest extends CrmTestCase
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

    public function test_lesson_packages_html_pages_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.table');

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('Абонементы', false);

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk();

        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('Расписание школы', false)
            ->assertSee('Добавить разовое занятие', false)
            ->assertSee('schoolCalSlotSingleFormWrap', false);

        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))
            ->assertOk();

        $this->get(route('admin.lesson-packages.team-schedule-slots'))
            ->assertOk();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Smoke пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 10,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.id', (int) $package->id);
    }

    public function test_school_schedule_calendar_json_and_safe_posts_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $studentId = (int) $this->user->id;

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk()->assertJsonStructure(['week_start', 'occurrences']);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
            'location_id' => $loc->id,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertJsonStructure(['view_start_min', 'view_end_min']);

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 480,
            'view_end_min' => 1200,
        ])->assertOk()->assertJsonPath('success', true);

        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))
            ->assertOk()
            ->assertJsonStructure(['flexible', 'fixed', 'single_lesson']);

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
            'user_id' => $studentId,
        ]))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', [
            'q' => '',
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

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $slot = TeamScheduleSlot::query()->where('partner_id', $this->partner->id)->firstOrFail();

        LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Smoke разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'single_lesson' => ['allowed', 'reason', 'mode', 'existing_assignments', 'templates'],
            ])
            ->assertJsonPath('single_lesson.allowed', true);

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => LessonPackage::query()
                ->where('partner_id', $this->partner->id)
                ->where('schedule_type', 'no_schedule')
                ->value('id'),
            'fee_amount' => 1000,
        ])->assertOk();

        $bindId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->value('id');

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bindId,
        ]))->assertOk();
    }

    public function test_lesson_packages_surface_forbidden_without_lesson_packages_view(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Smoke forbidden разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => 500,
            'created_by' => $this->user->id,
        ]);
        $bind = UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => self::WEEK_MONDAY,
            'created_by' => $this->user->id,
        ]);

        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.index'))->assertForbidden();
        $this->get(route('admin.lesson-packages.assignments'))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertForbidden();
        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();
        $this->get(route('admin.lesson-packages.occurrence-statuses.index'))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => 1,
            'fee_amount' => 1000,
        ])->assertForbidden();

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bind->id,
        ]))->assertForbidden();
    }
}
