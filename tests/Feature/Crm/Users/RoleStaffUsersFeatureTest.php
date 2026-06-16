<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Role staff users: валидация, store/update ошибки, smoke CRUD (дополняет full-access тесты).
 */
final class RoleStaffUsersFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_administrators_page_requires_role_update_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.role.update', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $this->get(route('admin.administrators.index'))->assertStatus(403);
    }

    public function test_administrators_store_validates_required_fields(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $this->postJson(route('admin.administrators.store'), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'lastname']);
    }

    public function test_administrators_store_rejects_duplicate_email(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $email = 'dup-admin-' . uniqid('', true) . '@example.test';

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->adminRoleId(),
            'email'      => $email,
        ]);

        $this->postJson(route('admin.administrators.store'), [
            'name'       => 'Дубль',
            'lastname'   => 'Админ',
            'email'      => $email,
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_administrators_crud_for_admin_role_user(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $adminRoleId = $this->adminRoleId();

        $this->get(route('admin.administrators.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'administrators')
            ->assertSee('>Администраторы</a>', false);

        $this->postJson(route('admin.administrators.store'), [
            'name'       => 'Новый',
            'lastname'   => 'Админ',
            'email'      => 'new-admin-' . uniqid('', true) . '@example.test',
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $created = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('email', 'like', 'new-admin-%@example.test')
            ->where('role_id', $adminRoleId)
            ->first();

        $this->assertNotNull($created);

        $this->getJson(route('admin.administrators.show', ['user' => $created->id]))
            ->assertOk()
            ->assertJsonPath('name', 'Новый');

        $this->putJson(route('admin.administrators.update', ['user' => $created->id]), [
            'name'       => 'Обновлён',
            'lastname'   => 'Админ',
            'is_enabled' => 1,
        ])->assertOk();

        $this->deleteJson(route('admin.administrators.destroy', ['user' => $created->id]))
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $created->id]);
    }

    public function test_users_data_lists_only_students(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'student-only-list-' . uniqid('', true) . '@example.test',
        ]);

        $adminUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->adminRoleId(),
            'email'      => 'admin-not-in-list-' . uniqid('', true) . '@example.test',
        ]);

        $json = $this->get('/admin/users/data?draw=1&length=100')
            ->assertOk()
            ->json();

        $ids = collect($json['data'] ?? [])->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertTrue($ids->contains($student->id));
        $this->assertFalse($ids->contains($adminUser->id));
    }

    public function test_trainer_role_is_visible_in_database(): void
    {
        $trainer = Role::query()->where('name', 'trainer')->firstOrFail();
        $this->assertTrue((bool) $trainer->is_visible);
    }

    public function test_columns_settings_rejects_invalid_table_key(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $this->getJson(route('admin.administrators.columns-settings.get') . '?table_key=invalid_key')
            ->assertStatus(422);
    }
}
