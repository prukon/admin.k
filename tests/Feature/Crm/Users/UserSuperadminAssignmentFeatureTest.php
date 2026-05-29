<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Запрет назначения роли superadmin через CRM (создание и редактирование).
 */
final class UserSuperadminAssignmentFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_users_page_roles_exclude_superadmin_for_admin(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('roles', function ($roles) use ($superRole) {
                return !$roles->pluck('id')->contains($superRole->id);
            });
    }

    public function test_store_rejects_superadmin_role_assignment(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();
        $userRole = Role::query()->where('name', 'user')->firstOrFail();

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Хакер',
            'lastname'   => 'Тест',
            'role_id'    => $superRole->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);

        $this->assertDatabaseMissing('users', [
            'name'    => 'Хакер',
            'role_id' => $superRole->id,
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Нормальный',
            'lastname'   => 'Клиент',
            'role_id'    => $userRole->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
    }

    public function test_store_rejects_superadmin_role_even_for_superadmin_actor(): void
    {
        $this->asSuperadmin();
        $this->grantPermission($this->user, 'users.view');

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Ещё один',
            'lastname'   => 'Супер',
            'role_id'    => $superRole->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_edit_json_marks_superadmin_target_and_omits_superadmin_from_roles(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();
        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $superRole->id,
        ]);

        $response = $this->getJson(route('admin.user.edit', $target->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $json = $response->json();

        $this->assertTrue($json['targetIsSuperadmin']);
        $this->assertNotContains($superRole->id, collect($json['roles'])->pluck('id')->all());
    }

    public function test_update_rejects_superadmin_role_assignment(): void
    {
        $this->asAdmin();
        $this->grantUsersUpdatePermissions();

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();
        $userRole = Role::query()->where('name', 'user')->firstOrFail();

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $userRole->id,
        ]);

        $this->patchJson(route('admin.user.update', $target->id), [
            'name'       => $target->name,
            'lastname'   => $target->lastname,
            'role_id'    => $superRole->id,
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);

        $this->assertSame($userRole->id, (int) $target->fresh()->role_id);
    }

    public function test_update_keeps_superadmin_role_when_editing_superadmin_user(): void
    {
        $this->asAdmin();
        $this->grantUsersUpdatePermissions();

        $superRole = Role::query()->where('name', 'superadmin')->firstOrFail();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Root',
            'lastname'   => 'Admin',
            'role_id'    => $superRole->id,
        ]);

        $this->patchJson(route('admin.user.update', $target->id), [
            'name'       => 'Root Updated',
            'lastname'   => 'Admin',
            'role_id'    => $adminRole->id,
            'is_enabled' => 1,
        ])->assertOk();

        $target->refresh();
        $this->assertSame('Root Updated', $target->name);
        $this->assertSame($superRole->id, (int) $target->role_id);
    }

    public function test_admin_users_create_and_edit_endpoints_return_200(): void
    {
        $this->asAdmin();
        $this->grantUsersUpdatePermissions();

        $studentRole = Role::query()->where('name', 'user')->firstOrFail();

        $this->get(route('admin.user1'))->assertOk();

        $storeResponse = $this->postJson(route('admin.user.store'), [
            'name'       => 'Новый',
            'lastname'   => 'Ученик',
            'role_id'    => $studentRole->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $storeResponse->assertOk();
        $userId = (int) $storeResponse->json('user.id');

        $this->getJson(route('admin.user.edit', $userId), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->patchJson(route('admin.user.update', $userId), [
            'name'       => 'Обновлён',
            'lastname'   => 'Ученик',
            'role_id'    => $studentRole->id,
            'is_enabled' => 1,
        ])->assertOk();
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

    private function grantUsersUpdatePermissions(): void
    {
        foreach ([
            'users.view',
            'users.name.update',
            'users.activity.update',
            'users.role.update',
        ] as $permission) {
            $this->grantPermission($this->user, $permission);
        }
    }
}
