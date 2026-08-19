<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class ChatMessagesViewPermissionCatalogFeatureTest extends CrmTestCase
{
    public function test_messages_view_is_visible_in_main_menu_group(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', 'messages.view')->first();
        $this->assertNotNull($row);
        $this->assertSame('Страница "Сообщения"', (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(1, (int) $row->is_visible);
    }

    public function test_messages_threads_delete_is_hidden_and_not_in_base_roles(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', 'messages.threads.delete')->first();
        $this->assertNotNull($row);
        $this->assertSame('Удаление чата (шапка диалога)', (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(0, (int) $row->is_visible);

        $permId = (int) $row->id;
        $partner = Partner::factory()->create();
        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} не должна иметь messages.threads.delete"
            );
        }
    }

    public function test_new_partner_base_roles_receive_messages_view(): void
    {
        $partner = Partner::factory()->create();
        $permId = $this->permissionId('messages.view');

        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->assertTrue(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} должна иметь messages.view"
            );
        }
    }

    public function test_partner_admin_matrix_shows_messages_view(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringContainsString('messages.view', $html);
    }
}
