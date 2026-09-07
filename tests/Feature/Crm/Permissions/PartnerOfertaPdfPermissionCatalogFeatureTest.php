<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог partner.oferta.pdf: скрытое, никому не выдаётся.
 */
final class PartnerOfertaPdfPermissionCatalogFeatureTest extends CrmTestCase
{
    private const PERMISSION = 'partner.oferta.pdf';

    private const DESCRIPTION = 'Скачать партнёрскую оферту в PDF';

    public function test_permission_exists_hidden_in_partner_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'partner')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', self::PERMISSION)->first();
        $this->assertNotNull($row, 'Право partner.oferta.pdf должно существовать');
        $this->assertSame(self::DESCRIPTION, (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(0, (int) $row->is_visible);
        $this->assertSame(100, (int) $row->sort_order);
    }

    public function test_superadmin_rules_page_shows_permission(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(self::PERMISSION, $html);
        $this->assertStringContainsString(self::DESCRIPTION, $html);
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

    public function test_new_partner_base_roles_do_not_receive_permission(): void
    {
        $partner = Partner::factory()->create();
        $permId = $this->permissionId(self::PERMISSION);

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $roleId = $this->roleId($roleName);
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} нового партнёра не должна иметь ".self::PERMISSION
            );
        }
    }
}
