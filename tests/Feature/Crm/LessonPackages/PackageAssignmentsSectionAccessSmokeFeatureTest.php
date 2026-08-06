<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Smoke контроля доступа вкладки «Назначение абонементов» (setPrices.packageAssignments.view):
 * гость → redirect/401/403; без права → 403; с правами — страница и функционал → 200.
 *
 * @see PackageAssignmentsPageFullAccessFeatureTest
 * @see LessonPackagesSectionAccessSmokeFeatureTest
 */
final class PackageAssignmentsSectionAccessSmokeFeatureTest extends CrmTestCase
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

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'Smoke',
            'name' => 'Ученик',
        ]);

        $this->package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Smoke assignment package',
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
            'fee_amount_cents' => 10000,
            'is_paid' => false,
            'created_by' => $this->user->id,
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

    private function grantAssignmentsAccess(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');
    }

    public function test_guest_cannot_open_assignments_page(): void
    {
        Auth::logout();

        $response = $this->get(route('admin.lesson-packages.assignments'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_assignments_page_forbidden_without_package_assignments_permission(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments'))->assertForbidden();
    }

    public function test_assignments_page_returns_200_with_required_permissions(): void
    {
        $this->grantAssignmentsAccess();

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('Назначение абонементов', false)
            ->assertSee('ulp-assignments-table', false)
            ->assertViewHas('activeTab', 'assignments');
    }

    public function test_packages_index_shows_assignments_tab_with_permission(): void
    {
        $this->grantAssignmentsAccess();

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('Назначение абонементов', false)
            ->assertSee(route('admin.lesson-packages.assignments', [], false), false);
    }

    public function test_all_assignments_read_and_write_endpoints_return_200_with_permissions(): void
    {
        $this->grantAssignmentsAccess();
        $this->grantPermission('lessonPackages.manualPaid.manage');

        // Страница
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk();

        // DataTable / история / колонки / поиск / группы
        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $columnsGet = $this->getJson(route('admin.lesson-packages.assignments.columns-settings.get'));
        $columnsGet->assertOk();
        $this->assertIsArray($columnsGet->json());
        $this->assertNotSame('', trim((string) $columnsGet->getContent()));

        $this->postJson(route('admin.lesson-packages.assignments.columns-settings.save'), [
            'columns' => ['student' => true, 'actions' => true],
        ])->assertOk();

        $this->getJson(route('admin.lesson-packages.assignments.users-search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('admin.lesson-packages.assignments.teams-for-user', [
            'user_id' => $this->student->id,
        ]))->assertOk()->assertJsonStructure(['results']);

        // Модалка: show / update
        $this->getJson(route('admin.lesson-packages.assignments.show', [
            'assignment' => $this->assignment->id,
        ]))->assertOk()->assertJsonStructure(['assignment' => ['id', 'fee_amount']]);

        $this->putJson(route('admin.lesson-packages.assignments.update', [
            'assignment' => $this->assignment->id,
        ]), [
            'fee_amount' => '111.00',
        ])->assertOk()->assertJsonPath('success', true);

        // Create: classic POST → followRedirects → финальный 200 страницы
        $this->followingRedirects()
            ->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->package->id,
                'fee_amount' => '222.00',
            ])
            ->assertOk()
            ->assertSee('Назначение абонементов', false);

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'fee_amount_cents' => 22200,
        ]);

        // manual-paid
        $this->postJson(route('admin.lesson-packages.assignments.manual-paid', [
            'assignment' => $this->assignment->id,
        ]), [
            'mode' => 'paid',
            'comment' => 'Smoke ручная оплата назначения',
        ])->assertOk()->assertJsonPath('success', true);

        // destroy
        $toDelete = UserLessonPackage::query()->create([
            'user_id' => $this->student->id,
            'lesson_package_id' => $this->package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 5000,
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $this->deleteJson(route('admin.lesson-packages.assignments.destroy', [
            'assignment' => $toDelete->id,
        ]))->assertOk()->assertJson(['success' => true]);
    }

    public function test_can_gate_package_assignments_view_reflects_permission(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->assertFalse($this->user->can('setPrices.packageAssignments.view'));

        $this->grantPermission('setPrices.packageAssignments.view');
        $this->user->unsetRelation('role');

        $this->assertTrue($this->user->fresh()->can('setPrices.packageAssignments.view'));
    }
}
