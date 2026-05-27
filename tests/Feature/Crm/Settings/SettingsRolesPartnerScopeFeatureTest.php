<?php

namespace Tests\Feature\Crm\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Настройки → Роли: partner-scope при удалении (PartnerRoleDeletionService),
 * доступ к странице и endpoint’ам, изоляция между партнёрами.
 */
final class SettingsRolesPartnerScopeFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    // --- Доступ (дополняет RulesSettingsPageFullAccessFeatureTest) ---

    public function test_guest_cannot_access_roles_section_endpoints(): void
    {
        Auth::logout();

        $adminRoleId = (int) Role::where('name', 'admin')->value('id');
        $permId = (int) Permission::query()->value('id');

        foreach ($this->allRolesSectionRoutesPayload($adminRoleId, $permId) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_settings_roles_view_all_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->grantSettingsRolesView($actor);
        $this->actingAs($actor);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $perm = Permission::firstOrFail();

        $this->get(route('admin.setting.rule'))->assertOk();

        foreach ($this->allRolesSectionRoutesPayload($adminRole->id, $perm->id) as $item) {
            if ($item['method'] === 'DELETE' && str_contains($item['url'], 'role/delete')) {
                continue;
            }

            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С settings.roles.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Scope delete ' . uniqid('', true),
        ])->assertOk();

        $roleId = (int) $create->json('role.id');

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $roleId,
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_user_without_settings_roles_view_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->actingAs($denied);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $perm = Permission::firstOrFail();

        $this->get(route('admin.setting.rule'))->assertForbidden();

        foreach ($this->allRolesSectionRoutesPayload($adminRole->id, $perm->id) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без settings.roles.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    // --- Изоляция партнёров (STRICT_CURRENT) ---

    public function test_delete_role_returns_404_for_role_of_another_partner(): void
    {
        $this->asSuperadmin();

        $foreignRole = $this->createCustomRoleForPartner($this->foreignPartner->id, 'foreign_only_role');

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $foreignRole->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('roles', ['id' => $foreignRole->id]);
        $this->assertDatabaseHas('partner_role', [
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $foreignRole->id,
        ]);
    }

    public function test_delete_role_only_reassigns_users_of_current_partner(): void
    {
        $this->asSuperadmin();

        $sharedRole = $this->createCustomRoleForPartner($this->partner->id, 'shared_scope_role');
        DB::table('partner_role')->insert([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $sharedRole->id,
        ]);

        $localUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $sharedRole->id,
        ]);
        $foreignUser = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $sharedRole->id,
        ]);

        $perm = Permission::firstOrFail();
        DB::table('permission_role')->insert([
            [
                'role_id'       => $sharedRole->id,
                'permission_id' => $perm->id,
                'partner_id'    => $this->partner->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'role_id'       => $sharedRole->id,
                'permission_id' => $perm->id,
                'partner_id'    => $this->foreignPartner->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $sharedRole->id,
        ])->assertOk();

        $defaultRoleId = (int) Role::where('name', 'user')->value('id');

        $localUser->refresh();
        $foreignUser->refresh();

        $this->assertSame($defaultRoleId, (int) $localUser->role_id);
        $this->assertSame($sharedRole->id, (int) $foreignUser->role_id);

        $this->assertDatabaseMissing('permission_role', [
            'role_id'    => $sharedRole->id,
            'partner_id' => $this->partner->id,
        ]);
        $this->assertDatabaseHas('permission_role', [
            'role_id'    => $sharedRole->id,
            'partner_id' => $this->foreignPartner->id,
        ]);
        $this->assertDatabaseHas('roles', ['id' => $sharedRole->id]);
        $this->assertDatabaseMissing('partner_role', [
            'partner_id' => $this->partner->id,
            'role_id'    => $sharedRole->id,
        ]);
        $this->assertDatabaseHas('partner_role', [
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $sharedRole->id,
        ]);
    }

    public function test_delete_role_removes_role_record_when_last_partner_detaches(): void
    {
        $this->asSuperadmin();

        $role = $this->createCustomRoleForPartner($this->partner->id, 'single_partner_role');

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $role->id,
        ])->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('partner_role', ['role_id' => $role->id]);
    }

    public function test_delete_system_role_still_returns_400(): void
    {
        $this->asSuperadmin();

        $systemRole = Role::where('is_sistem', 1)->firstOrFail();

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $systemRole->id,
        ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    public function test_rules_logs_data_excludes_foreign_partner_type_700_logs(): void
    {
        $this->asSuperadmin();
        $this->grantSettingsRolesView($this->user);

        \App\Models\MyLog::create([
            'type'        => 700,
            'action'      => 710,
            'author_id'   => $this->user->id,
            'partner_id'  => $this->partner->id,
            'description' => 'roles-log-home',
            'created_at'  => now(),
        ]);
        \App\Models\MyLog::create([
            'type'        => 700,
            'action'      => 710,
            'author_id'   => $this->user->id,
            'partner_id'  => $this->foreignPartner->id,
            'description' => 'roles-log-foreign',
            'created_at'  => now(),
        ]);

        $descriptions = collect(
            $this->getJson(route('logs.data.rule', ['draw' => 1, 'start' => 0, 'length' => 50]))->json('data') ?? []
        )->pluck('description')->all();

        $this->assertContains('roles-log-home', $descriptions);
        $this->assertNotContains('roles-log-foreign', $descriptions);
    }

    public function test_toggle_permission_on_foreign_partner_role_returns_403_and_does_not_write_pivot(): void
    {
        $this->asSuperadmin();
        $this->grantSettingsRolesView($this->user);

        $foreignRole = $this->createCustomRoleForPartner($this->foreignPartner->id, 'toggle_foreign_role');
        $perm = Permission::firstOrFail();

        DB::table('permission_role')
            ->where('role_id', $foreignRole->id)
            ->where('permission_id', $perm->id)
            ->delete();

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $foreignRole->id,
            'permission_id' => $perm->id,
            'value'         => 'true',
        ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Роль не найдена или недоступна для текущего партнёра.',
            ]);

        $this->assertDatabaseMissing('permission_role', [
            'role_id'       => $foreignRole->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->partner->id,
        ]);
    }

    public function test_toggle_permission_on_custom_role_of_current_partner_succeeds(): void
    {
        $this->asSuperadmin();
        $this->grantSettingsRolesView($this->user);

        $localRole = $this->createCustomRoleForPartner($this->partner->id, 'toggle_local_role');
        $perm = Permission::firstOrFail();

        DB::table('permission_role')
            ->where('role_id', $localRole->id)
            ->where('permission_id', $perm->id)
            ->delete();

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $localRole->id,
            'permission_id' => $perm->id,
            'value'         => 'true',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('permission_role', [
            'role_id'       => $localRole->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->partner->id,
        ]);
    }

    public function test_toggle_detach_does_not_remove_foreign_partner_permission_pivot(): void
    {
        $this->asSuperadmin();
        $this->grantSettingsRolesView($this->user);

        $sharedRole = $this->createCustomRoleForPartner($this->partner->id, 'toggle_shared_role');
        DB::table('partner_role')->insert([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $sharedRole->id,
        ]);

        $perm = Permission::firstOrFail();
        DB::table('permission_role')->insert([
            [
                'role_id'       => $sharedRole->id,
                'permission_id' => $perm->id,
                'partner_id'    => $this->partner->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'role_id'       => $sharedRole->id,
                'permission_id' => $perm->id,
                'partner_id'    => $this->foreignPartner->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $sharedRole->id,
            'permission_id' => $perm->id,
            'value'         => 'false',
        ])->assertOk();

        $this->assertDatabaseMissing('permission_role', [
            'role_id'       => $sharedRole->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->partner->id,
        ]);
        $this->assertDatabaseHas('permission_role', [
            'role_id'       => $sharedRole->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->foreignPartner->id,
        ]);
    }

    public function test_create_role_links_only_to_current_partner(): void
    {
        $this->asSuperadmin();
        $this->grantSettingsRolesView($this->user);

        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'scoped_role_' . uniqid('', true),
        ])->assertOk();

        $roleId = (int) $create->json('role.id');

        $this->assertDatabaseHas('partner_role', [
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
        ]);
        $this->assertDatabaseMissing('partner_role', [
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $roleId,
        ]);
    }

    public function test_toggle_permission_on_system_role_for_current_partner_succeeds(): void
    {
        $this->asSuperadmin();
        $this->grantSettingsRolesView($this->user);

        $systemRole = Role::where('is_sistem', 1)->firstOrFail();
        $perm = Permission::firstOrFail();

        DB::table('permission_role')
            ->where('role_id', $systemRole->id)
            ->where('permission_id', $perm->id)
            ->where('partner_id', $this->partner->id)
            ->delete();

        $this->postJson(route('admin.setting.rule.toggle'), [
            'role_id'       => $systemRole->id,
            'permission_id' => $perm->id,
            'value'         => 'true',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('permission_role', [
            'role_id'       => $systemRole->id,
            'permission_id' => $perm->id,
            'partner_id'    => $this->partner->id,
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allRolesSectionRoutesPayload(int $roleId, int $permissionId): array
    {
        return [
            ['method' => 'GET', 'url' => route('admin.setting.rule')],
            [
                'method'  => 'POST',
                'url'     => route('admin.setting.rule.toggle'),
                'data'    => [
                    'role_id'       => $roleId,
                    'permission_id' => $permissionId,
                    'value'         => 'true',
                ],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('logs.data.rule', ['draw' => 1, 'start' => 0, 'length' => 10]),
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.setting.role.create'),
                'data'    => ['name' => 'Route smoke ' . uniqid('', true)],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'DELETE',
                'url'     => route('admin.setting.role.delete'),
                'data'    => ['role_id' => $roleId],
                'headers' => ['HTTP_ACCEPT' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
            ],
        ];
    }

    private function createCustomRoleForPartner(int $partnerId, string $machineName): Role
    {
        $role = Role::create([
            'name'       => $machineName . '_' . uniqid('', true),
            'label'      => 'Test ' . $machineName,
            'is_sistem'  => 0,
            'is_visible' => 1,
            'order_by'   => (Role::max('order_by') ?? 0) + 10,
        ]);

        DB::table('partner_role')->insert([
            'partner_id' => $partnerId,
            'role_id'    => $role->id,
        ]);

        return $role;
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
