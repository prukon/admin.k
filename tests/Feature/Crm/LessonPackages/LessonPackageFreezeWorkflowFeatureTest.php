<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] UX: страница без scheduleSlots.view → правка названия (JS шлёт freeze_enabled=0)
 * → заморозка остаётся, строка видна в DataTables без F5.
 *
 * @see LessonPackageAutoAttendanceWorkflowFeatureTest
 */
final class LessonPackageFreezeWorkflowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermissionToUser(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_edit_from_packages_page_without_freeze_checkbox_keeps_flag_and_row_without_reload(): void
    {
        $actor = $this->createUserWithoutPermission('scheduleSlots.view', $this->partner);
        $this->grantPermissionToUser($actor, 'lessonPackages.view');
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $unique = 'WF-freeze-'.uniqid('', true);
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => $unique,
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 150000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
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
            ->assertSee('normalizePayload', false)
            ->assertDontSee('id="edit_freeze_enabled"', false)
            ->assertSee('id="colLessonPackageFreeze"', false);

        $updatedName = $unique.'-saved';
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            [
                'name' => $updatedName,
                'schedule_type' => 'flexible',
                'duration_days' => 30,
                'lessons_count' => 8,
                'price' => '1500.00',
                'freeze_enabled' => 0,
                'freeze_days' => '',
                'auto_attendance_enabled' => 0,
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('lesson_package.name', $updatedName)
            ->assertJsonPath('lesson_package.freeze_enabled', true)
            ->assertJsonPath('lesson_package.freeze_days', 14);

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
        $this->assertTrue((bool) $row['freeze_enabled']);
        $this->assertSame('14', $row['freeze_label']);
        $this->assertSame(14, (int) $package->fresh()->freeze_days);
    }
}
