<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class AdminUsersAccessTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->firstOrFail()->id;
    }

    private function grantUsersView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_guest_cannot_access_users_section(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertStatus(302);
        $this->getJson('/admin/users/data')->assertStatus(401);
    }

    public function test_users_section_forbidden_without_users_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('admin.user1'))
            ->assertStatus(403);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson('/admin/users/data?draw=1')
            ->assertStatus(403);
    }

    public function test_users_page_and_endpoints_return_ok_with_users_view(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $roleId = $this->defaultRoleId();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('data-column-key="parent"', false)
            ->assertSee('id="create-parent-lastname"', false)
            ->assertSee('id="edit-parent-lastname"', false);

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['parent' => true, 'name' => true],
        ])->assertOk();

        $this->getJson(route('logs.data.user', ['draw' => 1]))->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'              => 'Ученик',
            'lastname'          => 'Тестов',
            'parent_lastname'   => 'Родительов',
            'parent_firstname'  => 'Род',
            'parent_middlename' => 'Родович',
            'role_id'           => $roleId,
            'is_enabled'        => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $this->getJson(route('admin.user.edit', $userId), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->patchJson(route('admin.user.update', $userId), [
            'name'             => 'Ученик',
            'lastname'         => 'Тестов',
            'parent_lastname'  => 'Новиков',
            'parent_firstname' => 'Нов',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
    }
}
