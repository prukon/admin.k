<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\LessonPackages\UserLessonPackageAutoProlongGuard;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для create/update с автопролонгацией.
 *
 * @see PackageAssignmentsNonAjaxSafetyNetFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class FixedLessonPackageAutoProlongNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    private User $student;

    private LessonPackage $fixedPackage;

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
            'lastname' => 'NonAjaxProlong',
            'name' => 'Ученик',
        ]);

        $this->fixedPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Non-ajax fixed prolong',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 25000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    public function test_store_non_ajax_with_auto_prolong_redirects_and_persists_flag(): void
    {
        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'fee_amount' => '250.00',
                'auto_prolong_enabled' => '1',
            ]);

        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'fee_amount_cents' => 25000,
            'auto_prolong_enabled' => 1,
            'is_paid' => 0,
        ]);
    }

    public function test_store_non_ajax_without_auto_prolong_checkbox_leaves_flag_off(): void
    {
        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'fee_amount' => '250.00',
            ]);

        $response->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'auto_prolong_enabled' => 0,
        ]);
    }

    public function test_store_non_ajax_blocked_when_student_already_has_auto_prolong_returns_field_error(): void
    {
        UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 25000,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => true,
        ]);

        $before = UserLessonPackage::query()->where('user_id', $this->student->id)->count();

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->fixedPackage->id,
                'fee_amount' => '250.00',
            ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['user_id']);

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString(
            'автопролонгация',
            mb_strtolower((string) $errors->first('user_id'))
        );
        $this->assertSame(
            UserLessonPackageAutoProlongGuard::BLOCK_REASON,
            $errors->first('user_id')
        );

        $this->assertSame($before, UserLessonPackage::query()->where('user_id', $this->student->id)->count());
    }

    public function test_update_non_ajax_enables_auto_prolong_and_redirects_not_empty_200(): void
    {
        $assignment = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'starts_at' => '2026-05-04',
            'ends_at' => '2026-06-03',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 25000,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => false,
        ]);

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->put(route('admin.lesson-packages.assignments.update', $assignment), [
                '_token' => csrf_token(),
                '_method' => 'PUT',
                'fee_amount' => '250.00',
                'ends_at' => '2026-06-03',
                'auto_prolong_enabled' => '1',
            ]);

        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $assignment->id,
            'auto_prolong_enabled' => 1,
        ]);
    }
}
