<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог группы platformPayments: скрытые способы оплаты кошелька и абонплаты.
 * Миграция не выдаёт права существующим школам.
 *
 * @see PermissionGroupsReorganizationFeatureTest
 * @see PartnerBasePermissionsTest
 */
final class PlatformPaymentsMethodPermissionCatalogFeatureTest extends CrmTestCase
{
    private const GROUP_SLUG = 'platformPayments';

    private const PERM_TBANK = 'platformPayments.method.tbankSbp';

    private const PERM_YOOKASSA = 'platformPayments.method.yookassa';

    private const DESC_TBANK = 'T‑Bank СБП (кошелёк и абонплата)';

    private const DESC_YOOKASSA = 'ЮKassa (кошелёк и абонплата)';

    public function test_group_and_hidden_permissions_exist(): void
    {
        $group = DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->first();
        $this->assertNotNull($group, 'Группа platformPayments должна существовать');
        $this->assertSame('Оплата платформы', (string) $group->name);
        $this->assertSame(34, (int) $group->sort_order);
        $this->assertSame(1, (int) $group->is_visible);

        $groupId = (int) $group->id;

        $tbank = DB::table('permissions')->where('name', self::PERM_TBANK)->first();
        $this->assertNotNull($tbank);
        $this->assertSame(self::DESC_TBANK, (string) $tbank->description);
        $this->assertSame($groupId, (int) $tbank->permission_group_id);
        $this->assertSame(0, (int) $tbank->is_visible);
        $this->assertSame(10, (int) $tbank->sort_order);

        $yookassa = DB::table('permissions')->where('name', self::PERM_YOOKASSA)->first();
        $this->assertNotNull($yookassa);
        $this->assertSame(self::DESC_YOOKASSA, (string) $yookassa->description);
        $this->assertSame($groupId, (int) $yookassa->permission_group_id);
        $this->assertSame(0, (int) $yookassa->is_visible);
        $this->assertSame(20, (int) $yookassa->sort_order);
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

        $this->assertStringContainsString('Оплата платформы', $html);
        $this->assertStringContainsString(self::PERM_TBANK, $html);
        $this->assertStringContainsString(self::PERM_YOOKASSA, $html);
        $this->assertStringContainsString(self::DESC_TBANK, $html);
        $this->assertStringContainsString(self::DESC_YOOKASSA, $html);
    }

    public function test_new_partner_admin_receives_tbank_not_yookassa(): void
    {
        $partner = Partner::factory()->create();
        $tbankId = $this->permissionId(self::PERM_TBANK);
        $yookassaId = $this->permissionId(self::PERM_YOOKASSA);

        $adminRoleId = $this->roleId('admin');
        $this->assertTrue(
            DB::table('permission_role')
                ->where('partner_id', $partner->id)
                ->where('role_id', $adminRoleId)
                ->where('permission_id', $tbankId)
                ->exists(),
            'Новый партнёр: admin должен получить T‑Bank СБП платформы из role_base_permissions'
        );

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $yookassaId)
                    ->exists(),
                "Роль {$roleName} нового партнёра не должна иметь ".self::PERM_YOOKASSA
            );
        }

        foreach (['user', 'trainer'] as $roleName) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $tbankId)
                    ->exists(),
                "Роль {$roleName} нового партнёра не должна иметь ".self::PERM_TBANK
            );
        }
    }

    public function test_migration_does_not_grant_permissions_to_roles(): void
    {
        $path = database_path('migrations/2026_09_05_021700_add_platform_payments_method_permissions.php');
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertStringContainsString("private const GROUP_SLUG = 'platformPayments'", $src);
        $this->assertStringNotContainsString('insertOrIgnore', $src);
        $this->assertStringNotContainsString("DB::table('permission_role')->insert", $src);
    }
}
