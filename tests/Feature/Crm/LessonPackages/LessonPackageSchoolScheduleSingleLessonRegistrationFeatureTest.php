<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разовое занятие в калendаре школы: eligibility, создание назначения, привязка, отмена.
 */
final class LessonPackageSchoolScheduleSingleLessonRegistrationFeatureTest extends CrmTestCase
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

    private function studentUser(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'Разовый',
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

    private function singleTemplate(string $name = 'Разовое тест', int $priceCents = 200000): LessonPackage
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

    public function test_slot_bind_actions_single_lesson_allowed_create_new_without_existing_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $this->singleTemplate();

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('single_lesson.allowed', true)
            ->assertJsonPath('single_lesson.mode', 'create_new')
            ->assertJsonPath('single_lesson.existing_assignments', [])
            ->assertJsonCount(1, 'single_lesson.templates');
    }

    public function test_slot_bind_actions_single_lesson_bind_existing_when_free_assignment_exists(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate('Разовое свободное');

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => 1500,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->assertJsonPath('single_lesson.allowed', true)
            ->assertJsonPath('single_lesson.mode', 'bind_existing')
            ->assertJsonPath('single_lesson.existing_assignments.0.id', $ulp->id)
            ->assertJsonPath('single_lesson.templates', []);
    }

    public function test_store_single_lesson_registration_creates_assignment_and_calendar_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate('Разовое новое', 180000);

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1750.50,
        ])->assertOk()
            ->assertJsonPath('message', 'Разовое занятие записано в расписание.');

        $ulp = UserLessonPackage::query()
            ->where('user_id', $student->id)
            ->where('lesson_package_id', $package->id)
            ->first();

        $this->assertNotNull($ulp);
        $this->assertSame('1750.50', number_format((float) $ulp->fee_amount, 2, '.', ''));

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
    }

    public function test_store_single_lesson_registration_binds_existing_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate();

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => 900,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_lesson_package_id' => $ulp->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
        ]);
        $this->assertSame(1, UserLessonPackage::query()->where('user_id', $student->id)->count());
    }

    public function test_two_single_lessons_same_day_different_slots_allowed(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slotA = $this->mondaySlot('10:00', '11:00');
        $slotB = $this->mondaySlot('12:00', '13:00');
        $package = $this->singleTemplate();

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slotA->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1000,
        ])->assertOk();

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slotB->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'lesson_package_id' => $package->id,
            'fee_amount' => 1100,
        ])->assertOk();

        $this->assertSame(2, UserLessonPackage::query()->where('user_id', $student->id)->count());
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_destroy_single_lesson_registration_removes_calendar_row_but_keeps_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate();

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

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_lesson_package_id' => $ulp->id,
        ])->assertOk();

        /** @var UserTeamScheduleSlot $bind */
        $bind = UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bind->id,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Запись разового занятия отменена. Назначение абонемента сохранено.');

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $bind->id]);
        $this->assertDatabaseHas('user_lesson_packages', ['id' => $ulp->id]);

        $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))
            ->assertJsonPath('single_lesson.mode', 'bind_existing');
    }

    public function test_destroy_single_lesson_registration_restores_lessons_remaining_after_consumed_status(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate();

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

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_lesson_package_id' => $ulp->id,
        ])->assertOk();

        $attended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $attended->id,
        ])->assertOk();

        $ulp->refresh();
        $this->assertSame(0, $ulp->lessons_remaining);

        /** @var UserTeamScheduleSlot $bind */
        $bind = UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bind->id,
        ]))->assertOk();

        $ulp->refresh();
        $this->assertSame(1, $ulp->lessons_remaining);
        $this->assertDatabaseMissing('user_lesson_occurrence_status_events', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
        ]);
    }

    public function test_destroy_single_lesson_registration_writes_audit_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate();

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

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_lesson_package_id' => $ulp->id,
        ])->assertOk();

        $bind = UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();

        $this->deleteJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.destroy', [
            'userTeamScheduleSlot' => $bind->id,
        ]))->assertOk();

        $this->assertDatabaseHas('my_logs', [
            'partner_id' => $this->partner->id,
            'type' => 60,
            'action' => 602,
            'user_id' => $student->id,
            'target_type' => UserTeamScheduleSlot::class,
            'target_id' => $bind->id,
        ]);
    }

    public function test_week_json_includes_is_single_lesson_for_no_schedule_registration(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot();
        $package = $this->singleTemplate('Разовое JSON');

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => 700,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.single-lesson-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_lesson_package_id' => $ulp->id,
        ])->assertOk();

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_MONDAY]))
            ->assertOk()
            ->json('occurrences');

        $hit = collect($week)->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $slot->id);
        $this->assertNotNull($hit);
        $this->assertTrue(collect($hit['registrations'] ?? [])->contains(
            fn (array $reg): bool => ($reg['is_single_lesson'] ?? false) === true
                && ($reg['schedule_type'] ?? '') === 'no_schedule'
        ));
    }
}
