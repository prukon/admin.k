<?php

namespace Tests\Feature\Crm\Account;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * ФИО родителя в родительном падеже в личном кабинете (/account-settings/user).
 *
 * @see /docs/documentation/parents-and-family-cabinet.html §4
 */
final class AccountParentFullNameGenitiveFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return [
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function actingAsStudentWithParent(?ParentProfile $parent = null): User
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent?->id,
            'name'       => 'Кабинет',
            'lastname'   => 'Ученик',
        ]);

        foreach (['account.user.view', 'account.user.parent.update'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id'    => $this->partner->id,
                'role_id'       => $roleId,
                'permission_id' => $this->permissionId($permission),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        $this->actingAs($student);

        return $student;
    }

    public function test_guest_cannot_update_parent_genitive_in_account(): void
    {
        Auth::logout();

        $this->patch(route('account.user.update'), [
            'name'                      => 'A',
            'lastname'                  => 'B',
            'parent_lastname'           => 'X',
            'parent_firstname'          => 'Y',
            'parent_full_name_genitive' => 'Иванова Ивана',
        ])->assertRedirect();

        $this->patchJson(route('account.user.update'), [
            'name'                      => 'A',
            'lastname'                  => 'B',
            'parent_lastname'           => 'X',
            'parent_firstname'          => 'Y',
            'parent_full_name_genitive' => 'Иванова Ивана',
        ])->assertUnauthorized();
    }

    public function test_student_can_save_and_clear_parent_genitive_in_account(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Иванов',
            'firstname'          => 'Иван',
            'middlename'         => 'Иванович',
            'full_name_genitive' => null,
        ]);

        $student = $this->actingAsStudentWithParent($parent);

        $this->patchJson(route('account.user.update'), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'parent_lastname'           => 'Иванов',
            'parent_firstname'          => 'Иван',
            'parent_middlename'         => 'Иванович',
            'parent_full_name_genitive' => 'Иванова Ивана Ивановича',
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('Иванова Ивана Ивановича', $parent->fresh()->full_name_genitive);

        $this->patchJson(route('account.user.update'), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'parent_lastname'           => 'Иванов',
            'parent_firstname'          => 'Иван',
            'parent_middlename'         => 'Иванович',
            'parent_full_name_genitive' => '',
        ], $this->jsonHeaders())
            ->assertOk();

        $this->assertNull($parent->fresh()->full_name_genitive);
    }

    public function test_account_rejects_genitive_alone_without_parent_names(): void
    {
        $student = $this->actingAsStudentWithParent();

        $this->patchJson(route('account.user.update'), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'parent_full_name_genitive' => 'Иванова Ивана Ивановича',
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname', 'parent_firstname']);

        $student->refresh();
        $this->assertNull($student->parent_id);
    }

    public function test_account_validation_returns_422_when_genitive_too_long(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Длинный',
            'firstname'          => 'Родитель',
            'full_name_genitive' => null,
        ]);
        $student = $this->actingAsStudentWithParent($parent);

        $this->patchJson(route('account.user.update'), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'parent_lastname'           => 'Длинный',
            'parent_firstname'          => 'Родитель',
            'parent_full_name_genitive' => str_repeat('я', 301),
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_full_name_genitive']);

        $this->assertNull($parent->fresh()->full_name_genitive);
    }

    public function test_sibling_sees_genitive_updated_from_account(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Общий',
            'firstname'          => 'Родитель',
            'full_name_genitive' => 'Старое',
        ]);

        $student = $this->actingAsStudentWithParent($parent);

        $sibling = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $student->role_id,
            'parent_id'  => $parent->id,
        ]);

        $this->patchJson(route('account.user.update'), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'parent_lastname'           => 'Общий',
            'parent_firstname'          => 'Родитель',
            'parent_full_name_genitive' => 'Общего Родителя Родителевича',
        ], $this->jsonHeaders())
            ->assertOk();

        $sibling->load('parentProfile');
        $this->assertSame('Общего Родителя Родителевича', $sibling->parentProfile?->full_name_genitive);
    }

    public function test_student_without_parent_update_permission_cannot_change_genitive(): void
    {
        $this->revokePermissionFromRole('user', 'account.user.parent.update');

        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Запрет',
            'firstname'          => 'Родитель',
            'full_name_genitive' => 'Исходное Родительное',
        ]);

        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('account.user.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($student)
            ->patchJson(route('account.user.update'), [
                'name'                      => $student->name,
                'lastname'                  => $student->lastname,
                'parent_lastname'           => 'Новое',
                'parent_firstname'          => 'Имя',
                'parent_full_name_genitive' => 'Новое Родительное',
            ], $this->jsonHeaders())
            ->assertOk();

        $parent->refresh();
        $this->assertSame('Запрет', $parent->lastname);
        $this->assertSame('Исходное Родительное', $parent->full_name_genitive);
    }

    public function test_account_edit_page_shows_genitive_field_and_old_value(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Показан',
            'firstname'          => 'Родитель',
            'full_name_genitive' => 'Показана Родителя ВПадеже',
        ]);

        $this->actingAsStudentWithParent($parent);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('Данные родителя', false)
            ->assertSee('ФИО родителя в родительном падеже', false)
            ->assertSee('name="parent_full_name_genitive"', false)
            ->assertSee('id="parent_full_name_genitive"', false)
            ->assertSee('Показана Родителя ВПадеже', false)
            ->assertSee('maxlength="300"', false)
            ->getContent();

        $middlenamePos = strpos($html, 'id="parent_middlename"');
        $genitivePos = strpos($html, 'id="parent_full_name_genitive"');
        $passportPos = strpos($html, 'id="parent_passport"');

        $this->assertNotFalse($middlenamePos);
        $this->assertNotFalse($genitivePos);
        $this->assertNotFalse($passportPos);
        $this->assertLessThan($genitivePos, $middlenamePos);
        $this->assertLessThan($passportPos, $genitivePos);
    }

    public function test_account_edit_page_disables_genitive_without_parent_update_permission(): void
    {
        $this->revokePermissionFromRole('user', 'account.user.parent.update');

        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'full_name_genitive' => 'ТолькоЧтение Родителя',
        ]);

        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('account.user.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $html = $this->actingAs($student)
            ->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('id="parent_full_name_genitive"', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="parent_full_name_genitive"[^>]*\bdisabled\b/',
            $html
        );
    }

    public function test_patch_without_x_requested_with_still_persists_genitive_as_json(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'JsonLk',
            'firstname'  => 'Родитель',
        ]);
        $student = $this->actingAsStudentWithParent($parent);

        $response = $this->patch(route('account.user.update'), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'parent_lastname'           => 'JsonLk',
            'parent_firstname'          => 'Родитель',
            'parent_full_name_genitive' => 'JsonLk Родителя',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message']);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertSame('JsonLk Родителя', $parent->fresh()->full_name_genitive);
    }

    private function revokePermissionFromRole(string $roleName, string $permissionName): void
    {
        $roleId = $this->roleId($roleName);
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $permId)
            ->delete();
    }
}
