<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Настройки → Права и роли»: контроль доступа и smoke 200 на все endpoint'ы.
 */
final class RulesSettingsSectionFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_cannot_access_any_rules_section_endpoint(): void
    {
        Auth::logout();

        foreach ($this->rulesSectionRoutesPayload(includeRoleDelete: false) as $item) {
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

    public function test_user_without_settings_roles_view_gets_403_on_all_section_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->rulesSectionRoutesPayload(includeRoleDelete: false) as $item) {
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

    public function test_user_with_only_settings_roles_view_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->grantPermission($actor, 'settings.roles.view');
        $this->actingAs($actor);

        $this->assertAllRulesSectionEndpointsReturnOk();
    }

    public function test_admin_all_rules_section_endpoints_return_200(): void
    {
        $this->asAdmin();

        $this->assertTrue(
            $this->user->fresh()->hasPermission('settings.roles.view'),
            'Роль admin должна иметь settings.roles.view для текущего партнёра'
        );

        $this->assertAllRulesSectionEndpointsReturnOk();
    }

    public function test_superadmin_all_rules_section_endpoints_return_200(): void
    {
        $this->asSuperadmin();

        $this->assertAllRulesSectionEndpointsReturnOk();
    }

    public function test_rules_index_page_returns_200_with_permission_groups_accordion(): void
    {
        $this->asAdmin();

        $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'rule')
            ->assertViewHas(['roles', 'permissions', 'groups'])
            ->assertSee('id="permission-accordion"', false)
            ->assertSee('Главное меню', false)
            ->assertSee('Управление пользователями', false);
    }

    private function assertAllRulesSectionEndpointsReturnOk(): void
    {
        foreach ($this->rulesSectionRoutesPayload(includeRoleDelete: false) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Section smoke ' . uniqid('', true),
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function rulesSectionRoutesPayload(bool $includeRoleDelete): array
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $perm = Permission::firstOrFail();

        $routes = [
            [
                'method'  => 'GET',
                'url'     => route('admin.setting.rule'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.setting.rule.toggle'),
                'data'   => [
                    'role_id'       => $adminRole->id,
                    'permission_id' => $perm->id,
                    'value'         => 'true',
                ],
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.rule', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.setting.role.create'),
                'data'   => [
                    'name' => 'Payload role ' . uniqid('', true),
                ],
            ],
        ];

        if ($includeRoleDelete) {
            $routes[] = [
                'method' => 'DELETE',
                'url'    => route('admin.setting.role.delete'),
                'data'   => [
                    'role_id' => $adminRole->id,
                ],
            ];
        }

        return $routes;
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
