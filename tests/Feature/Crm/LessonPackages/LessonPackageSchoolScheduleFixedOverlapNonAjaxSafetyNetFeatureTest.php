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
 * Non-AJAX safety-net для assign-fixed при конфликтах расписания (проверка по starts_at).
 *
 * UI шлёт fetch + X-Requested-With; без JS форма уходит обычным POST → 302 + session errors,
 * не пустой 200 и не 500. Запись в БД при конфликте не создаётся.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackageSchoolScheduleFixedRegistrationFeatureTest
 */
final class LessonPackageSchoolScheduleFixedOverlapNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    private const MONDAY = '2026-05-04';

    private const NEXT_MONDAY = '2026-05-11';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->assertSame(1, (int) CarbonImmutable::parse(self::MONDAY)->format('N'));
        $this->assertSame(1, (int) CarbonImmutable::parse(self::NEXT_MONDAY)->format('N'));
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

    private function mondaySlot(string $timeStart, string $timeEnd): TeamScheduleSlot
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

    /**
     * @return array{student: User, slot: TeamScheduleSlot, ulp: UserLessonPackage}
     */
    private function newFixedAssignmentContext(TeamScheduleSlot $slot, int $lessons = 1): array
    {
        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Non-Ajax фикс',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => $lessons,
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
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return ['student' => $student, 'slot' => $slot, 'ulp' => $ulp];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignPayload(User $student, UserLessonPackage $ulp, TeamScheduleSlot $slot, string $anchor): array
    {
        return [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'anchor_date' => $anchor,
            'patterns' => [
                [
                    'weekday' => 1,
                    'time_start' => substr((string) $slot->time_start, 0, 5),
                    'time_end' => substr((string) $slot->time_end, 0, 5),
                ],
            ],
        ];
    }

    public function test_guest_non_ajax_assign_fixed_is_denied_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');
        $slot = $this->mondaySlot('14:15', '16:00');
        ['student' => $student, 'ulp' => $ulp] = $this->newFixedAssignmentContext($slot);

        auth()->logout();

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(
                route('admin.lesson-packages.school-schedule.assign-fixed'),
                $this->assignPayload($student, $ulp, $slot, self::MONDAY)
            );

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_without_permission_non_ajax_assign_fixed_returns_403(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $slot = $this->mondaySlot('14:15', '16:00');
        $student = $this->studentUser();
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Фикс без прав',
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
            'fee_amount' => '10.00',
            'is_paid' => false,
            'created_by' => $denied->id,
        ]);

        $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(
                route('admin.lesson-packages.school-schedule.assign-fixed'),
                $this->assignPayload($student, $ulp, $slot, self::MONDAY)
            )
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_non_ajax_overlap_redirects_with_patterns_session_errors_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');

        $slot = $this->mondaySlot('14:15', '16:00');
        ['student' => $student, 'ulp' => $ulp] = $this->newFixedAssignmentContext($slot);

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

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(
                route('admin.lesson-packages.school-schedule.assign-fixed'),
                $this->assignPayload($student, $ulp, $slot, self::MONDAY)
            );

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.school-schedule'));
        $response->assertSessionHasErrors(['patterns']);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $patternsError = (string) session('errors')->first('patterns');
        $this->assertStringContainsString('Конфликт расписания на '.self::MONDAY, $patternsError);
        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_non_ajax_ends_at_tail_allows_and_creates_row_with_redirect(): void
    {
        $this->grantPermission('lessonPackages.view');

        $slot = $this->mondaySlot('14:15', '16:00');
        ['student' => $student, 'ulp' => $ulp] = $this->newFixedAssignmentContext($slot);

        $oldPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Старый non-ajax',
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
            'fee_amount' => '10.00',
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

        $response = $this->from(route('admin.lesson-packages.school-schedule'))
            ->post(
                route('admin.lesson-packages.school-schedule.assign-fixed'),
                $this->assignPayload($student, $ulp, $slot, self::NEXT_MONDAY)
            );

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.school-schedule'));
        $response->assertSessionHas('status', 'Абонемент назначен, занятия привязаны к расписанию школы.');
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('user_team_schedule_slots', [
            'user_id' => $student->id,
            'user_lesson_package_id' => $ulp->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => self::NEXT_MONDAY,
        ]);
    }
}
