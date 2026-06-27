<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к вкладке календаря «Расписание школы», JSON API недели и пробных записей (lessonPackages.view).
 */
final class SchoolScheduleCalendarAccessFeatureTest extends CrmTestCase
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
            ->assertSee('Добавить пробное занятие', false)
            ->assertSee('Добавить разовое занятие', false)
            ->assertSee('schoolCalSlotSingleFormWrap', false)
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

        $this->getJson(route('admin.lesson-packages.assignments.users-search', [
            'q' => '',
        ]))->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))->assertOk()
            ->assertJsonStructure(['allowed', 'reason']);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))->assertOk()
            ->assertJsonStructure([
                'flexible' => ['allowed', 'reason', 'existing_assignments'],
                'fixed' => ['allowed', 'reason', 'existing_assignments'],
                'single_lesson' => ['allowed', 'reason', 'mode', 'existing_assignments', 'templates'],
                'trial' => ['allowed', 'reason'],
            ]);

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

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $occStatus = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $trialSlot->id,
            'occurrence_date' => '2026-05-04',
            'user_id' => $trialStudent->id,
            'lesson_occurrence_status_id' => $occStatus->id,
        ])->assertOk()
            ->assertJsonPath('event.lesson_occurrence_status.code', 'attended');

        $this->getJson(route('admin.lesson-packages.school-schedule.occurrence-status.history', [
            'team_schedule_slot_id' => $trialSlot->id,
            'occurrence_date' => '2026-05-04',
            'user_id' => $trialStudent->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'events');

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => '2026-05-04',
        ]))
            ->assertOk()
            ->assertJsonStructure(['week_start', 'occurrences']);

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

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $trialStudent->id,
            'team_schedule_slot_id' => $trialSlot->id,
            'occurrence_date' => '2026-05-04',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'flexible' => ['allowed', 'reason', 'existing_assignments'],
                'fixed' => ['allowed', 'reason', 'existing_assignments'],
                'single_lesson' => ['allowed', 'reason', 'mode', 'existing_assignments', 'templates'],
                'trial' => ['allowed', 'reason'],
            ]);
    }

    /**
     * Без lessonPackages.view недоступны страница и все перечисленные JSON-эндпоинты календаря школы (403).
     */
    public function test_school_schedule_page_and_calendar_json_endpoints_forbidden_without_lesson_packages_view(): void
    {
        $trialStudent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $trialTeam = Team::factory()->create(['partner_id' => $this->partner->id]);
        $trialSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $trialTeam->id,
            'location_id' => null,
            'weekday' => 2,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $trialOccurrence = '2026-05-05';
        $trialUtss = UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trialStudent->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $trialSlot->id,
            'starts_at' => $trialOccurrence,
            'ends_at' => $trialOccurrence,
            'is_trial_lesson' => true,
            'created_by' => $this->user->id,
        ]);

        $singleSlot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $trialTeam->id,
            'location_id' => null,
            'weekday' => 2,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $singlePackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Forbidden разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $singleUlp = UserLessonPackage::query()->create([
            'user_id' => $trialStudent->id,
            'lesson_package_id' => $singlePackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => 500,
            'created_by' => $this->user->id,
        ]);
        $singleUtss = UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trialStudent->id,
            'user_lesson_package_id' => $singleUlp->id,
            'team_schedule_slot_id' => $singleSlot->id,
            'starts_at' => $trialOccurrence,
            'ends_at' => $trialOccurrence,
            'created_by' => $this->user->id,
        ]);

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

        $this->getJson(route('admin.lesson-packages.assignments.users-search', [
            'q' => '',
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => 0,
            'team_schedule_slot_id' => 0,
            'occurrence_date' => '',
        ]))->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => '2026-05-04',
        ]))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => '2026-05-04',
        ])->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => 1,
            'fee_amount' => 1000,
        ])->assertForbidden();

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $singleUtss->id,
        ]))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => 1,
            'occurrence_date' => '2026-05-04',
            'user_id' => 1,
            'lesson_occurrence_status_id' => 1,
        ])->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.occurrence-status.history', [
            'team_schedule_slot_id' => 1,
            'occurrence_date' => '2026-05-04',
            'user_id' => 1,
        ]))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => 1,
            'user_lesson_package_id' => 1,
            'team_schedule_slot_id' => 1,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
            ],
        ])->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => 1,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertForbidden();

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialUtss->id,
        ]))->assertForbidden();
    }

    /**
     * Привязка гибкого, фиксированного и разового абонемента из календаря — успешные POST возвращают 200 при lessonPackages.view.
     */
    public function test_school_schedule_assign_flexible_fixed_and_single_lesson_posts_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $flexPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Доступ: гибкий',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 6,
            'price_cents' => 3000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $flexUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $flexPackage->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 6,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);
        $slotFlex = TeamScheduleSlot::query()->create([
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

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $flexUlp->id,
            'team_schedule_slot_id' => $slotFlex->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk()
            ->assertJsonPath('message', 'Занятие привязано к абонементу.');

        $flexUlp->refresh();
        $this->assertSame(2, (int) $flexUlp->lessons_remaining);

        $fixedPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Доступ: фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $fixedUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $fixedPackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '50.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
        $slotFixed = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '15:00',
            'time_end' => '16:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $fixedUlp->id,
            'team_schedule_slot_id' => $slotFixed->id,
            'anchor_date' => self::WEEK_MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $fixedUlp->refresh();
        $this->assertSame(1, (int) $fixedUlp->lessons_remaining, 'Привязка к календарю не списывает занятия — только статусы с consumes_lesson.');

        $singlePackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Доступ: разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $singleUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $singlePackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'created_by' => $this->user->id,
        ]);
        $slotSingle = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '18:00',
            'time_end' => '19:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => $singleUlp->id,
            'team_schedule_slot_id' => $slotSingle->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk()
            ->assertJsonPath('message', 'Разовое занятие записано в расписание.');

        $singleUlp->refresh();
        $this->assertSame(1, (int) $singleUlp->lessons_remaining);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk()
            ->assertJsonStructure(['week_start', 'occurrences']);
    }

    /**
     * POST/DELETE single-lesson-registration из модалки слота возвращают 200 при lessonPackages.view.
     */
    public function test_single_lesson_registration_store_and_destroy_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

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
            'time_start' => '20:00',
            'time_end' => '21:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Разовое календарь',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 150000,
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
            ->assertJsonPath('single_lesson.allowed', true)
            ->assertJsonPath('single_lesson.mode', 'create_new');

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1500,
        ])->assertOk();

        $bindId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->value('id');
        $this->assertGreaterThan(0, $bindId);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bindId,
        ]))->assertOk();
    }
}
