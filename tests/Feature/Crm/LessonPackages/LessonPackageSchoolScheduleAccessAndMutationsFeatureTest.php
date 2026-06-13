<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Location;
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
 * Доступ к странице «Расписание школы», все читающие JSON-эндпоинты календаря (200 при lessonPackages.view),
 * пробные занятия (поиск учеников, eligibility, POST/DELETE trial), контроль доступа к мутациям слотов
 * (scheduleSlots.manage) и сквозной сценарий API календаря.
 */
final class LessonPackageSchoolScheduleAccessAndMutationsFeatureTest extends CrmTestCase
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

    public function test_school_schedule_page_returns_200_and_core_markup_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('Расписание школы', false)
            ->assertSee('schoolCalGrid', false)
            ->assertSee('schoolCalSlotModal', false)
            ->assertSee('schoolCalSlotUserSelect', false)
            ->assertSee('schoolCalOpenTrial', false)
            ->assertSee('Добавить пробное занятие', false)
            ->assertSee('schoolCalOpenSingle', false)
            ->assertSee('Добавить разовое занятие', false)
            ->assertSee('schoolCalSlotSingleFormWrap', false)
            ->assertSee('schoolCalSlotSingleSubmit', false)
            ->assertSee('singleLessonRegistrationStore', false)
            ->assertSee('Привязать гибкий абонемент', false)
            ->assertSee('Привязать фиксированный абонемент', false)
            ->assertSee('historyModal', false)
            ->assertSee('fa-clock-rotate-left', false)
            ->assertDontSee('Изменить занятие', false);
    }

    public function test_school_schedule_page_shows_slot_management_ui_when_schedule_slots_manage_granted(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.manage');

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('Изменить занятие', false)
            ->assertSee('schoolCalSlotChangeLessonBtn', false)
            ->assertSee('slotEditModal', false)
            ->assertSee('slotEditOccurrenceMutations', false)
            ->assertSee('slotEditSkipOccurrenceBtn', false)
            ->assertSee('slotEditTruncateOccurrenceBtn', false);
    }

    public function test_all_school_schedule_read_json_endpoints_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $studentId = $this->user->id;

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonStructure(['week_start', 'occurrences']);

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
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))
            ->assertOk()
            ->assertJsonStructure(['flexible', 'fixed', 'single_lesson']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))
            ->assertOk()
            ->assertJsonStructure(['packages']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => 0,
        ]))->assertOk()
            ->assertExactJson(['assignments' => []]);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', [
            'user_id' => $studentId,
        ]))->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', [
            'user_id' => $studentId,
        ]))->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search', [
            'q' => '',
        ]))->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', [
            'user_id' => 0,
        ]))->assertOk()
            ->assertExactJson(['assignments' => []]);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', [
            'user_id' => $studentId,
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
            'weekday' => 1,
            'time_start' => '15:00',
            'time_end' => '16:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $trialStudent->id,
            'team_schedule_slot_id' => $trialSlot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertOk()
            ->assertJsonStructure(['allowed', 'reason'])
            ->assertJsonPath('allowed', true);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $trialStudent->id,
            'team_schedule_slot_id' => $trialSlot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertOk()
            ->assertJsonStructure([
                'flexible' => ['allowed', 'reason'],
                'fixed' => ['allowed', 'reason'],
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

    /**
     * Создание и удаление пробной записи доступны при lessonPackages.view и возвращают 200.
     */
    public function test_occurrence_status_trial_store_and_history_return_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

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
            'time_start' => '08:00',
            'time_end' => '09:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => self::WEEK_MONDAY,
            'is_trial_lesson' => true,
            'created_by' => $this->user->id,
        ]);

        $status = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        $this->actingAs($this->user);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'lesson_occurrence_status_id' => $status->id,
        ])->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.occurrence-status.history', [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['events'])
            ->assertJsonCount(1, 'events');

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk();
    }

    public function test_trial_registration_store_and_destroy_return_200_with_lesson_packages_view(): void
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
            'time_start' => '17:00',
            'time_end' => '18:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $trialId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', self::WEEK_MONDAY)
            ->where('is_trial_lesson', true)
            ->value('id');
        $this->assertGreaterThan(0, $trialId);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', [
            'userTeamScheduleSlot' => $trialId,
        ]))->assertOk();

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $trialId]);
    }

    /**
     * Создание и отмена записи разового занятия из модалки слота — 200 при lessonPackages.view.
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
            'time_start' => '19:00',
            'time_end' => '20:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Разовое доступ',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 200000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1800,
        ])->assertOk()
            ->assertJsonPath('message', 'Разовое занятие записано в расписание.');

        $bindId = (int) UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', self::WEEK_MONDAY)
            ->value('id');
        $this->assertGreaterThan(0, $bindId);

        $ulpId = (int) UserTeamScheduleSlot::query()->whereKey($bindId)->value('user_lesson_package_id');
        $this->assertGreaterThan(0, $ulpId);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bindId,
        ]))->assertOk()
            ->assertJsonPath('message', 'Запись разового занятия отменена. Назначение абонемента сохранено.');

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $bindId]);
        $this->assertDatabaseHas('user_lesson_packages', ['id' => $ulpId]);
    }

    public function test_single_lesson_registration_endpoints_forbidden_without_lesson_packages_view(): void
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
            'time_start' => '21:00',
            'time_end' => '22:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);
        $package = LessonPackage::query()->create([
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

        $user = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1000,
        ])->assertForbidden();

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bind->id,
        ]))->assertForbidden();
    }

    public function test_occurrence_status_endpoints_forbidden_without_lesson_packages_view(): void
    {
        $user = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => 1,
            'lesson_occurrence_status_id' => 1,
        ])->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.occurrence-status.history', [
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => 1,
        ]))->assertForbidden();
    }

    public function test_team_slot_mutations_return_403_without_schedule_slots_manage(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
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

        $this->getJson(route('admin.team-schedule-slots.show', $slot))->assertOk();

        $payload = [
            'team_id' => $team->id,
            'weekday' => 2,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '2026-12-31',
            'apply_changes_from' => '2026-01-01',
            'is_enabled' => 1,
        ];

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'weekday' => 5,
            'time_start' => '18:00',
            'time_end' => '19:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertForbidden();

        $this->putJson(route('admin.team-schedule-slots.update', $slot), $payload)->assertForbidden();

        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $slot), [
            'occurrence_date' => '2026-05-04',
        ])->assertForbidden();

        $this->postJson(route('admin.team-schedule-slots.truncate-from-date', $slot), [
            'occurrence_date' => '2026-05-11',
        ])->assertForbidden();

        $this->deleteJson(route('admin.team-schedule-slots.destroy', $slot))->assertForbidden();
    }

    public function test_calendar_slot_mutation_api_full_flow_returns_200_with_manage_permissions(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

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
        $slot = TeamScheduleSlot::query()->findOrFail($slotId);

        $this->getJson(route('admin.team-schedule-slots.show', $slot))
            ->assertOk()
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
            'occurrence_date' => '2026-05-04',
        ])->assertOk();

        $this->postJson(route('admin.team-schedule-slots.truncate-from-date', $slot), [
            'occurrence_date' => '2026-06-01',
        ])->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))->assertOk();
    }
}
