<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * reports.tbank.payments.view: скрытое, не в базовых ролях, без выдачи permission_role.
 */
final class ReportsTbankPaymentsPermissionCatalogFeatureTest extends CrmTestCase
{
    private const PERMISSION = 'reports.tbank.payments.view';

    private const DESCRIPTION = 'Страница «Платежи T‑Bank»';

    public function test_permission_exists_hidden_in_reports_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'reports')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', self::PERMISSION)->first();
        $this->assertNotNull($row);
        $this->assertSame(self::DESCRIPTION, (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(0, (int) $row->is_visible);
        $this->assertSame(16, (int) $row->sort_order);
    }

    public function test_add_migration_does_not_touch_permission_role_on_up(): void
    {
        $src = (string) file_get_contents(base_path(
            'database/migrations/2026_09_07_073200_add_reports_tbank_payments_view_permission.php'
        ));

        $this->assertStringContainsString("'is_visible' => 0", $src);
        $this->assertStringContainsString("'name' => 'reports.tbank.payments.view'", $src);
        $this->assertStringContainsString("'slug', 'reports'", $src);

        $upStart = strpos($src, 'function up');
        $downStart = strpos($src, 'function down');
        $this->assertNotFalse($upStart);
        $this->assertNotFalse($downStart);
        $up = substr($src, $upStart, $downStart - $upStart);
        $this->assertStringNotContainsString('permission_role', $up);
    }

    public function test_new_partner_base_roles_do_not_receive_permission(): void
    {
        $partner = Partner::factory()->create();
        $permId = $this->permissionId(self::PERMISSION);

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} нового партнёра не должна иметь ".self::PERMISSION
            );
        }
    }

    public function test_admin_rules_page_does_not_show_hidden_permission(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::PERMISSION, $html);
        $this->assertStringNotContainsString(self::DESCRIPTION, $html);
    }

    public function test_superadmin_rules_page_shows_hidden_permission(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringContainsString(self::PERMISSION, $html);
        $this->assertStringContainsString(self::DESCRIPTION, $html);
    }
}
