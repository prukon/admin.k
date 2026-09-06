<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог группы schoolSchedule («Расписание школы»): перенос существующих прав,
 * все скрытые, без выдачи новым партнёрам.

 *
 * @see PermissionGroupsReorganizationFeatureTest
 * @see SetPricesPermissionCatalogFeatureTest
 */
final class SchoolSchedulePermissionGroupCatalogFeatureTest extends CrmTestCase
{
    private const GROUP_SLUG = 'schoolSchedule';

    /**
     * @return array<string, array{description: string, sort_order: int, is_visible: int}>
     */
    private function expectedPermissions(): array
    {
        return [
            'scheduleSlots.view' => [
                'description' => 'Страница "Расписание школы"',
                'sort_order' => 10,
                'is_visible' => 0,
            ],
            'scheduleSlots.manage' => [
                'description' => 'Расписание школы: управление слотами',
                'sort_order' => 20,
                'is_visible' => 0,
            ],
            'scheduleSlots.table' => [
                'description' => 'Расписание школы: вкладка «Таблица занятий»',
                'sort_order' => 30,
                'is_visible' => 0,
            ],
            'lessonPackages.export' => [
                'description' => 'Абонементы: выгрузка занятий и назначений в Excel',
                'sort_order' => 40,
                'is_visible' => 0,
            ],
            'setPrices.packageAssignments.view' => [
                'description' => 'Назначение абонементов',
                'sort_order' => 50,
                'is_visible' => 0,
            ],
            'lessonPackages.manualPaid.manage' => [
                'description' => 'Абонементы: ручная отметка оплаты назначения',
                'sort_order' => 60,
                'is_visible' => 0,
            ],
        ];
    }

    public function test_group_and_permissions_exist_in_catalog(): void
    {
        $group = DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->first();
        $this->assertNotNull($group, 'Группа schoolSchedule должна существовать');
        $this->assertSame('Расписание школы', (string) $group->name);
        $this->assertSame(13, (int) $group->sort_order);
        $this->assertSame(1, (int) $group->is_visible);

        $groupId = (int) $group->id;
        $this->assertGreaterThan(0, $groupId);

        foreach ($this->expectedPermissions() as $name => $meta) {
            $row = DB::table('permissions')->where('name', $name)->first();
            $this->assertNotNull($row, "Право {$name} должно существовать");
            $this->assertSame($meta['description'], (string) $row->description, "description для {$name}");
            $this->assertSame($groupId, (int) $row->permission_group_id, "группа schoolSchedule для {$name}");
            $this->assertSame($meta['is_visible'], (int) $row->is_visible, "is_visible для {$name}");
            $this->assertSame($meta['sort_order'], (int) $row->sort_order, "sort_order для {$name}");
        }
    }

    public function test_moved_permissions_are_not_in_previous_groups(): void
    {
        $namesByOldGroup = [
            'mainMenu' => ['scheduleSlots.view'],
            'schedule' => ['scheduleSlots.manage', 'scheduleSlots.table'],
            'lessonPackages' => ['lessonPackages.export', 'lessonPackages.manualPaid.manage'],
            'setPrices' => ['setPrices.packageAssignments.view'],
        ];

        foreach ($namesByOldGroup as $oldSlug => $names) {
            $oldGroupId = (int) DB::table('permission_groups')->where('slug', $oldSlug)->value('id');
            $this->assertGreaterThan(0, $oldGroupId, "Группа {$oldSlug}");

            foreach ($names as $name) {
                $permissionGroupId = (int) DB::table('permissions')->where('name', $name)->value('permission_group_id');
                $this->assertNotSame(
                    $oldGroupId,
                    $permissionGroupId,
                    "Право {$name} не должно оставаться в {$oldSlug}"
                );
            }
        }
    }

    public function test_superadmin_rules_page_shows_group_and_permissions(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Расписание школы', $html);
        foreach ($this->expectedPermissions() as $name => $meta) {
            $this->assertStringContainsString($name, $html, "name {$name} на матрице");
            $this->assertStringContainsString(
                e($meta['description']),
                $html,
                "description «{$meta['description']}» на матрице"
            );
        }
    }

    public function test_partner_admin_does_not_see_group_because_all_permissions_are_hidden(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $groups = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->viewData('groups');

        $this->assertNotContains(self::GROUP_SLUG, $groups->pluck('slug')->all());
    }

    public function test_group_migration_does_not_grant_or_delete_permissions(): void
    {
        $path = database_path('migrations/2026_09_06_032500_add_school_schedule_permission_group.php');
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertStringContainsString("private const GROUP_SLUG = 'schoolSchedule'", $src);
        $this->assertStringNotContainsString("DB::table('permission_role')", $src);
        $this->assertStringNotContainsString("DB::table('permissions')->insert", $src);
        $this->assertStringNotContainsString("DB::table('permissions')->delete", $src);
    }

    public function test_hide_migration_only_sets_is_visible_and_does_not_touch_permission_role(): void
    {
        $path = database_path('migrations/2026_09_06_035300_hide_school_schedule_permissions.php');
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertStringContainsString("'is_visible' => 0", $src);
        $this->assertStringContainsString('lessonPackages.export', $src);
        $this->assertStringNotContainsString("DB::table('permission_role')", $src);
        $this->assertStringNotContainsString("DB::table('permissions')->insert", $src);
        $this->assertStringNotContainsString("DB::table('permissions')->delete", $src);
    }

    public function test_guest_is_denied_on_rules_page(): void
    {
        Auth::logout();

        $response = $this->get(route('admin.setting.rule'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
    }
}
