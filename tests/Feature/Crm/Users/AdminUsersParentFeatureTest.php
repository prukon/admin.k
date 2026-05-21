<?php

namespace Tests\Feature\Crm\Users;

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
        $student = User::factory()->create([
            'partner_id'        => $this->partner->id,
            'lastname'          => 'Учеников',
            'name'              => 'Пётр',
            'parent_lastname'   => 'УникальныйРодитель',
            'parent_firstname'  => 'Сергей',
            'parent_middlename' => 'Петрович',
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
        $student = User::factory()->create([
            'partner_id'       => $this->partner->id,
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_middlename'=> null,
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
