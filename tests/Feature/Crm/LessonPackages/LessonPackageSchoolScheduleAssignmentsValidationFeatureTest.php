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
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Набор проверок/валидаций для всех типов привязки из календаря школы:
 * flexible, fixed, single (no_schedule) и trial.
 */
final class LessonPackageSchoolScheduleAssignmentsValidationFeatureTest extends CrmTestCase
{
    private const MONDAY = '2026-05-04';
    private const WEDNESDAY = '2026-05-06';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::MONDAY)->format('N'),
            'Тестовая дата должна быть понедельником (ISO weekday 1).'
        );

        $this->assertSame(
            3,
            (int) CarbonImmutable::parse(self::WEDNESDAY)->format('N'),
            'Тестовая дата должна быть средой (ISO weekday 3).'
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
            'is_enabled' => 1,
        ]);
    }

    private function mondaySlot(string $timeStart = '10:00', string $timeEnd = '11:00'): TeamScheduleSlot
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

    public function test_assign_flexible_422_when_occurrence_date_weekday_does_not_match_slot(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot('09:00', '10:00');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий weekday',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 5,
            'lessons_remaining' => 5,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEDNESDAY,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_date']);
    }

    public function test_assign_flexible_422_when_lessons_total_limit_reached(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot('09:00', '10:00');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий без остатка',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 0,
            'lessons_remaining' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Достигнут лимит занятий в календаре для этого абонемента.')
            ->assertJsonPath('errors.user_lesson_package_id.0', 'Достигнут лимит занятий в календаре для этого абонемента.');
    }

    public function test_assign_single_lesson_422_when_assignment_is_not_no_schedule(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot('11:00', '12:00');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Не разовое',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 5,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Выберите назначение с типом «разовое занятие».');
    }

    public function test_assign_fixed_422_when_time_overlaps_with_existing_user_lesson_in_same_weekday(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $slotCandidate = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '12:00',
            'time_end' => '12:50',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $teamOther = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slotOther = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamOther->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '12:00',
            'time_end' => '12:50',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        // Уже существующая запись ученика на эту дату (пробное/любой календарный ряд тоже конфликтует по времени).
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slotOther->id,
            'starts_at' => self::MONDAY,
            'ends_at' => self::MONDAY,
            'is_trial_lesson' => true,
            'created_by' => $this->user->id,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс конфликт',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
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
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotCandidate->id,
            'anchor_date' => self::MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '12:00', 'time_end' => '12:50'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.patterns.0', 'Конфликт расписания на '.self::MONDAY.': время 12:00–12:50 пересекается с уже существующей записью ученика (12:00–12:50).');
    }

    public function test_assign_fixed_allows_when_existing_utss_ends_at_covers_anchor_but_starts_at_differs(): void
    {
        $this->grantPermission('lessonPackages.view');

        // Следующий понедельник после MONDAY (2026-05-04).
        $nextMonday = '2026-05-11';
        $this->assertSame(1, (int) CarbonImmutable::parse($nextMonday)->format('N'));

        $student = $this->studentUser();
        $slot = $this->mondaySlot('14:15', '16:00');

        $oldPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $oldUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $oldPackage->id,
            'starts_at' => self::MONDAY,
            'ends_at' => '2026-06-03',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        // Как при assign-fixed: starts_at = дата занятия, ends_at = конец периода абонемента.
        // «Хвост» ends_at покрывает nextMonday, но реальной записи на nextMonday нет.
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $oldUlp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::MONDAY,
            'ends_at' => '2026-06-03',
            'created_by' => $this->user->id,
        ]);

        $newPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Новый фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $newUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $newPackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $newUlp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => $nextMonday,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '14:15', 'time_end' => '16:00'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'user_lesson_package_id' => $newUlp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => $nextMonday,
        ]);
    }

    public function test_assign_fixed_422_when_existing_fixed_utss_starts_at_equals_anchor(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot('14:15', '16:00');

        $oldPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый фикс на якорь',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $oldUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $oldPackage->id,
            'starts_at' => self::MONDAY,
            'ends_at' => '2026-06-03',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $oldUlp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::MONDAY,
            'ends_at' => '2026-06-03',
            'created_by' => $this->user->id,
        ]);

        $newPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Новый фикс конфликт на starts_at',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $newUlp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $newPackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $newUlp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => self::MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '14:15', 'time_end' => '16:00'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.patterns.0',
                'Конфликт расписания на '.self::MONDAY.': время 14:15–16:00 пересекается с уже существующей записью ученика (14:15–16:00).'
            );

        $this->assertSame(
            0,
            UserTeamScheduleSlot::query()->where('user_lesson_package_id', $newUlp->id)->count()
        );
    }

    public function test_assign_fixed_422_when_chain_non_anchor_occurrence_overlaps_existing_lesson(): void
    {
        $this->grantPermission('lessonPackages.view');

        $thursday = '2026-05-07';
        $this->assertSame(4, (int) CarbonImmutable::parse($thursday)->format('N'));

        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slotMon = TeamScheduleSlot::query()->create([
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
        $slotThu = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 4,
            'time_start' => '10:00',
            'time_end' => '11:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        // Конфликт на втором вхождении цепочки (четверг), не на якоре (понедельник).
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slotThu->id,
            'starts_at' => $thursday,
            'ends_at' => $thursday,
            'is_trial_lesson' => true,
            'created_by' => $this->user->id,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс цепочка конфликт',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 2,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 2,
            'lessons_remaining' => 2,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotMon->id,
            'anchor_date' => self::MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '15:00', 'time_end' => '16:00'],
                ['weekday' => 4, 'time_start' => '10:00', 'time_end' => '11:00'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.patterns.0',
                'Конфликт расписания на '.$thursday.': время 10:00–11:00 пересекается с уже существующей записью ученика (10:00–11:00).'
            );

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_assign_fixed_allows_same_date_when_clock_intervals_do_not_overlap(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slotMorning = TeamScheduleSlot::query()->create([
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
        $slotEvening = TeamScheduleSlot::query()->create([
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

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slotMorning->id,
            'starts_at' => self::MONDAY,
            'ends_at' => self::MONDAY,
            'is_trial_lesson' => true,
            'created_by' => $this->user->id,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс без пересечения времени',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
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
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotEvening->id,
            'anchor_date' => self::MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '14:00', 'time_end' => '15:00'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotEvening->id,
            'starts_at' => self::MONDAY,
        ]);
    }

    public function test_assign_flexible_422_when_slot_already_has_user_calendar_row_on_that_date(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot('12:00', '13:00');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий для дубликата',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 5,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 5,
            'lessons_remaining' => 5,
            'created_by' => $this->user->id,
        ]);

        // В календаре уже есть запись (например, пробное/legacy/другая привязка) на эту дату и слот.
        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::MONDAY,
            'ends_at' => self::MONDAY,
            'is_trial_lesson' => true,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'Это занятие уже привязано к ученику.');
    }

    public function test_assign_single_lesson_422_when_same_assignment_already_has_calendar_row(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $slot = $this->mondaySlot('14:00', '15:00');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Разовое',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 1000,
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
            'created_by' => $this->user->id,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::MONDAY,
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_lesson_package_id']);
    }

    public function test_assign_fixed_422_when_lessons_total_is_zero(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс без остатка',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            // Для фикс-цепочки количество "нужных" занятий считается от lessons_total (минус уже запланированные),
            // поэтому делаем total=0, чтобы контроллер отклонил привязку.
            'lessons_total' => 0,
            'lessons_remaining' => 0,
            'fee_amount_cents' => 1000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => self::MONDAY,
            'patterns' => [
                ['weekday' => 1, 'time_start' => '16:00', 'time_end' => '17:00'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'У абонемента не задан объём занятий.');
    }
}

