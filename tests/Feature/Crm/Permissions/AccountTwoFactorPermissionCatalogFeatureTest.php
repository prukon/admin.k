<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * account.user.two_factor.update: скрытое, не в базовых ролях (по умолчанию выкл.).
 */
final class AccountTwoFactorPermissionCatalogFeatureTest extends CrmTestCase
{
    private const PERMISSION = 'account.user.two_factor.update';

    private const DESCRIPTION = 'ЛК: двухфакторная аутентификация (SMS)';

    public function test_permission_exists_hidden_in_account_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'account')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', self::PERMISSION)->first();
        $this->assertNotNull($row);
        $this->assertSame(self::DESCRIPTION, (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(0, (int) $row->is_visible);
        $this->assertSame(62, (int) $row->sort_order);
    }

    public function test_add_migration_creates_hidden_permission_without_role_backfill(): void
    {
        $src = (string) file_get_contents(base_path(
            'database/migrations/2026_09_10_034100_add_account_user_two_factor_update_permission.php'
        ));

        $this->assertStringContainsString("'is_visible'          => 0", $src);
        $this->assertStringContainsString("'name'                => self::PERMISSION_NAME", $src);
        $this->assertStringContainsString("'slug', 'account'", $src);
        $this->assertStringNotContainsString('BACKFILL_ROLE_NAMES', $src);
        $this->assertStringNotContainsString('insertOrIgnore', $src);
    }

    public function test_revoke_migration_keeps_permission_hidden_and_clears_assignments(): void
    {
        $src = (string) file_get_contents(base_path(
            'database/migrations/2026_09_10_044800_revoke_account_user_two_factor_update_from_roles.php'
        ));

        $this->assertStringContainsString("'is_visible' => 0", $src);
        $this->assertStringContainsString("where('permission_id', \$permissionId)->delete()", $src);
        $this->assertStringContainsString('Не восстанавливаем выдачу', $src);
    }

    public function test_config_does_not_include_permission_in_any_base_role(): void
    {
        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->assertNotContains(
                self::PERMISSION,
                config('role_base_permissions.roles.'.$roleName, []),
                "Роль {$roleName} не должна получать ".self::PERMISSION.' по умолчанию'
            );
        }
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
