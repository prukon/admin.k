<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Изоляция партнёра для справочника parents и CRUD ученика с родителем (админка).
 *
 * @see /docs/documentation/partner-scope-guide.html
 * @see /docs/documentation/admin-users.html
 */
final class AdminUsersParentPartnerIsolationTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function userRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    public function test_parents_search_returns_only_current_partner_parents(): void
    {
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'СвойПартнерРодитель',
            'firstname'  => 'А',
        ]);

        ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'СвойПартнерРодитель',
            'firstname'  => 'Чужой',
        ]);

        $json = $this->getJson(route('admin.users.parents.search', ['q' => 'СвойПартнерРодитель']))
            ->assertOk()
            ->json();

        $texts = collect($json['results'] ?? [])->pluck('text')->all();

        $this->assertContains('СвойПартнерРодитель А', $texts);
        $this->assertNotContains('СвойПартнерРодитель Чужой', $texts);
    }

    public function test_parents_search_by_id_returns_404_for_foreign_partner_parent(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'ForeignOnly',
            'firstname'  => 'Parent',
        ]);

        $json = $this->getJson(route('admin.users.parents.search', ['id' => $foreignParent->id]))
            ->assertOk()
            ->json();

        $this->assertSame([], $json['results'] ?? []);
    }

    public function test_store_rejects_foreign_parent_id(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Ребёнок',
            'lastname'   => 'Тест',
            'role_id'    => $this->userRoleId(),
            'parent_id'  => $foreignParent->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_update_rejects_foreign_parent_id(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->userRoleId(),
        ]);

        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => $student->name,
            'lastname'   => $student->lastname,
            'role_id'    => $student->role_id,
            'parent_id'  => $foreignParent->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_admin_users_data_does_not_find_foreign_partner_by_parent_name(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'ИзолированныйЧужойРодитель',
            'firstname'  => 'X',
        ]);

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->userRoleId(),
            'parent_id'  => $foreignParent->id,
        ]);

        $json = $this->getJson('/admin/users/data?name=ИзолированныйЧужойРодитель')
            ->assertOk()
            ->json();

        $this->assertSame([], $json['data'] ?? []);
    }

    public function test_admin_cannot_edit_foreign_partner_student(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->userRoleId(),
        ]);

        $this->getJson(route('admin.user.edit', $foreignStudent->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(404);
    }

    public function test_admin_store_creates_parent_profile_in_current_partner(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'              => 'Маша',
            'lastname'          => 'Ученица',
            'role_id'           => $this->userRoleId(),
            'parent_lastname'   => 'Созданный',
            'parent_firstname'  => 'Родитель',
            'is_enabled'        => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Ученица')
            ->firstOrFail();

        $this->assertNotNull($student->parent_id);
        $this->assertDatabaseHas('parents', [
            'id'         => $student->parent_id,
            'partner_id' => $this->partner->id,
            'lastname'   => 'Созданный',
            'firstname'  => 'Родитель',
        ]);
    }

    public function test_admin_users_index_and_data_endpoints_are_accessible(): void
    {
        $this->get(route('admin.user1'))->assertOk();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson(route('admin.users.parents.search', ['q' => '']))->assertOk();
    }
}
