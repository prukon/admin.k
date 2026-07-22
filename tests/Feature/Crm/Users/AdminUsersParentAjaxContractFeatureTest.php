<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * AJAX-контракт store/update ученика с родителем из справочника:
 * postJson/patchJson + X-Requested-With → JSON (message, user), 200/422, не пустой 200.
 */
final class AdminUsersParentAjaxContractFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantUsersView($this->user);
    }

    /** @return array<string, string> */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_store_ajax_json_contract_with_new_parent_fio(): void
    {
        $response = $this->postJson(route('admin.user.store'), [
            'name'             => 'Ajax',
            'lastname'         => 'Контракт',
            'role_id'          => $this->studentRoleId(),
            'parent_lastname'  => 'AjaxРодитель',
            'parent_firstname' => 'Новый',
            'is_enabled'       => 1,
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'is_enabled'],
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertIsInt($response->json('user.id'));
        $this->assertGreaterThan(0, (int) $response->json('user.id'));

        $student = User::query()->findOrFail((int) $response->json('user.id'));
        $this->assertNotNull($student->parent_id);
        $this->assertDatabaseHas('parents', [
            'id'       => $student->parent_id,
            'lastname' => 'AjaxРодитель',
        ]);
    }

    public function test_store_ajax_with_parent_id_updates_fio_and_returns_user(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ДоAjax',
            'firstname'  => 'Родитель',
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'             => 'Второй',
            'lastname'         => 'AjaxLink',
            'role_id'          => $this->studentRoleId(),
            'parent_id'        => $parent->id,
            'parent_lastname'  => 'ПослеAjax',
            'parent_firstname' => 'Родитель',
            'is_enabled'       => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id']]);

        $this->assertSame($parent->id, User::query()->findOrFail((int) $response->json('user.id'))->parent_id);
        $this->assertSame('ПослеAjax', $parent->fresh()->lastname);
    }

    public function test_store_ajax_validation_returns_422_with_parent_field_errors(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'             => 'Fail',
            'lastname'         => 'Ajax',
            'role_id'          => $this->studentRoleId(),
            'parent_lastname'  => str_repeat('а', 101),
            'parent_firstname' => 'X',
            'is_enabled'       => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname']);
    }

    public function test_update_ajax_json_contract_directory_fio(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'До',
            'firstname'  => 'Update',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Ученик',
            'lastname'   => 'AjaxUpdate',
        ]);

        $response = $this->patchJson(route('admin.user.update', $student->id), [
            'name'             => $student->name,
            'lastname'         => $student->lastname,
            'role_id'          => $student->role_id,
            'parent_id'        => $parent->id,
            'parent_lastname'  => 'ПослеAjaxUpdate',
            'parent_firstname' => 'Update',
            'parent_middlename'=> 'Контракт',
            'is_enabled'       => 1,
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonPath('message', 'Пользователь успешно обновлён')
            ->assertJsonStructure(['message']);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertSame('ПослеAjaxUpdate', $parent->fresh()->lastname);
        $this->assertSame('Контракт', $parent->fresh()->middlename);
    }

    public function test_update_ajax_validation_returns_422_for_parent_fields(): void
    {
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'             => $student->name,
            'lastname'         => $student->lastname,
            'role_id'          => $student->role_id,
            'parent_id'        => $parent->id,
            'parent_lastname'  => str_repeat('я', 101),
            'parent_firstname' => 'Ок',
            'is_enabled'       => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname']);
    }

    public function test_edit_and_parents_search_ajax_json_not_empty(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПоискAjax',
            'firstname'  => 'Родитель',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $edit = $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'parent_id', 'parent_lastname', 'parent_firstname']]);

        $this->assertNotSame('', trim((string) $edit->getContent()));
        $this->assertSame($parent->id, (int) $edit->json('user.parent_id'));

        $search = $this->getJson(route('admin.users.parents.search', ['q' => 'ПоискAjax']), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['results'])
            ->assertJsonFragment(['text' => 'ПоискAjax Родитель']);

        $this->assertNotSame('', trim((string) $search->getContent()));
    }
}
