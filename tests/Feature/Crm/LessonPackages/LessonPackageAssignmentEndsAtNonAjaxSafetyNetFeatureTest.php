<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для PUT смены ends_at назначения.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackagesNonAjaxSafetyNetFeatureTest
 */
final class LessonPackageAssignmentEndsAtNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');
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

    /**
     * @return array{assignment: UserLessonPackage, utss: UserTeamScheduleSlot}
     */
    private function seedPeriodAssignmentWithCalendarRow(): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'EndsAt non-ajax package',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount' => '100.00',
            'is_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        $utss = UserTeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'user_lesson_package_id' => $assignment->id,
            'team_schedule_slot_id' => $slot->id,
            'starts_at' => '2026-04-06',
            'ends_at' => '2026-05-01',
            'created_by' => $this->user->id,
        ]);

        return ['assignment' => $assignment, 'utss' => $utss];
    }

    public function test_update_ends_at_non_ajax_redirects_and_updates_assignment_and_calendar_rows(): void
    {
        $ctx = $this->seedPeriodAssignmentWithCalendarRow();

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->put(
                route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
                [
                    '_token' => csrf_token(),
                    'fee_amount' => '100.00',
                    'ends_at' => '2026-06-20',
                ]
            );

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-06-20',
        ]);
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'id' => $ctx['utss']->id,
            'ends_at' => '2026-06-20',
        ]);
        $this->assertSame(
            1,
            UserTeamScheduleSlot::query()
                ->where('user_lesson_package_id', $ctx['assignment']->id)
                ->count()
        );
    }

    public function test_update_ends_at_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $ctx = $this->seedPeriodAssignmentWithCalendarRow();

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->put(
                route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
                [
                    '_token' => csrf_token(),
                    'fee_amount' => '100.00',
                    'ends_at' => '2026-04-02',
                ]
            );

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $response->assertSessionHasErrors(['ends_at']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'ends_at' => '2026-05-01',
        ]);
        $this->assertDatabaseHas('user_team_schedule_slots', [
            'id' => $ctx['utss']->id,
            'ends_at' => '2026-05-01',
        ]);
    }

    public function test_update_ends_at_non_ajax_missing_ends_at_when_period_set_redirects_with_errors(): void
    {
        $ctx = $this->seedPeriodAssignmentWithCalendarRow();

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->put(
                route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
                [
                    '_token' => csrf_token(),
                    'fee_amount' => '110.00',
                ]
            );

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ends_at']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'fee_amount' => '100.00',
            'ends_at' => '2026-05-01',
        ]);
    }
}
