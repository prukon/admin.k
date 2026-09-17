<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * reports.ltv.teams.view: видимое, в базовых admin/trainer, без backfill существующих партнёров.
 */
final class ReportsLtvTeamsPermissionCatalogFeatureTest extends CrmTestCase
{
    private const PERMISSION = 'reports.ltv.teams.view';

    private const DESCRIPTION = 'Отчёт «Платежи по группам»';

    public function test_permission_exists_visible_in_reports_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'reports')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', self::PERMISSION)->first();
        $this->assertNotNull($row);
        $this->assertSame(self::DESCRIPTION, (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(1, (int) $row->is_visible);
        $this->assertSame(15, (int) $row->sort_order);
    }

    public function test_add_migration_does_not_touch_permission_role_on_up(): void
    {
        $src = (string) file_get_contents(base_path(
            'database/migrations/2026_09_17_093700_add_reports_ltv_teams_view_permission.php'
        ));

        $this->assertStringContainsString("'is_visible' => 1", $src);
        $this->assertStringContainsString("'name' => self::PERMISSION_NAME", $src);
        $this->assertStringContainsString("'slug', 'reports'", $src);
        $this->assertStringContainsString('Существующим партнёрам не выдаётся', $src);

        $upStart = strpos($src, 'function up');
        $downStart = strpos($src, 'function down');
        $this->assertNotFalse($upStart);
        $this->assertNotFalse($downStart);
        $up = substr($src, $upStart, $downStart - $upStart);
        $this->assertStringNotContainsString('permission_role', $up);
    }

    public function test_new_partner_admin_and_trainer_receive_permission_user_does_not(): void
    {
        $partner = Partner::factory()->create();
        $permId = $this->permissionId(self::PERMISSION);

        foreach (['admin', 'trainer'] as $roleName) {
            $this->assertTrue(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} нового партнёра должна иметь ".self::PERMISSION
            );
        }

        $this->assertFalse(
            DB::table('permission_role')
                ->where('partner_id', $partner->id)
                ->where('role_id', $this->roleId('user'))
                ->where('permission_id', $permId)
                ->exists(),
            'Роль user нового партнёра не должна иметь '.self::PERMISSION
        );
    }

    public function test_admin_rules_page_shows_visible_permission(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringContainsString(self::PERMISSION, $html);
        $this->assertStringContainsString(self::DESCRIPTION, $html);
    }
}
