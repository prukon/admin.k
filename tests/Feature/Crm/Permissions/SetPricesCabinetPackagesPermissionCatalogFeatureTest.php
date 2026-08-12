<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use App\Support\CabinetLessonPackagePermission;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог прав setPrices.cabinetPackages.*: скрытость, группа, sort_order, без выдачи ролям.
 *
 * UI консоли: DashboardCabinetPackagesTypeAccessFeatureTest.
 *
 * @see SetPricesPermissionCatalogFeatureTest
 * @see PartnerBasePermissionsTest
 */
final class SetPricesCabinetPackagesPermissionCatalogFeatureTest extends CrmTestCase
{
    /**
     * @return array<string, array{description: string, sort_order: int}>
     */
    private function expectedPermissions(): array
    {
        return [
            CabinetLessonPackagePermission::FIXED => [
                'description' => 'Консоль: фиксированный абонемент',
                'sort_order' => 25,
            ],
            CabinetLessonPackagePermission::FLEXIBLE => [
                'description' => 'Консоль: гибкий абонемент',
                'sort_order' => 26,
            ],
            CabinetLessonPackagePermission::SINGLE => [
                'description' => 'Консоль: разовое занятие',
                'sort_order' => 27,
            ],
            CabinetLessonPackagePermission::POSTPAY => [
                'description' => 'Консоль: постоплата',
                'sort_order' => 28,
            ],
        ];
    }

    public function test_cabinet_packages_permissions_exist_hidden_in_set_prices_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'setPrices')->value('id');
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

    public function test_superadmin_rules_page_shows_cabinet_packages_permissions(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Установка цен', $html);
        foreach ($this->expectedPermissions() as $name => $meta) {
            $this->assertStringContainsString($name, $html, "name {$name} на матрице");
            $this->assertStringContainsString($meta['description'], $html);
        }
    }

    public function test_partner_admin_rules_page_hides_cabinet_packages_permissions(): void
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

    public function test_new_partner_base_roles_do_not_receive_cabinet_packages_permissions(): void
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
