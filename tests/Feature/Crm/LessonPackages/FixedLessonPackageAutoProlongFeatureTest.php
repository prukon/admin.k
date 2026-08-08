<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\UserLessonOccurrenceStatusService;
use App\Services\LessonPackages\UserLessonPackageAutoProlongGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Workflow автопролонгации: списание → новый цикл / fail-soft, hard ban в календаре.
 *
 * @see FixedLessonPackageAutoProlongAjaxContractFeatureTest
 * @see docs/documentation/lesson-packages.html#auto-prolong
 */
final class FixedLessonPackageAutoProlongFeatureTest extends CrmTestCase
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
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function studentUser(string $suffix = ''): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'Пролонг'.$suffix,
            'lastname' => 'Ученик',
            'is_enabled' => 1,
        ]);
    }

    private function attendedStatus(): LessonOccurrenceStatus
    {
        return LessonOccurrenceStatus::query()->firstOrCreate(
            [
                'partner_id' => $this->partner->id,
                'code' => LessonOccurrenceStatus::CODE_ATTENDED,
            ],
            [
                'title' => 'Посетил',
                'consumes_lesson' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * @return array{student: User, package: LessonPackage, slot: TeamScheduleSlot, ulp: UserLessonPackage, team: Team}
     */
    private function seededLaidOutFixed(
        int $lessons = 2,
        bool $autoProlong = true,
        int $packageLessonsCount = 0,
        int $durationDays = 60,
    ): array {
        $student = $this->studentUser();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
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

        $packageLessons = $packageLessonsCount > 0 ? $packageLessonsCount : $lessons;

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс автопролонг',
            'schedule_type' => 'fixed',
            'duration_days' => $durationDays,
            'lessons_count' => $packageLessons,
            'price_cents' => 500000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'lesson_package_id' => $package->id,
            'starts_at' => self::WEEK_MONDAY,
            'ends_at' => CarbonImmutable::parse(self::WEEK_MONDAY)->addDays($durationDays)->toDateString(),
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount_cents' => 77700,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => $autoProlong,
        ]);

        for ($i = 0; $i < $lessons; $i++) {
            $date = CarbonImmutable::parse(self::WEEK_MONDAY)->addWeeks($i)->toDateString();
            UserTeamScheduleSlot::query()->create([
                'partner_id' => $this->partner->id,
                'user_id' => $student->id,
                'user_lesson_package_id' => $ulp->id,
                'team_schedule_slot_id' => $slot->id,
                'starts_at' => $date,
                'ends_at' => $ulp->ends_at->format('Y-m-d'),
                'created_by' => $this->user->id,
            ]);
        }

        return compact('student', 'package', 'slot', 'ulp', 'team');
    }

    public function test_auto_prolong_creates_next_fixed_cycle_when_remaining_hits_zero(): void
    {
        $ctx = $this->seededLaidOutFixed(2, true);
        $status = $this->attendedStatus();
        $service = app(UserLessonOccurrenceStatusService::class);

        $dates = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ctx['ulp']->id)
            ->orderBy('starts_at')
            ->pluck('starts_at')
            ->map(fn ($d) => CarbonImmutable::parse((string) $d)->toDateString())
            ->all();

        $service->apply(
            $this->partner->id,
            (int) $ctx['student']->id,
            (int) $ctx['slot']->id,
            $dates[0],
            (int) $ctx['ulp']->id,
            $status,
            $this->user->id,
        );

        $ctx['ulp']->refresh();
        $this->assertSame(1, (int) $ctx['ulp']->lessons_remaining);
        $this->assertTrue((bool) $ctx['ulp']->auto_prolong_enabled);

        $service->apply(
            $this->partner->id,
            (int) $ctx['student']->id,
            (int) $ctx['slot']->id,
            $dates[1],
            (int) $ctx['ulp']->id,
            $status,
            $this->user->id,
        );

        $ctx['ulp']->refresh();
        $this->assertSame(0, (int) $ctx['ulp']->lessons_remaining);
        $this->assertFalse((bool) $ctx['ulp']->auto_prolong_enabled);
        $this->assertNotNull($ctx['ulp']->auto_prolonged_to_id);

        $new = UserLessonPackage::query()->findOrFail((int) $ctx['ulp']->auto_prolonged_to_id);
        $this->assertTrue((bool) $new->auto_prolong_enabled);
        $this->assertSame((int) $ctx['ulp']->id, (int) $new->auto_prolonged_from_id);
        $this->assertSame(77700, (int) $new->fee_amount_cents);
        $this->assertFalse((bool) $new->is_paid);
        $this->assertSame(2, (int) $new->lessons_total);
        $this->assertSame(2, (int) $new->lessons_remaining);
        $this->assertSame(2, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $new->id)->count());

        $firstNewDate = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $new->id)
            ->min('starts_at');
        $this->assertSame(
            CarbonImmutable::parse($dates[1])->addWeek()->toDateString(),
            CarbonImmutable::parse((string) $firstNewDate)->toDateString()
        );

        $this->assertTrue(
            MyLog::query()->where('event', AuditEvent::UserLessonPackageAutoProlonged->value)->exists()
        );
        $this->assertTrue($ctx['ulp']->showsAutoProlongBadge());
        $this->assertTrue($new->showsAutoProlongBadge());
    }

    public function test_auto_prolong_failure_disables_flag_writes_log_and_does_not_create_ulp(): void
    {
        // Текущий цикл на 1 занятие; шаблон требует 8 занятий в окне 7 дней → раскладка нового цикла падает.
        $ctx = $this->seededLaidOutFixed(
            lessons: 1,
            autoProlong: true,
            packageLessonsCount: 8,
            durationDays: 7,
        );
        $status = $this->attendedStatus();
        $beforeCount = UserLessonPackage::query()->where('user_id', $ctx['student']->id)->count();

        $date = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ctx['ulp']->id)
            ->value('starts_at');

        app(UserLessonOccurrenceStatusService::class)->apply(
            $this->partner->id,
            (int) $ctx['student']->id,
            (int) $ctx['slot']->id,
            CarbonImmutable::parse((string) $date)->toDateString(),
            (int) $ctx['ulp']->id,
            $status,
            $this->user->id,
        );

        $ctx['ulp']->refresh();
        $this->assertSame(0, (int) $ctx['ulp']->lessons_remaining);
        $this->assertFalse((bool) $ctx['ulp']->auto_prolong_enabled);
        $this->assertNull($ctx['ulp']->auto_prolonged_to_id);
        $this->assertSame(
            $beforeCount,
            UserLessonPackage::query()->where('user_id', $ctx['student']->id)->count()
        );
        $this->assertTrue(
            MyLog::query()->where('event', AuditEvent::UserLessonPackageAutoProlongFailed->value)->exists()
        );
    }

    public function test_disabling_auto_prolong_does_not_remove_already_created_next_cycle(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');

        $ctx = $this->seededLaidOutFixed(1, true);
        $status = $this->attendedStatus();
        $date = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ctx['ulp']->id)
            ->value('starts_at');

        app(UserLessonOccurrenceStatusService::class)->apply(
            $this->partner->id,
            (int) $ctx['student']->id,
            (int) $ctx['slot']->id,
            CarbonImmutable::parse((string) $date)->toDateString(),
            (int) $ctx['ulp']->id,
            $status,
            $this->user->id,
        );

        $ctx['ulp']->refresh();
        $newId = (int) $ctx['ulp']->auto_prolonged_to_id;
        $this->assertGreaterThan(0, $newId);

        $new = UserLessonPackage::query()->findOrFail($newId);
        $this->assertTrue((bool) $new->auto_prolong_enabled);

        $this->putJson(route('admin.lesson-packages.assignments.update', $new), [
            'fee_amount' => '777.00',
            'ends_at' => $new->ends_at->format('Y-m-d'),
            'auto_prolong_enabled' => false,
        ])->assertOk();

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $newId,
            'auto_prolong_enabled' => 0,
        ]);
        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['ulp']->id,
            'auto_prolonged_to_id' => $newId,
        ]);
        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()->where('user_lesson_package_id', $newId)->count()
        );
    }

    public function test_slot_bind_actions_block_flexible_trial_and_single_when_auto_prolong_enabled(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seededLaidOutFixed(1, true);

        $response = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $ctx['student']->id,
            'team_schedule_slot_id' => $ctx['slot']->id,
            'occurrence_date' => '2026-05-11',
        ]));

        $response->assertOk();
        $this->assertNotSame(500, $response->getStatusCode());
        $reason = UserLessonPackageAutoProlongGuard::BLOCK_REASON;

        $response->assertJsonPath('flexible.allowed', false)
            ->assertJsonPath('flexible.reason', $reason)
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath('trial.reason', $reason)
            ->assertJsonPath('single_lesson.allowed', false)
            ->assertJsonPath('single_lesson.reason', $reason)
            ->assertJsonPath('fixed.allowed', false)
            ->assertJsonPath('fixed.reason', $reason);
    }

    public function test_slot_bind_actions_allow_first_fixed_bind_of_unbound_auto_prolong_assignment(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = $this->studentUser('Unbound');
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
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Unbound fixed prolong',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 2,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 2,
            'lessons_remaining' => 2,
            'fee_amount_cents' => 10000,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => true,
        ]);

        $response = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]));

        $response->assertOk()
            ->assertJsonPath('fixed.allowed', true)
            ->assertJsonPath('flexible.allowed', false)
            ->assertJsonPath('trial.allowed', false)
            ->assertJsonPath('single_lesson.allowed', false);

        $ids = collect($response->json('fixed.existing_assignments'))->pluck('id')->all();
        $this->assertContains((int) $ulp->id, $ids);
    }

    public function test_assign_flexible_returns_422_with_user_id_error_when_auto_prolong_enabled(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seededLaidOutFixed(1, true);

        $flexPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий блок',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
        $flexUlp = UserLessonPackage::query()->create([
            'user_id' => $ctx['student']->id,
            'lesson_package_id' => $flexPackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 10000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson(route('admin.lesson-packages.school-schedule.assign-flexible'), [
            'user_lesson_package_id' => $flexUlp->id,
            'team_schedule_slot_id' => $ctx['slot']->id,
            'occurrence_date' => '2026-05-11',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
        $this->assertSame(
            UserLessonPackageAutoProlongGuard::BLOCK_REASON,
            $response->json('errors.user_id.0')
        );
        $this->assertDatabaseMissing('user_team_schedule_slots', [
            'user_lesson_package_id' => $flexUlp->id,
        ]);
    }

    public function test_trial_registration_returns_422_when_student_has_auto_prolong(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seededLaidOutFixed(1, true);

        $response = $this->postJson(route('admin.lesson-packages.school-schedule.trial-registration.store'), [
            'user_id' => $ctx['student']->id,
            'team_schedule_slot_id' => $ctx['slot']->id,
            'occurrence_date' => '2026-05-11',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
        $this->assertSame(
            UserLessonPackageAutoProlongGuard::BLOCK_REASON,
            $response->json('errors.user_id.0')
        );
    }

    public function test_remaining_zero_without_auto_prolong_does_not_create_next_cycle(): void
    {
        $ctx = $this->seededLaidOutFixed(1, false);
        $status = $this->attendedStatus();
        $before = UserLessonPackage::query()->where('user_id', $ctx['student']->id)->count();
        $date = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', $ctx['ulp']->id)
            ->value('starts_at');

        app(UserLessonOccurrenceStatusService::class)->apply(
            $this->partner->id,
            (int) $ctx['student']->id,
            (int) $ctx['slot']->id,
            CarbonImmutable::parse((string) $date)->toDateString(),
            (int) $ctx['ulp']->id,
            $status,
            $this->user->id,
        );

        $ctx['ulp']->refresh();
        $this->assertSame(0, (int) $ctx['ulp']->lessons_remaining);
        $this->assertNull($ctx['ulp']->auto_prolonged_to_id);
        $this->assertSame($before, UserLessonPackage::query()->where('user_id', $ctx['student']->id)->count());
        $this->assertFalse(
            MyLog::query()->where('event', AuditEvent::UserLessonPackageAutoProlonged->value)->exists()
        );
    }
}
