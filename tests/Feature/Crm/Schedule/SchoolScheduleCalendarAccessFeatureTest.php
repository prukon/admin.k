<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к вкладке календаря «Расписание школы», JSON API недели и пробных записей (lessonPackages.view).
 */
final class SchoolScheduleCalendarAccessFeatureTest extends CrmTestCase
{
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

    public function test_school_schedule_page_forbidden_without_lesson_packages_view(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertStatus(403);
    }

    public function test_school_schedule_page_ok_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalGrid', false)
            ->assertSee('schoolCalTrialModal', false)
            ->assertSee('Пробное занятие', false)
            ->assertSee('Загрузка расписания', false);
    }

    public function test_school_schedule_json_endpoints_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => '2026-05-04',
        ]))
            ->assertOk()
            ->assertJsonStructure(['week_start', 'occurrences']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))->assertOk()
            ->assertJsonStructure(['packages']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => 0,
        ]))->assertOk()
            ->assertJsonPath('assignments', []);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', [
            'q' => '',
        ]))->assertOk()
            ->assertJsonStructure(['results']);
    }

    public function test_slots_index_and_calendar_workflow_returns_200_when_permissions_granted(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');
        $this->grantPermission('scheduleSlots.table');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->followingRedirects()
            ->get(route('admin.team-schedule-slots.index'))
            ->assertOk()
            ->assertSee('Расписание школы')
            ->assertSee('Таблица занятий');

        $store = $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();

        $slotId = (int) ($store->json('slot.id'));
        $this->assertGreaterThan(0, $slotId);

        /** @var TeamScheduleSlot $slot */
        $slot = TeamScheduleSlot::query()->findOrFail($slotId);

        $this->getJson(route('admin.team-schedule-slots.show', $slot))->assertOk()
            ->assertJsonPath('id', $slotId);

        $this->putJson(route('admin.team-schedule-slots.update', $slot), [
            'team_id' => $team->id,
            'weekday' => 1,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ])->assertOk();

        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $slot), [
            'occurrence_date' => '2026-06-01',
        ])->assertOk();

        $this->postJson(route('admin.team-schedule-slots.truncate-from-date', $slot), [
            'occurrence_date' => '2026-06-15',
        ])->assertOk();

        $this->deleteJson(route('admin.team-schedule-slots.destroy', $slot))->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => '2026-06-01',
        ]))->assertOk();
    }

    /**
     * Страница «Расписание школы» и все основные JSON-ручки календаря доступны при lessonPackages.view и отвечают 200.
     */
    public function test_school_schedule_page_and_all_calendar_json_endpoints_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('schoolCalViewSettingsModal', false);

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))
            ->assertOk()
            ->assertJsonStructure(['view_start_min', 'view_end_min']);

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 540,
            'view_end_min' => 1260,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))
            ->assertOk()
            ->assertJsonStructure(['flexible', 'fixed', 'single_lesson']);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => '2026-05-04',
        ]))
            ->assertOk()
            ->assertJsonStructure(['week_start', 'occurrences']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))
            ->assertOk()
            ->assertJsonStructure(['packages']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => 0,
        ]))->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', [
            'user_id' => 0,
        ]))->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', [
            'q' => '',
        ]))->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', [
            'user_id' => 0,
        ]))->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-users-search', [
            'q' => '',
        ]))->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))->assertOk()
            ->assertJsonStructure(['allowed', 'reason']);

        $roleUserId = Role::query()->where('name', 'user')->value('id');
        $this->assertNotNull($roleUserId);
        $trialStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => (int) $roleUserId,
            'is_enabled' => 1,
        ]);
        $trialTeam = Team::factory()->create(['partner_id' => $this->partner->id]);
        $trialSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $trialTeam->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '19:00',
            'time_end' => '20:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $trialStudent->id,
            'team_schedule_slot_id' => $trialSlot->id,
            'occurrence_date' => '2026-05-04',
        ]))->assertOk()
            ->assertJsonPath('allowed', true);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $trialStudent->id,
            'team_schedule_slot_id' => $trialSlot->id,
            'occurrence_date' => '2026-05-04',
        ])->assertOk();

        $trialRowId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $trialStudent->id)
            ->where('team_schedule_slot_id', $trialSlot->id)
            ->where('is_trial_lesson', true)
            ->value('id');
        $this->assertGreaterThan(0, $trialRowId);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialRowId,
        ]))->assertOk();

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $trialRowId]);
    }

    /**
     * Без lessonPackages.view недоступны страница и все перечисленные JSON-эндпоинты календаря школы (403).
     */
    public function test_school_schedule_page_and_calendar_json_endpoints_forbidden_without_lesson_packages_view(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.view-settings'))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.view-settings.save'), [
            'view_start_min' => 540,
            'view_end_min' => 1260,
        ])->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => '2026-05-04',
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => 0,
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', [
            'user_id' => 0,
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', [
            'q' => '',
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', [
            'user_id' => 0,
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-users-search', [
            'q' => '',
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => '2026-05-04',
        ])->assertForbidden();
    }
}
