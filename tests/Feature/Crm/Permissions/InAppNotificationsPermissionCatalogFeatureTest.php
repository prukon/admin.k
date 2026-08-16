<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class InAppNotificationsPermissionCatalogFeatureTest extends CrmTestCase
{
    public function test_permissions_exist_in_dedicated_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'inAppNotifications')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $view = DB::table('permissions')->where('name', 'inAppNotifications.view')->first();
        $this->assertNotNull($view);
        $this->assertSame('Колокольчик и лента уведомлений', (string) $view->description);
        $this->assertSame($groupId, (int) $view->permission_group_id);
        $this->assertSame(1, (int) $view->is_visible);
        $this->assertSame(10, (int) $view->sort_order);

        $manage = DB::table('permissions')->where('name', 'inAppNotifications.manage')->first();
        $this->assertNotNull($manage);
        $this->assertSame($groupId, (int) $manage->permission_group_id);
        $this->assertSame(0, (int) $manage->is_visible);
        $this->assertSame(20, (int) $manage->sort_order);
    }

    public function test_superadmin_matrix_shows_both_permissions(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringContainsString('Уведомления CRM', $html);
        $this->assertStringContainsString('inAppNotifications.view', $html);
        $this->assertStringContainsString('inAppNotifications.manage', $html);
    }

    public function test_partner_admin_matrix_shows_view_hides_manage(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringContainsString('inAppNotifications.view', $html);
        $this->assertStringNotContainsString('inAppNotifications.manage', $html);
    }

    public function test_new_partner_base_roles_receive_view_not_manage(): void
    {
        $partner = Partner::factory()->create();
        $viewId = $this->permissionId('inAppNotifications.view');
        $manageId = $this->permissionId('inAppNotifications.manage');

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->assertTrue(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $viewId)
                    ->exists(),
                "Роль {$roleName} должна иметь inAppNotifications.view"
            );
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $manageId)
                    ->exists(),
                "Роль {$roleName} не должна иметь inAppNotifications.manage"
            );
        }
    }
}
