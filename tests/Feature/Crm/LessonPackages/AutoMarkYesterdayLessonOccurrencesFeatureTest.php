<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Jobs\AutoMarkYesterdayLessonOccurrencesJob;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\TeamScheduleSlotException;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Tests\Feature\Crm\CrmTestCase;

final class AutoMarkYesterdayLessonOccurrencesFeatureTest extends CrmTestCase
{
    private const OCCURRENCE_DATE = '2026-07-02';

    protected function setUp(): void
    {
        parent::setUp();

        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
    }

    /**
     * @return array{student: User, ulp: UserLessonPackage, slot: TeamScheduleSlot}
     */
    private function createEligibleBinding(
        string $scheduleType = 'flexible',
        bool $autoAttendanceEnabled = true,
        int $lessonsRemaining = 3,
    ): array {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Автосписание '.$scheduleType,
            'schedule_type' => $scheduleType,
            'duration_days' => 90,
            'lessons_count' => 8,
            'price_cents' => 5000,
            'freeze_enabled' => false,
            'freeze_days' => 0,
            'auto_attendance_enabled' => $autoAttendanceEnabled,
            'is_active' => true,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-12-31',
            'lessons_total' => 8,
            'lessons_remaining' => $lessonsRemaining,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => (int) CarbonImmutable::parse(self::OCCURRENCE_DATE)->format('N'),
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::OCCURRENCE_DATE,
            'ends_at' => '2026-12-31',
            'is_trial_lesson' => false,
            'created_by' => $this->user->id,
        ]);

        return [
            'student' => $student,
            'ulp' => $ulp,
            'slot' => $slot,
        ];
    }

    private function attendedStatusId(): int
    {
        $status = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'attended')
            ->firstOrFail();

        return (int) $status->id;
    }

    private function runJobForDate(string $dateYmd): void
    {
        (new AutoMarkYesterdayLessonOccurrencesJob($dateYmd))->handle(app(\App\Services\LessonPackages\AutoMarkYesterdayLessonOccurrencesService::class));
    }

    public function test_auto_marks_attended_and_decrements_balance_for_flexible_package(): void
    {
        $binding = $this->createEligibleBinding('flexible', true, 3);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'partner_id' => $this->partner->id,
            'user_id' => $binding['student']->id,
            'team_schedule_slot_id' => $binding['slot']->id,
            'user_lesson_package_id' => $binding['ulp']->id,
            'lesson_occurrence_status_id' => $this->attendedStatusId(),
            'created_by' => null,
        ]);

        $binding['ulp']->refresh();
        $this->assertSame(2, (int) $binding['ulp']->lessons_remaining);

        $log = MyLog::query()
            ->where('event', AuditEvent::ScheduleOccurrenceStatusAutoSet->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Автосписание', (string) $log->description);
    }

    public function test_auto_marks_attended_for_fixed_package(): void
    {
        $binding = $this->createEligibleBinding('fixed', true, 5);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $binding['ulp']->refresh();
        $this->assertSame(4, (int) $binding['ulp']->lessons_remaining);
    }

    public function test_skips_when_any_status_already_exists(): void
    {
        $binding = $this->createEligibleBinding();

        $scheduled = LessonOccurrenceStatus::query()
            ->where('partner_id', $this->partner->id)
            ->where('code', 'scheduled')
            ->firstOrFail();

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $binding['student']->id,
            'team_schedule_slot_id' => $binding['slot']->id,
            'occurrence_date' => self::OCCURRENCE_DATE,
            'user_lesson_package_id' => $binding['ulp']->id,
            'lesson_occurrence_status_id' => $scheduled->id,
            'created_by' => $this->user->id,
        ]);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $binding['ulp']->refresh();
        $this->assertSame(3, (int) $binding['ulp']->lessons_remaining);
        $this->assertSame(1, UserLessonOccurrenceStatusEvent::query()->count());
    }

    public function test_skips_when_auto_attendance_disabled_on_template(): void
    {
        $binding = $this->createEligibleBinding('flexible', false, 3);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $this->assertDatabaseCount('user_lesson_occurrence_status_events', 0);
        $binding['ulp']->refresh();
        $this->assertSame(3, (int) $binding['ulp']->lessons_remaining);
    }

    public function test_skips_no_schedule_package(): void
    {
        $binding = $this->createEligibleBinding('no_schedule', true, 1);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $this->assertDatabaseCount('user_lesson_occurrence_status_events', 0);
        $binding['ulp']->refresh();
        $this->assertSame(1, (int) $binding['ulp']->lessons_remaining);
    }

    public function test_skips_trial_lesson_registration(): void
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
            'weekday' => (int) CarbonImmutable::parse(self::OCCURRENCE_DATE)->format('N'),
            'time_start' => '16:00',
            'time_end' => '17:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => null,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::OCCURRENCE_DATE,
            'ends_at' => self::OCCURRENCE_DATE,
            'is_trial_lesson' => true,
            'trial_lessons_remaining' => 1,
            'trial_lessons_total' => 1,
            'created_by' => $this->user->id,
        ]);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $this->assertDatabaseCount('user_lesson_occurrence_status_events', 0);
    }

    public function test_skips_when_school_occurrence_is_excluded_from_schedule(): void
    {
        $binding = $this->createEligibleBinding();

        TeamScheduleSlotException::query()->create([
            'partner_id' => $this->partner->id,
            'team_schedule_slot_id' => $binding['slot']->id,
            'occurrence_date' => self::OCCURRENCE_DATE,
        ]);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $this->assertDatabaseCount('user_lesson_occurrence_status_events', 0);
        $binding['ulp']->refresh();
        $this->assertSame(3, (int) $binding['ulp']->lessons_remaining);
    }

    public function test_skips_when_lessons_remaining_is_zero_without_stopping_job(): void
    {
        $binding = $this->createEligibleBinding('flexible', true, 0);

        $this->runJobForDate(self::OCCURRENCE_DATE);

        $this->assertDatabaseCount('user_lesson_occurrence_status_events', 0);
        $binding['ulp']->refresh();
        $this->assertSame(0, (int) $binding['ulp']->lessons_remaining);
    }

    public function test_second_run_is_idempotent(): void
    {
        $binding = $this->createEligibleBinding('flexible', true, 2);

        $this->runJobForDate(self::OCCURRENCE_DATE);
        $this->runJobForDate(self::OCCURRENCE_DATE);

        $this->assertSame(1, UserLessonOccurrenceStatusEvent::query()->count());
        $binding['ulp']->refresh();
        $this->assertSame(1, (int) $binding['ulp']->lessons_remaining);
    }

    public function test_service_is_eligible_returns_false_for_skipped_occurrence(): void
    {
        $binding = $this->createEligibleBinding();

        TeamScheduleSlotException::query()->create([
            'partner_id' => $this->partner->id,
            'team_schedule_slot_id' => $binding['slot']->id,
            'occurrence_date' => self::OCCURRENCE_DATE,
        ]);

        $utss = UserTeamScheduleSlot::query()->firstOrFail();
        $service = app(\App\Services\LessonPackages\AutoMarkYesterdayLessonOccurrencesService::class);

        $this->assertFalse($service->isEligible($utss, self::OCCURRENCE_DATE));
    }

    public function test_service_is_eligible_returns_true_when_no_status_and_flag_enabled(): void
    {
        $binding = $this->createEligibleBinding();
        $utss = UserTeamScheduleSlot::query()->firstOrFail();
        $service = app(\App\Services\LessonPackages\AutoMarkYesterdayLessonOccurrencesService::class);

        $this->assertTrue($service->isEligible($utss, self::OCCURRENCE_DATE));
        unset($binding);
    }
}
