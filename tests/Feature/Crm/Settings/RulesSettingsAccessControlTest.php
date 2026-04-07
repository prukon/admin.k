<?php

namespace Tests\Feature\Crm\Settings;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к экрану «Настройки → Права и роли» (middleware can:settings.roles.view).
 */
class RulesSettingsAccessControlTest extends CrmTestCase
{
    public function test_rules_routes_are_forbidden_for_user_without_settings_roles_view(): void
    {
        $this->assertFalse(
            $this->user->fresh()->hasPermission('settings.roles.view'),
            'Сценарий рассчитан на роль user без права settings.roles.view'
        );

        $this->get(route('admin.setting.rule'))->assertForbidden();

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => Role::where('name', 'admin')->value('id'),
            'permission_id' => Permission::query()->value('id'),
            'value'         => 'true',
        ])->assertForbidden();

        $this->getJson(route('logs.data.rule'))->assertForbidden();

        $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Роль без доступа',
        ])->assertForbidden();

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => Role::where('name', 'user')->value('id'),
        ])->assertForbidden();
    }

    public function test_rules_page_and_json_endpoints_return_200_for_partner_admin_with_settings_roles_view(): void
    {
        $this->asAdmin();

        $this->assertTrue(
            $this->user->fresh()->hasPermission('settings.roles.view'),
            'Сценарий рассчитан на роль admin с правом settings.roles.view для текущего партнёра'
        );

        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->assertSee('Права и роли', false);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $perm = Permission::firstOrFail();

        DB::table('permission_role')->updateOrInsert(
            [
                'role_id'       => $adminRole->id,
                'permission_id' => $perm->id,
                'partner_id'    => $this->partner->id,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $adminRole->id,
            'permission_id' => $perm->id,
            'value'         => 'true',
        ])->assertOk()->assertJson(['success' => true]);

        $this->getJson(route('logs.data.rule'))
            ->assertOk()
            ->assertJsonStructure(['data']);

        $roleLabel = 'Доступ тест ' . uniqid('', true);
        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => $roleLabel,
        ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['role', 'permission_ids']);

        $newRoleId = (int) $create->json('role.id');
        $this->assertGreaterThan(0, $newRoleId);
        $this->assertNotEmpty($create->json('permission_ids'));

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $newRoleId,
        ])->assertOk()->assertJson(['success' => true]);
    }
}
