<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] E2E smoke списка шаблонов: страница → AJAX create/update/delete →
 * строка видна в DataTables без F5.
 *
 * @see UsersImportFeatureTest::test_import_workflow_page_preview_commit_and_datatable_shows_student
 */
final class LessonPackagesListWorkflowFeatureTest extends CrmTestCase
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
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'E2E smoke пакет',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    /**
     * [P2] Страница справочника → create → data содержит строку → update → data обновлена → delete → data без строки.
     */
    public function test_directories_page_ajax_crud_workflow_visible_in_datatable_without_reload(): void
    {
        $unique = 'E2E-' . uniqid('', true);

        $this->withoutVite();

        $page = $this->get(route('admin.directories.lesson-packages.index'));
        $page->assertOk()
            ->assertSee('lessonPackageCreateModal', false)
            ->assertSee('lesson-packages-table', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('reloadPackagesTable', false);
        $this->assertNotSame('', trim((string) $page->getContent()));

        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => $unique,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $createdId = (int) LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', $unique)
            ->value('id');
        $this->assertGreaterThan(0, $createdId);

        $afterCreate = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => $unique,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $createdRow = collect($afterCreate['data'] ?? [])->firstWhere('id', $createdId);
        $this->assertIsArray($createdRow, 'Созданный абонемент должен появиться в DataTables без F5.');
        $this->assertSame($unique, $createdRow['name']);
        $this->assertTrue((bool) ($createdRow['can_delete'] ?? false));

        $updatedName = $unique . '-updated';
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $createdId]),
            $this->validPayload([
                'name' => $updatedName,
                'schedule_type' => 'flexible',
                'lessons_count' => 4,
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $afterUpdate = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 2,
            'start' => 0,
            'length' => 100,
            'name' => $updatedName,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $updatedRow = collect($afterUpdate['data'] ?? [])->firstWhere('id', $createdId);
        $this->assertIsArray($updatedRow, 'Обновлённый абонемент должен быть виден в DataTables без F5.');
        $this->assertSame($updatedName, $updatedRow['name']);
        $this->assertSame('Гибкий', $updatedRow['schedule_type_label']);
        $this->assertSame(4, (int) $updatedRow['lessons_count']);

        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $createdId]), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $afterDelete = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 3,
            'start' => 0,
            'length' => 100,
            'name' => $updatedName,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $this->assertFalse(
            collect($afterDelete['data'] ?? [])->contains(fn (array $row): bool => (int) ($row['id'] ?? 0) === $createdId),
            'Удалённый абонемент не должен оставаться в DataTables.'
        );
        $this->assertDatabaseMissing('lesson_packages', ['id' => $createdId]);
    }

    /**
     * [P2] Тот же workflow со страницы /admin/lesson-packages (не справочники).
     */
    public function test_school_section_packages_page_ajax_create_visible_in_datatable_without_reload(): void
    {
        $unique = 'SchoolE2E-' . uniqid('', true);

        $this->withoutVite();

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('lessonPackageCreateModal', false)
            ->assertSee('lesson-packages-table', false);

        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => $unique,
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $data = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => $unique,
            'schedule_type' => 'no_schedule',
        ]))->assertOk()->json();

        $this->assertTrue(
            collect($data['data'] ?? [])->contains(fn (array $row): bool => ($row['name'] ?? '') === $unique),
            'Созданный абонемент должен появиться в DataTables без перезагрузки.'
        );
    }
}
