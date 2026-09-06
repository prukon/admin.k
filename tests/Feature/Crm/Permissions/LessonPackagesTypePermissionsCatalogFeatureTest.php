<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use App\Support\LessonPackageTypePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог скрытых прав lessonPackages.type.fixed / flexible / no_schedule:
 * описание, группа, sort_order, без выдачи ролям.
 *
 * UI/валидация: LessonPackageTypePermissionAccessFeatureTest.
 * Postpay (sort 39): LessonPackagesTypePostpayPermissionCatalogFeatureTest.
 *
 * @see PermissionGroupsReorganizationFeatureTest
 * @see PartnerBasePermissionsTest
 */
final class LessonPackagesTypePermissionsCatalogFeatureTest extends CrmTestCase
{
    /**
     * @return array<string, array{description: string, sort_order: int}>
     */
    private function expectedPermissions(): array
    {
        return [
            LessonPackageTypePermission::PERMISSION_FIXED => [
                'description' => 'Абонементы, тип «Фиксированный»',
                'sort_order' => 36,
            ],
            LessonPackageTypePermission::PERMISSION_FLEXIBLE => [
                'description' => 'Абонементы, тип «Предоплата»',
                'sort_order' => 37,
            ],
            LessonPackageTypePermission::PERMISSION_NO_SCHEDULE => [
                'description' => 'Абонементы, тип «Разовое занятие»',
                'sort_order' => 38,
            ],
        ];
    }

    public function test_type_permissions_exist_hidden_in_lesson_packages_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'lessonPackages')->value('id');
        $this->assertGreaterThan(0, $groupId);

        foreach ($this->expectedPermissions() as $name => $meta) {
            $row = DB::table('permissions')->where('name', $name)->first();
            $this->assertNotNull($row, "Право {$name} должно существовать");
            $this->assertSame($meta['description'], (string) $row->description);
            $this->assertSame($groupId, (int) $row->permission_group_id);
            $this->assertSame(0, (int) $row->is_visible);
            $this->assertSame($meta['sort_order'], (int) $row->sort_order);
        }
    }

    public function test_superadmin_rules_page_shows_type_permissions(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Абонементы', $html);
        foreach ($this->expectedPermissions() as $name => $meta) {
            $this->assertStringContainsString($name, $html, "name {$name} на матрице");
            $this->assertStringContainsString($meta['description'], $html);
        }
    }

    public function test_partner_admin_rules_page_hides_type_permissions(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        foreach (array_keys($this->expectedPermissions()) as $name) {
            $this->assertStringNotContainsString(
                $name,
                $html,
                "Скрытое право {$name} не должно быть в матрице admin партнёра"
            );
        }
    }

    public function test_new_partner_base_roles_do_not_receive_type_permissions(): void
    {
        $partner = Partner::factory()->create();

        foreach (array_keys($this->expectedPermissions()) as $permissionName) {
            $permId = $this->permissionId($permissionName);
            foreach (['user', 'admin', 'trainer'] as $roleName) {
                $this->assertFalse(
                    DB::table('permission_role')
                        ->where('partner_id', $partner->id)
                        ->where('role_id', $this->roleId($roleName))
                        ->where('permission_id', $permId)
                        ->exists(),
                    "Роль {$roleName} нового партнёра не должна иметь {$permissionName}"
                );
            }
        }
    }
}
