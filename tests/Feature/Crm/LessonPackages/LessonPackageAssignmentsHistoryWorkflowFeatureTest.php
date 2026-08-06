<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] E2E smoke истории назначений: страница → мутации → запись видна в logs-data без F5.
 *
 * @see LessonPackagesListWorkflowFeatureTest
 * @see LessonPackageAssignmentEndsAtWorkflowFeatureTest
 */
final class LessonPackageAssignmentsHistoryWorkflowFeatureTest extends CrmTestCase
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
        $this->grantPermission('lessonPackages.manualPaid.manage');
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
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * [P2] Страница → store → logs-data created → update → logs-data updated →
     * manual-paid → logs-data manual_paid → destroy → logs-data deleted.
     */
    public function test_assignments_page_history_workflow_visible_in_logs_data_without_reload(): void
    {
        $unique = 'HistWF-'.uniqid('', true);

        $this->withoutVite();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Workflow',
            'name' => $unique,
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'History workflow package '.$unique,
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false)
            ->assertSee('lesson-packages\/assignments\/logs-data', false)
            ->assertSee('ulpAssignmentCreateModal', false)
            ->assertSee('ulp-assignments-table', false);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'fee_amount' => '140.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $assignmentId = (int) UserLessonPackage::query()
            ->where('user_id', $student->id)
            ->where('lesson_package_id', $package->id)
            ->where('fee_amount_cents', 14000)
            ->value('id');
        $this->assertGreaterThan(0, $assignmentId);

        $afterCreate = collect($this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
        ]), $this->ajaxHeaders())->assertOk()->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($afterCreate)->contains(
                fn (string $d): bool => str_contains($d, $unique) && str_contains($d, '140 ₽')
            ),
            'После store запись user_lesson_package.created должна быть видна в logs-data без F5.'
        );

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $assignmentId]),
            ['fee_amount' => '190.00'],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true);

        $afterUpdate = collect($this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 2,
            'start' => 0,
            'length' => 100,
        ]), $this->ajaxHeaders())->assertOk()->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($afterUpdate)->contains(
                fn (string $d): bool => str_contains($d, '140 ₽ → 190 ₽')
            ),
            'После update запись user_lesson_package.updated должна быть видна в logs-data без F5.'
        );

        $this->postJson(
            route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $assignmentId]),
            ['mode' => 'paid', 'comment' => 'Workflow наличные '.$unique],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('success', true);

        $afterManual = collect($this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 3,
            'start' => 0,
            'length' => 100,
        ]), $this->ajaxHeaders())->assertOk()->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($afterManual)->contains(
                fn (string $d): bool => str_contains($d, 'Workflow наличные '.$unique)
            ),
            'После manual-paid запись должна быть видна в logs-data без F5.'
        );

        // Сброс оплаты, чтобы destroy оставался допустимым при полном остатке.
        $this->postJson(
            route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $assignmentId]),
            ['mode' => 'unpaid', 'comment' => 'Сброс перед удалением '.$unique],
            $this->ajaxHeaders()
        )->assertOk();

        $this->deleteJson(
            route('admin.lesson-packages.assignments.destroy', ['assignment' => $assignmentId]),
            [],
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $afterDelete = collect($this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 4,
            'start' => 0,
            'length' => 100,
        ]), $this->ajaxHeaders())->assertOk()->json('data'));

        $this->assertTrue(
            $afterDelete->contains(
                fn (array $row): bool => ($row['action'] ?? '') === AuditEvent::UserLessonPackageDeleted->label()
                    || str_contains((string) ($row['description'] ?? ''), 'Назначение абонемента удалено.')
            ),
            'После destroy запись user_lesson_package.deleted должна быть видна в logs-data без F5.'
        );

        $this->assertDatabaseMissing('user_lesson_packages', ['id' => $assignmentId]);
    }
}
