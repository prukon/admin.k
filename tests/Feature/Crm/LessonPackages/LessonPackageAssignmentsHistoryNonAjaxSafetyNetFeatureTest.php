<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для store/update назначений (история аудита).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackagesNonAjaxSafetyNetFeatureTest
 */
final class LessonPackageAssignmentsHistoryNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
     * @return array{package: LessonPackage, student: User, assignment: UserLessonPackage}
     */
    private function seedAssignment(float $fee = 100.0): array
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'NonAjax',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'History non-ajax package',
            'schedule_type' => 'no_schedule',
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
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => (int) round($fee * 100),
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        return compact('package', 'student', 'assignment');
    }

    public function test_store_non_ajax_redirects_creates_assignment_and_writes_created_audit(): void
    {
        $ctx = $this->seedAssignment();
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Создан',
            'name' => 'NonAjax',
            'is_enabled' => 1,
        ]);

        $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'lesson_package_id' => $ctx['package']->id,
                'fee_amount' => '333.00',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $student->id,
            'lesson_package_id' => $ctx['package']->id,
            'fee_amount_cents' => 33300,
        ]);

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::UserLessonPackageCreated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('333 ₽', (string) $log->description);
        $this->assertStringContainsString('Создан NonAjax', (string) $log->description);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'))
            ->assertSessionHasErrors(['user_id', 'lesson_package_id', 'fee_amount']);

        $this->assertSame(
            0,
            MyLog::query()
                ->where('event', AuditEvent::UserLessonPackageCreated->value)
                ->where('partner_id', $this->partner->id)
                ->count()
        );
    }

    public function test_update_non_ajax_redirects_updates_fee_and_writes_updated_audit(): void
    {
        $ctx = $this->seedAssignment(100.0);

        $this->from(route('admin.lesson-packages.assignments'))
            ->put(route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]), [
                '_token' => csrf_token(),
                'fee_amount' => '222.00',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'fee_amount_cents' => 22200,
        ]);

        $log = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::UserLessonPackageUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('100 ₽ → 222 ₽', (string) $log->description);
    }

    public function test_update_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $ctx = $this->seedAssignment(100.0);

        $this->from(route('admin.lesson-packages.assignments'))
            ->put(route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]), [
                '_token' => csrf_token(),
                'fee_amount' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['fee_amount']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ctx['assignment']->id,
            'fee_amount_cents' => 10000,
        ]);

        $this->assertSame(
            0,
            MyLog::query()
                ->where('event', AuditEvent::UserLessonPackageUpdated->value)
                ->where('partner_id', $this->partner->id)
                ->count()
        );
    }
}
