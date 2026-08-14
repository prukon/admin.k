<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каталог права schedule.trainerSalary.scheme.classic: скрытое, не в базовых ролях.
 */
final class TrainerSalarySchemeClassicPermissionCatalogFeatureTest extends CrmTestCase
{
    private const PERMISSION_NAME = 'schedule.trainerSalary.scheme.classic';

    private const DESCRIPTION = 'ЗП тренеров: схема «Классическая» (оклад + ставка за тренировку)';

    public function test_permission_exists_in_schedule_group_hidden(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'schedule')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', self::PERMISSION_NAME)->first();
        $this->assertNotNull($row, 'Право schedule.trainerSalary.scheme.classic должно существовать');
        $this->assertSame(self::DESCRIPTION, (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(0, (int) $row->is_visible);
        $this->assertSame(30, (int) $row->sort_order);
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

        $this->assertStringContainsString('Расписание', $html);
        $this->assertStringContainsString(self::PERMISSION_NAME, $html);
        $this->assertStringContainsString(self::DESCRIPTION, $html);
    }

    public function test_new_partner_base_roles_do_not_receive_permission(): void
    {
        $partner = Partner::factory()->create();
        $permId = $this->permissionId(self::PERMISSION_NAME);

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $roleId = $this->roleId($roleName);
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} нового партнёра не должна иметь " . self::PERMISSION_NAME
            );
        }
    }
}
