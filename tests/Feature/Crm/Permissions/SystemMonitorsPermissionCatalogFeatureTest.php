<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use App\Support\SystemMonitors;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог settings.systemMonitors.view: скрытое, никому не выдаётся.
 * settings.reverbOverlay.manage в каталоге нет.
 */
final class SystemMonitorsPermissionCatalogFeatureTest extends CrmTestCase
{
    private const DESCRIPTION = 'Системные мониторы (оверлеи)';

    public function test_system_monitors_permission_exists_hidden_in_settings_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'settings')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', SystemMonitors::PERMISSION)->first();
        $this->assertNotNull($row, 'Право settings.systemMonitors.view должно существовать');
        $this->assertSame(self::DESCRIPTION, (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(0, (int) $row->is_visible);
        $this->assertSame(224, (int) $row->sort_order);
    }

    public function test_legacy_reverb_overlay_permission_is_not_in_catalog(): void
    {
        $this->assertFalse(
            DB::table('permissions')->where('name', 'settings.reverbOverlay.manage')->exists(),
            'Право settings.reverbOverlay.manage не должно быть в каталоге'
        );
        $this->assertFalse(
            DB::table('permissions')->where('name', 'settings.cabinetDiagnostics.manage')->exists(),
            'Право settings.cabinetDiagnostics.manage не должно быть в каталоге'
        );
    }

    public function test_superadmin_rules_page_shows_system_monitors_permission_not_legacy_name(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(SystemMonitors::PERMISSION, $html);
        $this->assertStringContainsString(self::DESCRIPTION, $html);
        $this->assertStringNotContainsString('settings.reverbOverlay.manage', $html);
        $this->assertStringNotContainsString('settings.cabinetDiagnostics.manage', $html);
        $this->assertStringNotContainsString('Оверлей статуса Reverb', $html);
    }

    public function test_admin_rules_page_does_not_show_hidden_system_monitors_permission(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringNotContainsString(SystemMonitors::PERMISSION, $html);
        $this->assertStringNotContainsString(self::DESCRIPTION, $html);
    }

    public function test_new_partner_base_roles_do_not_receive_permission(): void
    {
        $partner = Partner::factory()->create();
        $permId = $this->permissionId(SystemMonitors::PERMISSION);

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $roleId = $this->roleId($roleName);
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} нового партнёра не должна иметь ".SystemMonitors::PERMISSION
            );
        }
    }
}
