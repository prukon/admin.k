<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] E2E smoke модалки смены ends_at: страница → show → update → show/data без F5.
 *
 * @see LessonPackagesListWorkflowFeatureTest
 */
final class LessonPackageAssignmentEndsAtWorkflowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
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

    public function test_assignments_page_modal_show_update_ends_at_visible_without_reload(): void
    {
        $this->withoutVite();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Workflow',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'EndsAt workflow package',
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

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('ulpAssignmentEditModal', false)
            ->assertSee('ulp-modal-period-end', false)
            ->assertSee('ulp-modal-period-end-err', false)
            ->assertSee('ulp-modal-save', false)
            ->assertSee('js-ulp-assignment-edit', false)
            ->assertSee('period_editable', false)
            ->assertSee('body.ends_at', false);

        $show = $this->getJson(
            route('admin.lesson-packages.assignments.show', ['assignment' => $assignment->id]),
            $this->ajaxHeaders()
        );
        $show->assertOk()
            ->assertJsonPath('assignment.period_editable', true)
            ->assertJsonPath('assignment.period_end', '2026-05-01');

        $update = $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $assignment->id]),
            [
                'fee_amount' => '100.00',
                'ends_at' => '2026-08-01',
            ],
            $this->ajaxHeaders()
        );
        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.period_end', '2026-08-01');
        $this->assertNotSame('', trim((string) $update->getContent()));

        $showAgain = $this->getJson(
            route('admin.lesson-packages.assignments.show', ['assignment' => $assignment->id]),
            $this->ajaxHeaders()
        );
        $showAgain->assertOk()
            ->assertJsonPath('assignment.period_end', '2026-08-01')
            ->assertJsonPath('assignment.period_start', '2026-04-01');

        $data = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]), $this->ajaxHeaders());

        $data->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $rows = collect($data->json('data') ?? []);
        $row = $rows->first(fn ($r) => (int) ($r['id'] ?? 0) === (int) $assignment->id);
        $this->assertNotNull($row, 'Строка назначения должна быть в DataTables после update без F5');
        $this->assertStringContainsString('1.08.26', (string) ($row['period'] ?? ''));
    }
}
