<?php

namespace Tests\Feature\Crm\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к /admin/settings/rules и связанным эндпоинтам
 * (settings.roles.view → 200, без права → 403).
 */
final class RulesSettingsPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_rules_index_page_returns_200_with_settings_view(): void
    {
        $this->asAdmin();

        $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'rule')
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('>Настройки</span>', false)
            ->assertSee('>История</span>', false)
            ->assertSee('id="permission-accordion"', false);
    }

    public function test_all_rules_section_endpoints_return_200_for_admin_with_settings_roles_view(): void
    {
        $this->asAdmin();

        $this->get(route('admin.setting.rule'))->assertOk();

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
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('logs.data.rule', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data']);

        $roleLabel = 'Full access ' . uniqid('', true);
        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => $roleLabel,
        ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['role', 'permission_ids']);

        $newRoleId = (int) $create->json('role.id');
        $this->assertGreaterThan(0, $newRoleId);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $newRoleId,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_user_with_only_settings_roles_view_can_access_all_section_endpoints_return_ok(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->grantSettingsRolesView($actor);
        $this->actingAs($actor);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $perm = Permission::firstOrFail();

        $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->assertSee('payments-report-toolbar', false);

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $adminRole->id,
            'permission_id' => $perm->id,
            'value'         => 'false',
        ])->assertOk();

        $this->getJson(route('logs.data.rule', ['draw' => 1]))->assertOk();

        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Granted view ' . uniqid('', true),
        ])->assertOk();

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => (int) $create->json('role.id'),
        ])->assertOk();
    }

    public function test_rules_routes_return_403_without_settings_roles_view(): void
    {
        $this->assertFalse(
            $this->user->fresh()->hasPermission('settings.roles.view'),
            'Сценарий рассчитан на роль user без settings.roles.view'
        );

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $perm = Permission::firstOrFail();

        $this->get(route('admin.setting.rule'))->assertForbidden();

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $adminRole->id,
            'permission_id' => $perm->id,
            'value'         => 'true',
        ])->assertForbidden();

        $this->getJson(route('logs.data.rule', ['draw' => 1]))->assertForbidden();

        $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Forbidden role',
        ])->assertForbidden();

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $adminRole->id,
        ])->assertForbidden();
    }

    public function test_guest_cannot_access_any_rules_endpoint(): void
    {
        Auth::logout();

        $adminRoleId = (int) Role::where('name', 'admin')->value('id');
        $permId = (int) Permission::query()->value('id');

        $endpoints = [
            fn () => $this->get(route('admin.setting.rule')),
            fn () => $this->postJson(route('admin.setting.rule.toggle'), [
                'role_id'       => $adminRoleId,
                'permission_id' => $permId,
                'value'         => 'true',
            ]),
            fn () => $this->getJson(route('logs.data.rule', ['draw' => 1])),
            fn () => $this->postJson(route('admin.setting.role.create'), [
                'name' => 'Guest role',
            ]),
            fn () => $this->deleteJson(route('admin.setting.role.delete'), [
                'role_id' => $adminRoleId,
            ]),
        ];

        foreach ($endpoints as $call) {
            $status = $call()->getStatusCode();
            $this->assertContains($status, [302, 401, 403], 'Unexpected status: ' . $status);
        }
    }

    private function grantSettingsRolesView(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('settings.roles.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
