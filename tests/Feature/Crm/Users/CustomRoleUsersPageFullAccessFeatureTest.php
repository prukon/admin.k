<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * /admin/roles/{role}: кастомные роли — полный доступ (200) и запреты (403/404).
 */
final class CustomRoleUsersPageFullAccessFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    private Role $customRole;

    private User $customRoleUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->customRole = $this->createPartnerCustomRole('custom_full_access', 'Кастомная роль');

        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $this->customRoleUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->customRole->id,
            'lastname'   => 'Кастомов',
            'name'       => 'Полный',
            'email'      => 'full-access-custom-' . uniqid('', true) . '@example.test',
        ]);
    }

    public function test_custom_role_index_page_returns_200(): void
    {
        $this->get(route('admin.roles.users.index', ['role' => $this->customRole->name]))
            ->assertOk()
            ->assertViewHas('activeTab', 'role-' . $this->customRole->name)
            ->assertSee('id="role-staff-table"', false)
            ->assertSee('roleStaffCreateModal', false)
            ->assertSee($this->customRole->label, false);
    }

    public function test_all_custom_role_page_endpoints_return_200(): void
    {
        $roleName = $this->customRole->name;
        $tableKey = 'role_staff_' . $roleName;

        $this->get(route('admin.roles.users.index', ['role' => $roleName]))->assertOk();

        $this->getJson(route('admin.roles.users.data', ['role' => $roleName, 'draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.roles.users.columns-settings.get', ['role' => $roleName]) . '?table_key=' . $tableKey)
            ->assertOk();

        $this->postJson(route('admin.roles.users.columns-settings.save', ['role' => $roleName]) . '?table_key=' . $tableKey, [
            'columns' => [
                'avatar'     => true,
                'full_name'  => true,
                'email'      => true,
                'phone'      => true,
                'is_enabled' => true,
                'actions'    => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('admin.roles.users.show', ['role' => $roleName, 'user' => $this->customRoleUser->id]))
            ->assertOk()
            ->assertJsonPath('id', $this->customRoleUser->id);

        $createdEmail = 'full-access-new-custom-' . uniqid('', true) . '@example.test';

        $this->postJson(route('admin.roles.users.store', ['role' => $roleName]), [
            'lastname'   => 'Новый',
            'name'       => 'Кастом',
            'email'      => $createdEmail,
            'password'   => 'password123',
            'is_enabled' => 1,
        ])->assertOk();

        $created = User::query()
            ->where('email', $createdEmail)
            ->where('role_id', $this->customRole->id)
            ->firstOrFail();

        $this->putJson(route('admin.roles.users.update', [
            'role' => $roleName,
            'user' => $this->customRoleUser->id,
        ]), [
            'lastname'   => 'Обновлён',
            'name'       => 'Кастом',
            'email'      => $this->customRoleUser->email,
            'is_enabled' => 1,
        ])->assertOk();

        $this->deleteJson(route('admin.roles.users.destroy', [
            'role' => $roleName,
            'user' => $created->id,
        ]))->assertOk();

        $this->assertSoftDeleted('users', ['id' => $created->id]);
    }

    public function test_custom_role_data_lists_only_users_of_that_role(): void
    {
        $adminUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->adminRoleId(),
            'email'      => 'admin-not-custom-list-' . uniqid('', true) . '@example.test',
        ]);

        $json = $this->getJson(route('admin.roles.users.data', [
            'role'   => $this->customRole->name,
            'draw'   => 1,
            'length' => 100,
        ]))
            ->assertOk()
            ->json();

        $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertTrue($ids->contains($this->customRoleUser->id));
        $this->assertFalse($ids->contains($adminUser->id));
    }

    public function test_foreign_partner_custom_role_returns_404(): void
    {
        $foreignRole = $this->createPartnerCustomRoleForPartner($this->foreignPartner->id, 'foreign_custom');

        $this->get(route('admin.roles.users.index', ['role' => $foreignRole->name]))
            ->assertNotFound();
    }

    public function test_custom_role_endpoints_return_403_without_permissions(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $roleName = $this->customRole->name;
        $tableKey = 'role_staff_' . $roleName;
        $userId = $this->customRoleUser->id;

        $this->get(route('admin.roles.users.index', ['role' => $roleName]))->assertForbidden();
        $this->getJson(route('admin.roles.users.data', ['role' => $roleName, 'draw' => 1]))->assertForbidden();
        $this->getJson(route('admin.roles.users.columns-settings.get', ['role' => $roleName]) . '?table_key=' . $tableKey)->assertForbidden();
        $this->postJson(route('admin.roles.users.store', ['role' => $roleName]), [
            'name'     => 'X',
            'lastname' => 'Y',
        ])->assertForbidden();
        $this->getJson(route('admin.roles.users.show', ['role' => $roleName, 'user' => $userId]))->assertForbidden();
        $this->putJson(route('admin.roles.users.update', ['role' => $roleName, 'user' => $userId]), [
            'name'     => 'X',
            'lastname' => 'Y',
        ])->assertForbidden();
        $this->deleteJson(route('admin.roles.users.destroy', ['role' => $roleName, 'user' => $userId]))->assertForbidden();
    }

    public function test_guest_cannot_access_custom_role_page(): void
    {
        Auth::logout();

        $this->get(route('admin.roles.users.index', ['role' => $this->customRole->name]))
            ->assertStatus(302);
    }

    public function test_user_of_different_custom_role_returns_404_on_role_scoped_routes(): void
    {
        $otherRole = $this->createPartnerCustomRole('other_custom', 'Другая роль');
        $otherUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $otherRole->id,
            'email'      => 'other-custom-user-' . uniqid('', true) . '@example.test',
        ]);

        $this->getJson(route('admin.roles.users.show', [
            'role' => $this->customRole->name,
            'user' => $otherUser->id,
        ]))->assertNotFound();
    }

    private function createPartnerCustomRole(string $machineName, string $label): Role
    {
        return $this->createPartnerCustomRoleForPartner($this->partner->id, $machineName, $label);
    }

    private function createPartnerCustomRoleForPartner(int $partnerId, string $machineName, ?string $label = null): Role
    {
        $role = Role::create([
            'name'       => $machineName . '_' . str_replace('.', '', uniqid('', true)),
            'label'      => $label ?? $machineName,
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
}
