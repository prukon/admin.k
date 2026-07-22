<?php

namespace Tests\Feature\Crm\Account;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX / AJAX safety-net для правки ФИО родителя в ЛК.
 * AccountController@update всегда отвечает JSON (форма на AJAX) — проверяем непустой контракт
 * и обновление общей карточки parents без X-Requested-With и с ним.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html §4
 */
final class AccountParentNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    private function actingAsStudentWithParent(ParentProfile $parent): User
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $parent->id,
            'name'       => 'Кабинет',
            'lastname'   => 'Ученик',
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('account.user.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('account.user.parent.update'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($student);

        return $student;
    }

    public function test_guest_cannot_patch_account_parent(): void
    {
        Auth::logout();

        $this->patch(route('account.user.update'), [
            'name'             => 'A',
            'lastname'         => 'B',
            'parent_lastname'  => 'X',
            'parent_firstname' => 'Y',
        ])->assertRedirect();

        $this->patchJson(route('account.user.update'), [
            'name'             => 'A',
            'lastname'         => 'B',
            'parent_lastname'  => 'X',
            'parent_firstname' => 'Y',
        ])->assertUnauthorized();
    }

    public function test_patch_without_x_requested_with_returns_json_and_updates_parent_fio_not_empty_200(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ДоNonAjaxLk',
            'firstname'  => 'Родитель',
        ]);

        $student = $this->actingAsStudentWithParent($parent);

        $response = $this->patch(route('account.user.update'), [
            'name'              => $student->name,
            'lastname'          => $student->lastname,
            'parent_lastname'   => 'ПослеNonAjaxLk',
            'parent_firstname'  => 'Родитель',
            'parent_middlename' => 'Кабинет',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message']);

        $this->assertNotSame('', trim((string) $response->getContent()));

        $parent->refresh();
        $this->assertSame('ПослеNonAjaxLk', $parent->lastname);
        $this->assertSame('Кабинет', $parent->middlename);
        $this->assertSame($parent->id, $student->fresh()->parent_id);
    }

    public function test_patch_ajax_json_contract_updates_shared_parent_for_sibling(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ОбщийLk',
            'firstname'  => 'Родитель',
        ]);

        $student = $this->actingAsStudentWithParent($parent);

        $sibling = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $student->role_id,
            'parent_id'  => $parent->id,
            'name'       => 'Брат',
            'lastname'   => 'Sibling',
        ]);

        $response = $this->patchJson(route('account.user.update'), [
            'name'             => $student->name,
            'lastname'         => $student->lastname,
            'parent_lastname'  => 'ОбщийПослеAjax',
            'parent_firstname' => 'Родитель',
        ], $this->jsonHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message']);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertSame('ОбщийПослеAjax', $parent->fresh()->lastname);
        $this->assertSame('ОбщийПослеAjax Родитель', $sibling->fresh()->parent_full_name);
    }

    public function test_patch_ajax_validation_returns_422_for_parent_lastname(): void
    {
        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);
        $student = $this->actingAsStudentWithParent($parent);

        $this->patchJson(route('account.user.update'), [
            'name'             => $student->name,
            'lastname'         => $student->lastname,
            'parent_lastname'  => str_repeat('а', 101),
            'parent_firstname' => 'Ок',
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname']);

        $this->assertNotSame(str_repeat('а', 101), $parent->fresh()->lastname);
    }
}
