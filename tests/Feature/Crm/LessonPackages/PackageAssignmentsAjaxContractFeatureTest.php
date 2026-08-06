<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * AJAX-контракт модалки «Изменить» на вкладке назначений
 * (fetch + X-Requested-With / Accept: application/json).
 *
 * Create-форма — classic POST (см. PackageAssignmentsNonAjaxSafetyNetFeatureTest).
 *
 * @see LessonPackageAssignmentsHistoryAjaxContractFeatureTest
 * @see LessonPackageAssignmentEndsAtAjaxContractFeatureTest
 */
final class PackageAssignmentsAjaxContractFeatureTest extends CrmTestCase
{
    private LessonPackage $package;

    private User $student;

    private UserLessonPackage $assignment;

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
            'lastname' => 'Ajax',
            'name' => 'Ученик',
        ]);

        $this->package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Ajax assignment package',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->assignment = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 15000,
            'is_paid' => false,
            'created_by' => $this->user->id,
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

    public function test_show_ajax_returns_assignment_payload_not_empty_200(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', [
                'assignment' => $this->assignment->id,
            ]));

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonStructure([
            'assignment' => [
                'id',
                'fee_amount',
                'period_editable',
                'effective_is_paid',
            ],
        ]);
        $response->assertJsonPath('assignment.id', $this->assignment->id);
        $response->assertJsonPath('assignment.fee_amount', 150);
    }

    public function test_update_ajax_returns_success_assignment_and_persists_fee(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', [
                'assignment' => $this->assignment->id,
            ]), [
                'fee_amount' => '199.99',
            ]);

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonPath('success', true)
            ->assertJsonPath('assignment.fee_amount', 199.99)
            ->assertJsonStructure(['assignment' => ['id', 'fee_amount']]);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->assignment->id,
            'fee_amount_cents' => 19999,
        ]);
    }

    public function test_update_ajax_validation_returns_422_with_field_errors(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', [
                'assignment' => $this->assignment->id,
            ]), [
                'fee_amount' => '',
            ]);

        $response->assertStatus(422);
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertJsonValidationErrors(['fee_amount']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->assignment->id,
            'fee_amount_cents' => 15000,
        ]);
    }

    public function test_destroy_ajax_returns_success_json_and_deletes_assignment(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.lesson-packages.assignments.destroy', [
                'assignment' => $this->assignment->id,
            ]));

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('user_lesson_packages', [
            'id' => $this->assignment->id,
        ]);
    }

    public function test_destroy_ajax_returns_422_when_lessons_already_consumed(): void
    {
        $this->assignment->forceFill([
            'lessons_remaining' => 3,
        ])->save();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.lesson-packages.assignments.destroy', [
                'assignment' => $this->assignment->id,
            ]));

        $response->assertStatus(422);
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertJsonStructure(['success', 'message']);
        $response->assertJsonPath('success', false);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $this->assignment->id,
        ]);
    }

    public function test_show_ajax_forbidden_without_package_assignments_permission(): void
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

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', [
                'assignment' => $this->assignment->id,
            ]))
            ->assertForbidden();
    }

    public function test_update_ajax_forbidden_without_package_assignments_permission(): void
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

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', [
                'assignment' => $this->assignment->id,
            ]), [
                'fee_amount' => '200.00',
            ])
            ->assertForbidden();
    }

    public function test_assignments_page_inline_js_markers_for_ajax_edit_modal(): void
    {
        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ulpAssignmentEditModal', $html);
        $this->assertStringContainsString('js-ulp-assignment-edit', $html);
        $this->assertStringContainsString('ulp-modal-save', $html);
        $this->assertStringContainsString("method: 'PUT'", $html);
        $this->assertStringContainsString('X-Requested-With', $html);
        $this->assertStringContainsString('fetchJson', $html);
    }
}
