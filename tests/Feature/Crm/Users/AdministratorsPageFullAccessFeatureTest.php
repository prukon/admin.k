<?php

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * /admin/administrators: полный доступ (200) и запреты (403/404).
 */
final class AdministratorsPageFullAccessFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $this->adminUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->adminRoleId(),
            'lastname'   => 'Админов',
            'name'       => 'Полный',
            'email'      => 'full-access-admin-' . uniqid('', true) . '@example.test',
        ]);
    }

    public function test_administrators_index_page_returns_200_with_required_permissions(): void
    {
        $this->get(route('admin.administrators.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'administrators')
            ->assertViewHas('role')
            ->assertSee('id="role-staff-table"', false)
            ->assertSee('roleStaffCreateModal', false)
            ->assertSee('roleStaffFiltersCollapse', false)
            ->assertSee('roleStaffColumnsDropdown', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_all_administrators_page_endpoints_return_200(): void
    {
        $tableKey = 'role_staff_admin';

        $this->get(route('admin.administrators.index'))->assertOk();

        $this->getJson(route('admin.administrators.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.administrators.columns-settings.get') . '?table_key=' . $tableKey)
            ->assertOk();

        $this->postJson(route('admin.administrators.columns-settings.save') . '?table_key=' . $tableKey, [
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

        $this->getJson(route('admin.administrators.show', ['user' => $this->adminUser->id]))
            ->assertOk()
            ->assertJsonPath('id', $this->adminUser->id);

        $createdEmail = 'full-access-new-admin-' . uniqid('', true) . '@example.test';

        $this->postJson(route('admin.administrators.store'), [
            'lastname'   => 'Новый',
            'name'       => 'Админ',
            'email'      => $createdEmail,
            'password'   => 'password123',
            'is_enabled' => 1,
        ])->assertOk();

        $created = User::query()
            ->where('email', $createdEmail)
            ->where('role_id', $this->adminRoleId())
            ->firstOrFail();

        $this->putJson(route('admin.administrators.update', ['user' => $this->adminUser->id]), [
            'lastname'   => 'Обновлён',
            'name'       => 'Админ',
            'email'      => $this->adminUser->email,
            'is_enabled' => 1,
        ])->assertOk();

        $this->deleteJson(route('admin.administrators.destroy', ['user' => $created->id]))
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $created->id]);
    }

    public function test_administrators_data_lists_only_admin_role_users(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'student-not-admin-list-' . uniqid('', true) . '@example.test',
        ]);

        $json = $this->getJson('/admin/administrators/data?draw=1&length=100')
            ->assertOk()
            ->json();

        $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertTrue($ids->contains($this->adminUser->id));
        $this->assertFalse($ids->contains($student->id));
    }

    public function test_administrators_data_with_filters_returns_200(): void
    {
        $query = http_build_query([
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'name'   => 'Полный',
            'status' => 'active',
            'order'  => [['column' => 2, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'avatar'],
                ['name' => 'full_name'],
                ['name' => 'email'],
                ['name' => 'phone'],
                ['name' => 'is_enabled'],
                ['name' => 'actions'],
            ],
        ]);

        $json = $this->get('/admin/administrators/data?' . $query)
            ->assertOk()
            ->json();

        $row = collect($json['data'] ?? [])->firstWhere('id', $this->adminUser->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('full_name', $row);
        $this->assertArrayHasKey('status_label', $row);
    }

    public function test_administrators_index_returns_403_without_users_view(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->grantRoleUpdate($actor);

        $this->get(route('admin.administrators.index'))->assertStatus(403);
    }

    public function test_administrators_index_returns_403_without_users_role_update(): void
    {
        $actor = $this->createUserWithoutPermission('users.role.update', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $this->get(route('admin.administrators.index'))->assertStatus(403);
    }

    public function test_all_administrators_endpoints_return_403_without_staff_permissions(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $tableKey = 'role_staff_admin';
        $userId = $this->adminUser->id;

        $this->get(route('admin.administrators.index'))->assertForbidden();
        $this->getJson('/admin/administrators/data?draw=1')->assertForbidden();
        $this->getJson(route('admin.administrators.columns-settings.get') . '?table_key=' . $tableKey)->assertForbidden();
        $this->postJson(route('admin.administrators.columns-settings.save') . '?table_key=' . $tableKey, [
            'columns' => ['full_name' => true],
        ])->assertForbidden();
        $this->getJson(route('admin.administrators.show', ['user' => $userId]))->assertForbidden();
        $this->postJson(route('admin.administrators.store'), [
            'name'     => 'Запрет',
            'lastname' => 'Админ',
        ])->assertForbidden();
        $this->putJson(route('admin.administrators.update', ['user' => $userId]), [
            'name'     => 'Запрет',
            'lastname' => 'Админ',
        ])->assertForbidden();
        $this->deleteJson(route('admin.administrators.destroy', ['user' => $userId]))->assertForbidden();
    }

    public function test_guest_cannot_access_administrators_page_or_endpoints(): void
    {
        Auth::logout();

        $this->get(route('admin.administrators.index'))->assertStatus(302);
        $this->get('/admin/administrators/data?draw=1')->assertStatus(302);
    }

    public function test_foreign_partner_admin_user_is_not_accessible(): void
    {
        $foreignAdmin = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->adminRoleId(),
            'email'      => 'foreign-admin-' . uniqid('', true) . '@example.test',
        ]);

        $this->getJson(route('admin.administrators.show', ['user' => $foreignAdmin->id]))
            ->assertNotFound();

        $this->putJson(route('admin.administrators.update', ['user' => $foreignAdmin->id]), [
            'name'       => 'Чужой',
            'lastname'   => 'Админ',
            'is_enabled' => 1,
        ])->assertNotFound();

        $this->deleteJson(route('admin.administrators.destroy', ['user' => $foreignAdmin->id]))
            ->assertNotFound();
    }

    public function test_student_user_returns_404_on_administrators_routes(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'student-wrong-route-' . uniqid('', true) . '@example.test',
        ]);

        $this->getJson(route('admin.administrators.show', ['user' => $student->id]))
            ->assertNotFound();

        $this->putJson(route('admin.administrators.update', ['user' => $student->id]), [
            'name'       => $student->name,
            'lastname'   => $student->lastname,
            'is_enabled' => 1,
        ])->assertNotFound();
    }
}
