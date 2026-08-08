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
 * AJAX-контракт флага автопролонгации в модалке «Изменение абонемента».
 *
 * @see PackageAssignmentsAjaxContractFeatureTest
 * @see LessonPackageAssignmentEndsAtAjaxContractFeatureTest
 */
final class FixedLessonPackageAutoProlongAjaxContractFeatureTest extends CrmTestCase
{
    private User $student;

    private LessonPackage $fixedPackage;

    private LessonPackage $flexiblePackage;

    private UserLessonPackage $fixedAssignment;

    private UserLessonPackage $flexibleAssignment;

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
            'lastname' => 'AjaxProlong',
            'name' => 'Ученик',
        ]);

        $this->fixedPackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax fixed',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 20000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->flexiblePackage = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax flexible',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 15000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->fixedAssignment = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'starts_at' => '2026-05-04',
            'ends_at' => '2026-06-03',
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 20000,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => false,
        ]);

        $this->flexibleAssignment = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->flexiblePackage->id,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-05-31',
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 15000,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => false,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    public function test_show_ajax_returns_auto_prolong_fields_for_fixed_assignment(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', $this->fixedAssignment));

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonPath('assignment.auto_prolong_enabled', false)
            ->assertJsonPath('assignment.auto_prolong_editable', true)
            ->assertJsonPath('assignment.schedule_type', 'fixed');
    }

    public function test_show_ajax_marks_flexible_assignment_as_not_auto_prolong_editable(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', $this->flexibleAssignment));

        $response->assertOk()
            ->assertJsonPath('assignment.auto_prolong_editable', false)
            ->assertJsonPath('assignment.schedule_type', 'flexible');
    }

    public function test_update_ajax_enables_auto_prolong_and_returns_assignment_payload(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', $this->fixedAssignment), [
                'fee_amount' => '200.00',
                'ends_at' => '2026-06-03',
                'auto_prolong_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.auto_prolong_enabled', true)
            ->assertJsonPath('assignment.auto_prolong_badge', true);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->fixedAssignment->id,
            'auto_prolong_enabled' => 1,
        ]);
    }

    public function test_update_ajax_rejects_auto_prolong_on_flexible_with_field_error(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', $this->flexibleAssignment), [
                'fee_amount' => '150.00',
                'ends_at' => '2026-05-31',
                'auto_prolong_enabled' => true,
            ]);

        $response->assertStatus(422);
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertJsonValidationErrors(['auto_prolong_enabled']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->flexibleAssignment->id,
            'auto_prolong_enabled' => 0,
        ]);
    }

    public function test_update_ajax_rejects_second_auto_prolong_on_same_student_with_field_error(): void
    {
        $this->fixedAssignment->forceFill(['auto_prolong_enabled' => true])->save();

        $secondFixed = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->fixedPackage->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount_cents' => 20000,
            'is_paid' => false,
            'created_by' => $this->user->id,
            'auto_prolong_enabled' => false,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', $secondFixed), [
                'fee_amount' => '200.00',
                'auto_prolong_enabled' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['auto_prolong_enabled']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $secondFixed->id,
            'auto_prolong_enabled' => 0,
        ]);
    }

    public function test_users_search_ajax_marks_blocked_student_only_with_context_assign(): void
    {
        $this->fixedAssignment->forceFill(['auto_prolong_enabled' => true])->save();

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'AjaxProlongFree',
            'name' => 'Другой',
        ]);

        $assign = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.users-search', [
                'q' => 'AjaxProlong',
                'context' => 'assign',
            ]));

        $assign->assertOk();
        $assignResults = collect($assign->json('results'));

        $blocked = $assignResults->firstWhere('id', $this->student->id);
        $this->assertNotNull($blocked);
        $this->assertTrue((bool) ($blocked['blocked'] ?? false));
        $this->assertTrue((bool) ($blocked['disabled'] ?? false));
        $this->assertSame(
            UserLessonPackageAutoProlongGuard::BLOCK_REASON,
            $blocked['blocked_reason'] ?? null
        );

        $free = $assignResults->firstWhere('id', $other->id);
        $this->assertNotNull($free);
        $this->assertFalse((bool) ($free['blocked'] ?? false));
        $this->assertFalse((bool) ($free['disabled'] ?? false));

        // Calendar / filter (no context=assign): student stays selectable for first fixed bind.
        $calendar = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.users-search', [
                'q' => 'AjaxProlong',
            ]));

        $calendar->assertOk();
        $calendarResults = collect($calendar->json('results'));
        $selectable = $calendarResults->firstWhere('id', $this->student->id);
        $this->assertNotNull($selectable);
        $this->assertFalse((bool) ($selectable['blocked'] ?? false));
        $this->assertFalse((bool) ($selectable['disabled'] ?? false));
        $this->assertNull($selectable['blocked_reason'] ?? null);
    }

    public function test_assignments_datatable_includes_auto_prolong_badge_fields(): void
    {
        $this->fixedAssignment->forceFill(['auto_prolong_enabled' => true])->save();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 50,
            ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $this->fixedAssignment->id);
        $this->assertNotNull($row);
        $this->assertTrue((bool) ($row['auto_prolong_badge'] ?? false));
        $this->assertTrue((bool) ($row['auto_prolong_enabled'] ?? false));
        $this->assertNotEmpty($row['auto_prolong_badge_label'] ?? null);
    }
}
