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

    public function test_messages_own_delete_is_hidden_and_not_in_base_roles(): void
    {
        $groupId = (int) DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');
        $this->assertGreaterThan(0, $groupId);

        $row = DB::table('permissions')->where('name', 'messages.own.delete')->first();
        $this->assertNotNull($row);
        $this->assertSame('Удаление своих сообщений в чате', (string) $row->description);
        $this->assertSame($groupId, (int) $row->permission_group_id);
        $this->assertSame(0, (int) $row->is_visible);
        $this->assertSame(112, (int) $row->sort_order);

        $permId = (int) $row->id;
        $partner = Partner::factory()->create();
        foreach (['user', 'admin', 'trainer'] as $roleName) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('partner_id', $partner->id)
                    ->where('role_id', $this->roleId($roleName))
                    ->where('permission_id', $permId)
                    ->exists(),
                "Роль {$roleName} не должна иметь messages.own.delete"
            );
        }
    }

    public function test_own_delete_migration_does_not_touch_permission_role_on_up(): void
    {
        $src = (string) file_get_contents(base_path(
            'database/migrations/2026_09_07_222000_add_messages_own_delete_permission.php'
        ));

        $this->assertStringContainsString("'is_visible'          => 0", $src);
        $this->assertStringContainsString("'name'                => self::PERMISSION_NAME", $src);
        $this->assertStringContainsString('messages.own.delete', $src);
        $this->assertStringContainsString("'slug', 'mainMenu'", $src);

        $upStart = strpos($src, 'function up');
        $downStart = strpos($src, 'function down');
        $this->assertNotFalse($upStart);
        $this->assertNotFalse($downStart);
        $up = substr($src, $upStart, $downStart - $upStart);
        $this->assertStringNotContainsString('permission_role', $up);
    }

    public function test_admin_rules_page_does_not_show_own_delete_permission(): void
    {
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringNotContainsString('messages.own.delete', $html);
        $this->assertStringNotContainsString('Удаление своих сообщений в чате', $html);
    }

    public function test_superadmin_rules_page_shows_own_delete_permission(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('admin.setting.rule'))->assertOk()->getContent();
        $this->assertStringContainsString('messages.own.delete', $html);
        $this->assertStringContainsString('Удаление своих сообщений в чате', $html);
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
