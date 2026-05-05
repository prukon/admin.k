<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
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
 * Лимит записей в календаре по lessons_total, списание/возврат по смене статуса, поля баланса в JSON недели.
 */
final class LessonPackageSchoolScheduleCalendarBalancesFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';
    private const NEXT_MONDAY = '2026-05-11';
    private const THIRD_MONDAY = '2026-05-18';

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
            'is_enabled' => 1,
        ]);
    }

    public function test_assign_flexible_third_occurrence_returns_422_when_lessons_total_is_two(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий лимит слотов',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 2,
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
            'lessons_total' => 2,
            'lessons_remaining' => 2,
            'created_by' => $this->user->id,
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

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::NEXT_MONDAY,
        ])->assertOk();

        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::THIRD_MONDAY,
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.user_lesson_package_id.0',
                'Достигнут лимит занятий в календаре для этого абонемента.'
            );

        $ulp->refresh();
        $this->assertSame(2, (int) $ulp->lessons_remaining);
    }

    public function test_occurrence_status_attended_then_cancelled_restores_lessons_remaining(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $student = $this->studentUser();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий статус отмена',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 10,
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
            'lessons_total' => 10,
            'lessons_remaining' => 5,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
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

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
        ]);

        $statusAttended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        $statusCancelled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'cancelled')
            ->firstOrFail();

        $this->actingAs($this->user);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $statusAttended->id,
        ])
            ->assertOk()
            ->assertJsonPath('user_lesson_package.lessons_remaining', 4);

        $ulp->refresh();
        $this->assertSame(4, (int) $ulp->lessons_remaining);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $statusCancelled->id,
        ])
            ->assertOk()
            ->assertJsonPath('user_lesson_package.lessons_remaining', 5);

        $ulp->refresh();
        $this->assertSame(5, (int) $ulp->lessons_remaining);
    }

    public function test_occurrence_status_second_attended_does_not_double_consume(): void
    {
        $this->grantPermission('lessonPackages.view');
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);

        $student = $this->studentUser();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий двойной статус',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 10,
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
            'time_start' => '11:00',
            'time_end' => '12:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => '2026-12-31',
            'created_by' => $this->user->id,
        ]);

        $statusAttended = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        $this->actingAs($this->user);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $statusAttended->id,
        ])
            ->assertOk()
            ->assertJsonPath('user_lesson_package.lessons_remaining', 2);

        $this->postJson(route('admin.lesson-packages.school-schedule.occurrence-status.store'), [
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'lesson_occurrence_status_id' => $statusAttended->id,
        ])
            ->assertOk()
            ->assertJsonPath('user_lesson_package.lessons_remaining', 2);

        $ulp->refresh();
        $this->assertSame(2, (int) $ulp->lessons_remaining);
    }

    public function test_week_json_package_registration_includes_balance_after_flexible_bind(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий JSON баланс',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 10,
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
            'lessons_total' => 10,
            'lessons_remaining' => 7,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '13:00',
            'time_end' => '14:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ])->assertOk();

        $week = $this->getJson(route('admin.lesson-packages.school-schedule.week', [
            'week' => self::WEEK_MONDAY,
        ]))
            ->assertOk()
            ->json();

        $hit = collect($week['occurrences'] ?? [])->first(function (array $o) use ($slot): bool {
            return ($o['date'] ?? '') === self::WEEK_MONDAY
                && (int) ($o['id'] ?? 0) === $slot->id;
        });
        $this->assertNotNull($hit);

        $packageReg = collect($hit['registrations'] ?? [])->first(
            fn (array $r): bool => ($r['registration_kind'] ?? '') === 'package'
                && (int) ($r['user_id'] ?? 0) === $student->id
        );
        $this->assertNotNull($packageReg);
        $this->assertSame(7, (int) ($packageReg['lessons_remaining'] ?? 0));
        $this->assertSame(10, (int) ($packageReg['lessons_total'] ?? 0));
    }
}
