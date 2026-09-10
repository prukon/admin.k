<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] UX: страница справочника → «Изменить» show=12 → таблица 12 → update без F5 оставляет 12.
 *
 * @see LessonPackagesListWorkflowFeatureTest
 */
final class LessonPackageEditFillDefaultsWorkflowFeatureTest extends CrmTestCase
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

    public function test_edit_from_directories_page_keeps_twelve_lessons_in_table_without_reload(): void
    {
        $unique = 'WF-fill-12-'.uniqid('', true);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $unique,
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price_cents' => 1680000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->withoutVite();

        $page = $this->get(route('admin.directories.lesson-packages.index'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('lessonPackageEditModal', false)
            ->assertSee('lesson-packages-table', false)
            ->assertSee('reloadPackagesTable', false)
            ->assertSee('applyEditScheduleTypeUi();', false)
            ->assertSee('else if (fromTypeChange)', false);

        $show = $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('lesson_package.lessons_count', 12)
            ->assertJsonPath('lesson_package.duration_days', 120)
            ->json('lesson_package');

        $updatedName = $unique.'-saved';
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            [
                'name' => $updatedName,
                'schedule_type' => $show['schedule_type'],
                'duration_days' => $show['duration_days'],
                'lessons_count' => $show['lessons_count'],
                'price' => $show['price'],
                'freeze_enabled' => 0,
                'auto_attendance_enabled' => 0,
            ],
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

        $row = collect($afterUpdate['data'] ?? [])->firstWhere('id', $package->id);
        $this->assertIsArray($row, 'Строка должна быть в DataTables без перезагрузки страницы.');
        $this->assertSame($updatedName, $row['name']);
        $this->assertSame(12, (int) $row['lessons_count']);
        $this->assertSame(120, (int) $row['duration_days']);
        $this->assertSame(12, (int) $package->fresh()->lessons_count);
        $this->assertSame(120, (int) $package->fresh()->duration_days);
    }

    public function test_school_schedule_packages_tab_shares_same_edit_fill_contract(): void
    {
        $this->withoutVite();

        $page = $this->get(route('admin.lesson-packages.index'));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('applyEditScheduleTypeUi(fromTypeChange)', false)
            ->assertSee('applyEditScheduleTypeUi();', false)
            ->assertSee('else if (fromTypeChange)', false)
            ->assertSee('reloadPackagesTable', false);
    }
}
