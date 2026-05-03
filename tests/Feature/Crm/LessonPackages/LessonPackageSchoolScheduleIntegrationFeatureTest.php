<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\LessonPackageTimeSlot;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Интеграция вкладки «Расписание школы»: доступ, JSON недели, слоты, назначения и привязки из календаря.
 */
final class LessonPackageSchoolScheduleIntegrationFeatureTest extends CrmTestCase
{
    /** Понедельник (проверка ниже). */
    private const WEEK_ANCHOR_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(
            1,
            (int) CarbonImmutable::parse(self::WEEK_ANCHOR_MONDAY)->format('N'),
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
            'name' => 'Иван',
            'lastname' => 'Тестов',
            'is_enabled' => 1,
        ]);
    }

    public function test_school_schedule_week_fixed_flexible_and_assign_posts_forbidden_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $student = $this->studentUser();

        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_ANCHOR_MONDAY]))
            ->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))
            ->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => $student->id]))
            ->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search'))
            ->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', ['user_id' => $student->id]))
            ->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-users-search'))
            ->assertForbidden();
        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))
            ->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => 999999,
            'team_schedule_slot_id' => 999999,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $student->id,
            'user_lesson_package_id' => 999999,
            'team_schedule_slot_id' => 999999,
            'anchor_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => 999999,
            'team_schedule_slot_id' => 999999,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertForbidden();

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ]))->assertForbidden();

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $student->id,
            'team_schedule_slot_id' => 1,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertForbidden();
    }

    public function test_school_schedule_page_and_json_endpoints_ok_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();

        $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->assertSee('Расписание школы', false);

        $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_ANCHOR_MONDAY]))
            ->assertOk()
            ->assertJsonStructure([
                'week_start',
                'occurrences',
            ]);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-packages'))
            ->assertOk()
            ->assertJsonStructure(['packages']);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => 0]))
            ->assertOk()
            ->assertExactJson(['assignments' => []]);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonStructure(['assignments']);

        $this->getJson(route('admin.lesson-packages.school-schedule.fixed-assignments', ['user_id' => $student->id]))
            ->assertOk()
            ->assertExactJson(['assignments' => []]);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-users-search'))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', ['user_id' => 0]))
            ->assertOk()
            ->assertExactJson(['assignments' => []]);

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-users-search'))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.school-schedule.assignment-availability'))
            ->assertOk()
            ->assertJsonStructure(['flexible', 'fixed', 'single_lesson'])
            ->assertJson(['flexible' => false, 'fixed' => false, 'single_lesson' => false]);
    }

    public function test_store_team_slot_without_location_id_saves_null_location(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->postJson(route('admin.team-schedule-slots.store'), [
            'team_id' => $team->id,
            'weekday' => 2,
            'time_start' => '09:00',
            'time_end' => '10:00',
            'date_start' => '2026-01-01',
            'date_end' => '',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 2,
            'time_start' => '09:00',
            'time_end' => '10:00',
        ]);
    }

    public function test_assign_flexible_creates_binding_decrements_remaining_and_week_json_lists_registration(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий интеграция',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 10,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 10,
            'lessons_remaining' => 3,
            'created_by' => $this->user->id,
        ]);

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

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonPath('assignments.0.id', $ulp->id);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk()
            ->assertJsonPath('message', 'Занятие привязано к абонементу.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_ANCHOR_MONDAY,
        ]);

        $ulp->refresh();
        $this->assertSame(2, $ulp->lessons_remaining);

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_ANCHOR_MONDAY]))
            ->assertOk()
            ->json();

        $hit = collect($week['occurrences'] ?? [])->first(function (array $o) use ($slot): bool {
            return ($o['date'] ?? '') === self::WEEK_ANCHOR_MONDAY
                && (int) ($o['id'] ?? 0) === $slot->id;
        });
        $this->assertNotNull($hit, 'Ожидалась запись слота на понедельник недели.');
        $this->assertNotEmpty($hit['registrations'] ?? []);
        $lines = array_column($hit['registrations'], 'line');
        $this->assertTrue(
            collect($lines)->contains(fn (string $line): bool => str_contains($line, 'гибкий абонемент')),
            'Подпись регистрации должна относиться к гибкому абонементу.'
        );
    }

    public function test_assign_single_lesson_no_schedule_creates_binding_and_registration_label(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Разовое интеграция',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 150000,
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

        $this->getJson(route('admin.lesson-packages.school-schedule.single-lesson-assignments', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonPath('assignments.0.id', $ulp->id);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk()
            ->assertJsonPath('message', 'Разовое занятие записано в расписание.');

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'user_lesson_package_id' => $ulp->id,
            'starts_at' => self::WEEK_ANCHOR_MONDAY,
        ]);

        $ulp->refresh();
        $this->assertSame(0, $ulp->lessons_remaining);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-single-lesson'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertStatus(422)
            ->assertJsonPath('errors.user_lesson_package_id.0', 'Для этого назначения слот в календаре уже выбран. Оформите новое разовое занятие отдельным абонементом.');

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_ANCHOR_MONDAY]))
            ->assertOk()
            ->json();

        $hit = collect($week['occurrences'] ?? [])->first(function (array $o) use ($slot): bool {
            return ($o['date'] ?? '') === self::WEEK_ANCHOR_MONDAY
                && (int) ($o['id'] ?? 0) === $slot->id;
        });
        $this->assertNotNull($hit);
        $this->assertNotEmpty($hit['registrations'] ?? []);
        $lines = array_column($hit['registrations'], 'line');
        $this->assertTrue(
            collect($lines)->contains(fn (string $line): bool => str_contains($line, 'разовое занятие')),
            'Подпись регистрации должна относиться к разовому занятию.'
        );
    }

    public function test_assign_flexible_422_when_occurrence_weekday_does_not_match_slot(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий',
            'schedule_type' => 'flexible',
            'duration_days' => 60,
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

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slotMonday = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        // 2026-05-05 — вторник (ISO 2), слот на понедельник (1).
        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slotMonday->id,
            'occurrence_date' => '2026-05-05',
        ])->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'Дата не соответствует дню недели выбранного слота.');
    }

    public function test_assign_flexible_rejects_skipped_occurrence_date(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('scheduleSlots.manage');

        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий skip',
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

        $this->postJson(route('admin.team-schedule-slots.skip-occurrence', $slot), [
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertStatus(422)
            ->assertJsonPath('errors.occurrence_date.0', 'На эту дату занятие исключено из расписания школы.');
    }

    public function test_assign_fixed_creates_user_lesson_package_and_slot_binding(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс интеграция',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 1,
            'price_cents' => 8000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        LessonPackageTimeSlot::query()->create([
            'lesson_package_id' => $package->id,
            'weekday' => 1,
            'time_start' => '15:00:00',
            'time_end' => '16:00:00',
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '80.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
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
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk()
            ->assertJsonPath('message', 'Абонемент назначен, занятия привязаны к расписанию школы.');

        $ulpId = (int) $ulp->id;
        $ulp->refresh();
        $this->assertSame(self::WEEK_ANCHOR_MONDAY, $ulp->starts_at->format('Y-m-d'));
        $this->assertSame('2026-06-03', $ulp->ends_at->format('Y-m-d'));

        $this->assertSame(1, UserTeamScheduleSlot::query()
            ->where('user_id', $student->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->where('starts_at', self::WEEK_ANCHOR_MONDAY)
            ->where('user_lesson_package_id', $ulpId)
            ->count());

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_ANCHOR_MONDAY]))
            ->assertOk()
            ->json();

        $hit = collect($week['occurrences'] ?? [])->first(function (array $o) use ($slot): bool {
            return ($o['date'] ?? '') === self::WEEK_ANCHOR_MONDAY
                && (int) ($o['id'] ?? 0) === $slot->id;
        });
        $this->assertNotNull($hit);
        $this->assertNotEmpty($hit['registrations'] ?? []);
    }

    public function test_assign_fixed_validation_rejects_user_from_other_partner(): void
    {
        $this->grantPermission('lessonPackages.view');

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 14,
            'lessons_count' => 1,
            'price_cents' => 1000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        LessonPackageTimeSlot::query()->create([
            'lesson_package_id' => $package->id,
            'weekday' => 1,
            'time_start' => '16:00:00',
            'time_end' => '17:00:00',
        ]);

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

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-fixed'), [
            'user_id' => $this->foreignUser->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_store_lesson_package_and_assignment_then_calendar_bind_flow(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => 'Гибкий из формы',
            'schedule_type' => 'flexible',
            'duration_days' => 90,
            'lessons_count' => 6,
            'price' => '2000.00',
            'freeze_enabled' => 0,
        ])->assertOk()->assertJson(['success' => true]);

        $packageId = (int) LessonPackage::query()->where('name', 'Гибкий из формы')->value('id');
        $this->assertGreaterThan(0, $packageId);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            'user_id' => $student->id,
            'lesson_package_id' => $packageId,
            'fee_amount' => '2000.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $ulpId = (int) UserLessonPackage::query()
            ->where('user_id', $student->id)
            ->where('lesson_package_id', $packageId)
            ->value('id');
        $this->assertGreaterThan(0, $ulpId);

        $beforeAssign = UserLessonPackage::query()->whereKey($ulpId)->first();
        $this->assertNull($beforeAssign->starts_at);
        $this->assertNull($beforeAssign->ends_at);

        $this->getJson(route('admin.lesson-packages.school-schedule.flexible-assignments', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonPath('assignments.0.id', $ulpId);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
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

        $beforeRemaining = (int) UserLessonPackage::query()->whereKey($ulpId)->value('lessons_remaining');

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulpId,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk();

        $this->assertSame($beforeRemaining - 1, (int) UserLessonPackage::query()->whereKey($ulpId)->value('lessons_remaining'));

        $ulpAfter = UserLessonPackage::query()->whereKey($ulpId)->first();
        $this->assertNotNull($ulpAfter->starts_at);
        $this->assertNotNull($ulpAfter->ends_at);
        $this->assertSame(self::WEEK_ANCHOR_MONDAY, $ulpAfter->starts_at->format('Y-m-d'));
        $this->assertSame('2026-08-02', $ulpAfter->ends_at->format('Y-m-d'));
    }

    public function test_trial_registration_two_students_same_slot_eligibility_block_with_abonement_and_delete(): void
    {
        $this->grantPermission('lessonPackages.view');

        $studentA = $this->studentUser();
        $studentB = $this->studentUser();

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '12:00',
            'time_end' => '13:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $studentA->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk();

        $rowA = UserTeamScheduleSlot::query()
            ->where('user_id', $studentA->id)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', self::WEEK_ANCHOR_MONDAY)
            ->first();
        $this->assertNotNull($rowA);
        $this->assertTrue((bool) $rowA->is_trial_lesson);
        $this->assertNull($rowA->user_lesson_package_id);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $studentB->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $studentA->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ]))->assertOk()
            ->assertJsonPath('allowed', false);

        $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $studentA->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertStatus(422);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий для пробного теста',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 10,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $studentC = $this->studentUser();
        $ulpC = UserLessonPackage::query()->create([
            'user_id' => $studentC->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 10,
            'lessons_remaining' => 3,
            'created_by' => $this->user->id,
        ]);
        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulpC->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ])->assertOk();

        $this->getJson(route('admin.lesson-packages.school-schedule.trial-registration-eligibility', [
            'user_id' => $studentC->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_ANCHOR_MONDAY,
        ]))->assertOk()
            ->assertJsonPath('allowed', false);

        $this->deleteJson(route('admin.lesson-packages.school-schedule.trial-registration.destroy', ['userTeamScheduleSlot' => $rowA->id]))
            ->assertOk();

        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $rowA->id]);

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', ['week' => self::WEEK_ANCHOR_MONDAY]))
            ->assertOk()
            ->json();
        $hit = collect($week['occurrences'] ?? [])->first(function (array $o) use ($slot): bool {
            return ($o['date'] ?? '') === self::WEEK_ANCHOR_MONDAY
                && (int) ($o['id'] ?? 0) === $slot->id;
        });
        $this->assertNotNull($hit);
        $regs = $hit['registrations'] ?? [];
        $this->assertGreaterThanOrEqual(2, count($regs));
        $this->assertTrue(
            collect($regs)->contains(fn (array $r): bool => ($r['registration_kind'] ?? '') === 'trial'),
            'Ожидалась регистрация с видом trial в JSON недели.'
        );
    }
}
