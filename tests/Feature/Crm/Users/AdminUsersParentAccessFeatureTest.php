<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к разделу «Пользователи» и endpoint’ам родителя: 200 при users.view, 401/403 без доступа.
 *
 * @see /docs/documentation/admin-users.html
 */
final class AdminUsersParentAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    private function grantUsersView(): void
    {
        $this->asAdmin();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    public function test_guest_cannot_access_users_and_parent_endpoints(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertStatus(302);
        $this->getJson('/admin/users/data?draw=1')->assertUnauthorized();
        $this->getJson(route('admin.users.parents.search', ['q' => '']))->assertUnauthorized();
    }

    public function test_authenticated_user_without_users_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);

        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->get(route('admin.user1'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson('/admin/users/data?draw=1&start=0&length=10')
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('admin.users.parents.search', ['q' => '']))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('admin.users.table-settings.get'))
            ->assertForbidden();
    }

    public function test_users_section_and_parent_endpoints_return_ok_with_users_view(): void
    {
        $this->grantUsersView();

        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Доступный',
            'firstname'  => 'Родитель',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'lastname'   => 'SmokeStudent',
        ]);

        $this->get(route('admin.user1'))->assertOk();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('admin.users.parents.search', ['q' => 'Доступный']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonFragment(['text' => 'Доступный Родитель']);

        $this->getJson(route('admin.users.parents.search', ['q' => '']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['parent' => true, 'name' => true],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'             => 'Новый',
            'lastname'         => 'УченикДоступ',
            'role_id'          => $this->studentRoleId(),
            'parent_lastname'  => 'Род',
            'parent_firstname' => 'Доступ',
            'is_enabled'       => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $newId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $newId);

        $this->patchJson(route('admin.user.update', $newId), [
            'name'             => 'Новый',
            'lastname'         => 'УченикДоступ',
            'role_id'          => $this->studentRoleId(),
            'parent_lastname'  => 'Род',
            'parent_firstname' => 'ДоступОбновлён',
            'is_enabled'       => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
    }
}
