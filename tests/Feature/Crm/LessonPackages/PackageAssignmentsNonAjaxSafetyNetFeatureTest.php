<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для назначений абонементов (право setPrices.packageAssignments.view).
 * Classic POST/PUT без X-Requested-With → 302 на вкладку, запись в БД; не пустой 200.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackageAssignmentsHistoryNonAjaxSafetyNetFeatureTest
 */
final class PackageAssignmentsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    private LessonPackage $package;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach (['lessonPackages.view', 'setPrices.packageAssignments.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'NonAjax',
            'name' => 'Ученик',
        ]);

        $this->package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Non-ajax assignment package',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    private function createAssignment(float $fee = 100.0): UserLessonPackage
    {
        return UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => (int) round($fee * 100),
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_non_ajax_redirects_and_creates_assignment_not_empty_200(): void
    {
        $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->package->id,
                'fee_amount' => '444.00',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'fee_amount_cents' => 44400,
        ]);
    }

    public function test_store_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $before = UserLessonPackage::query()->count();

        $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'))
            ->assertSessionHasErrors(['user_id', 'lesson_package_id', 'fee_amount']);

        $this->assertSame($before, UserLessonPackage::query()->count());
    }

    public function test_store_non_ajax_forbidden_without_package_assignments_permission(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->from(route('admin.lesson-packages.index'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->package->id,
                'fee_amount' => '100.00',
            ])
            ->assertForbidden();
    }

    public function test_update_non_ajax_redirects_and_updates_fee_not_empty_200(): void
    {
        $assignment = $this->createAssignment(100.0);

        $this->from(route('admin.lesson-packages.assignments'))
            ->put(route('admin.lesson-packages.assignments.update', ['assignment' => $assignment->id]), [
                '_token' => csrf_token(),
                'fee_amount' => '275.50',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $assignment->id,
            'fee_amount_cents' => 27550,
        ]);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $assignment = $this->createAssignment(100.0);

        $this->from(route('admin.lesson-packages.assignments'))
            ->put(route('admin.lesson-packages.assignments.update', ['assignment' => $assignment->id]), [
                '_token' => csrf_token(),
                'fee_amount' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['fee_amount']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $assignment->id,
            'fee_amount_cents' => 10000,
        ]);
    }

    public function test_update_non_ajax_forbidden_without_package_assignments_permission(): void
    {
        $assignment = $this->createAssignment(100.0);

        $actor = $this->createUserWithoutPermission('setPrices.packageAssignments.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($actor);

        $this->from(route('admin.lesson-packages.index'))
            ->put(route('admin.lesson-packages.assignments.update', ['assignment' => $assignment->id]), [
                '_token' => csrf_token(),
                'fee_amount' => '200.00',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $assignment->id,
            'fee_amount_cents' => 10000,
        ]);
    }
}
