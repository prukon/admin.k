<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] E2E: страница шаблонов → AJAX create/update полей «Для договора» → строка в DataTables без F5.
 */
final class LessonPackageContractFieldsWorkflowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantPermission('lessonPackages.view');
        $this->grantLessonPackageTypePermissions();
        $this->grantPermission('scheduleSlots.view');
        $this->grantPermission('contracts.lessonPackage.bind');
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
            'name' => 'E2E договор',
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1500.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_directories_page_ajax_crud_shows_contract_fields_in_datatable_without_reload(): void
    {
        $unique = 'E2E-contract-'.uniqid('', true);

        $this->withoutVite();

        $page = $this->get(route('admin.directories.lesson-packages.index'));
        $page->assertOk()
            ->assertSee('create_contract_fields_section', false)
            ->assertSee('colLessonPackageLessonsPerWeek', false)
            ->assertSee('lesson_price_label', false);
        $this->assertNotSame('', trim((string) $page->getContent()));

        $this->postJson(route('admin.lesson-packages.store'), $this->validPayload([
            'name' => $unique,
            'lessons_per_week' => 2,
            'lesson_duration_minutes' => 60,
            'lesson_price' => '700.00',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $afterCreate = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => $unique,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $row = collect($afterCreate['data'] ?? [])->firstWhere('name', $unique);
        $this->assertIsArray($row, 'Строка должна появиться в DataTables без перезагрузки.');
        $this->assertSame('2', $row['lessons_per_week_label']);
        $this->assertArrayNotHasKey('lessons_per_month_label', $row);
        $this->assertSame('60', $row['lesson_duration_minutes_label']);
        $this->assertSame('700,00 ₽', $row['lesson_price_label']);
        $packageId = (int) $row['id'];

        $updatedName = $unique.'-upd';
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $packageId]),
            $this->validPayload([
                'name' => $updatedName,
                'lessons_per_week' => 4,
                'lesson_duration_minutes' => 90,
                'lesson_price' => '950.00',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $afterUpdate = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => $updatedName,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $updatedRow = collect($afterUpdate['data'] ?? [])->firstWhere('id', $packageId);
        $this->assertIsArray($updatedRow);
        $this->assertSame($updatedName, $updatedRow['name']);
        $this->assertSame('4', $updatedRow['lessons_per_week_label']);
        $this->assertSame('90', $updatedRow['lesson_duration_minutes_label']);
        $this->assertSame('950,00 ₽', $updatedRow['lesson_price_label']);
    }
}
