<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class AdminUsersParentFeatureTest extends CrmTestCase
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

    private function defaultRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->firstOrFail()->id;
    }

    public function test_data_returns_parent_column_and_supports_search(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'УникальныйРодитель',
            'firstname'  => 'Сергей',
            'middlename' => 'Петрович',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Учеников',
            'name'       => 'Пётр',
            'parent_id'  => $parent->id,
        ]);

        $json = $this->getJson('/admin/users/data?id=' . $student->id)->json();
        $row = collect($json['data'])->firstWhere('id', $student->id);

        $this->assertNotNull($row);
        $this->assertSame('УникальныйРодитель Сергей Петрович', $row['parent']);

        $search = $this->getJson('/admin/users/data?name=УникальныйРодитель')->json();
        $ids = collect($search['data'])->pluck('id')->all();

        $this->assertContains($student->id, $ids);
    }

    public function test_edit_json_includes_parent_fields(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
            'middlename' => null,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => $parent->id,
        ]);

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.parent_lastname', 'Иванов')
            ->assertJsonPath('user.parent_firstname', 'Иван')
            ->assertJsonPath('user.parent_middlename', null);
    }

    public function test_store_validation_rejects_too_long_parent_fields(): void
    {
        $roleId = $this->defaultRoleId();
        $tooLong = str_repeat('а', 101);

        $response = $this->postJson(route('admin.user.store'), [
            'name'             => 'Тест',
            'lastname'         => 'Тестов',
            'role_id'          => $roleId,
            'parent_lastname'  => $tooLong,
            'is_enabled'       => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname']);
    }

    public function test_data_shows_parent_from_parent_profile(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Профильный',
            'firstname'  => 'Родитель',
            'middlename' => 'Тестович',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $json = $this->getJson('/admin/users/data?id=' . $student->id)->json();
        $row = collect($json['data'])->firstWhere('id', $student->id);

        $this->assertSame('Профильный Родитель Тестович', $row['parent']);
    }

    public function test_parents_search_returns_partner_parents(): void
    {
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'УникальныйПоиск',
            'firstname'  => 'Род',
        ]);

        $this->getJson('/admin/users/parents/search?q=УникальныйПоиск')
            ->assertOk()
            ->assertJsonFragment(['text' => 'УникальныйПоиск Род']);
    }

    public function test_store_links_existing_parent_id(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Старый',
            'firstname'  => 'Родитель',
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Второй',
            'lastname'   => 'Ребёнок',
            'role_id'    => $this->defaultRoleId(),
            'parent_id'  => $parent->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $child = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Ребёнок')
            ->firstOrFail();

        $this->assertSame($parent->id, $child->parent_id);
        $child->load('parentProfile');
        $this->assertSame('Старый', $child->parentProfile?->lastname);
    }

    public function test_update_clears_parent_when_admin_form_sends_empty_parent_block(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Отвязка',
            'firstname'  => 'Родитель',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->defaultRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Ученик',
            'lastname'   => 'Тестов',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'              => $student->name,
            'lastname'          => $student->lastname,
            'role_id'           => $student->role_id,
            'is_enabled'        => 1,
            'parent_lastname'   => '',
            'parent_firstname'  => '',
            'parent_middlename' => '',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $this->assertNull($student->fresh()->parent_id);
    }

    public function test_columns_settings_accepts_parent_key(): void
    {
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'parent' => false,
                'name'   => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonPath('parent', false);
    }
}
