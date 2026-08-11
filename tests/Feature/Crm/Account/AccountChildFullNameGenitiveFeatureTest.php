<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * ФИО ученика в родительном падеже в личном кабинете (/account-settings/user).
 *
 * @see /docs/documentation/admin-users.html §2.1.7
 */
final class AccountChildFullNameGenitiveFeatureTest extends CrmTestCase
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

    private function actingAsStudent(array $overrides = []): User
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $student = User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'name'       => 'Кабинет',
            'lastname'   => 'Ученик',
        ], $overrides));

        foreach (['account.user.view', 'account.user.name.update', 'users.full_name_genitive'] as $permission) {
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

    public function test_guest_cannot_update_child_genitive_in_account(): void
    {
        Auth::logout();

        $this->patch(route('account.user.update'), [
            'name'               => 'A',
            'lastname'           => 'B',
            'full_name_genitive' => 'Иванова Ивана',
        ])->assertRedirect();

        $this->patchJson(route('account.user.update'), [
            'name'               => 'A',
            'lastname'           => 'B',
            'full_name_genitive' => 'Иванова Ивана',
        ])->assertUnauthorized();
    }

    public function test_student_can_save_and_clear_child_genitive_in_account(): void
    {
        $student = $this->actingAsStudent(['full_name_genitive' => null]);

        $save = $this->patchJson(route('account.user.update'), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'full_name_genitive' => 'Ученика Кабинета',
        ], $this->jsonHeaders());

        $save->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotSame('', trim((string) $save->getContent()));
        $this->assertSame('Ученика Кабинета', $student->fresh()->full_name_genitive);

        $clear = $this->patchJson(route('account.user.update'), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'full_name_genitive' => '',
        ], $this->jsonHeaders());

        $clear->assertOk();
        $this->assertNotSame('', trim((string) $clear->getContent()));
        $this->assertNull($student->fresh()->full_name_genitive);
    }

    public function test_account_validation_returns_422_when_genitive_too_long(): void
    {
        $student = $this->actingAsStudent(['full_name_genitive' => null]);

        $this->patchJson(route('account.user.update'), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'full_name_genitive' => str_repeat('я', 301),
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_name_genitive']);

        $this->assertNull($student->fresh()->full_name_genitive);
    }

    public function test_student_without_genitive_permission_cannot_change_genitive(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $roleId,
            'full_name_genitive' => 'Исходное Родительное',
        ]);

        foreach (['account.user.view', 'account.user.name.update'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id'    => $this->partner->id,
                'role_id'       => $roleId,
                'permission_id' => $this->permissionId($permission),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        $this->actingAs($student)
            ->patchJson(route('account.user.update'), [
                'name'               => $student->name,
                'lastname'           => $student->lastname,
                'full_name_genitive' => 'Новое Родительное',
            ], $this->jsonHeaders())
            ->assertOk();

        $this->assertSame('Исходное Родительное', $student->fresh()->full_name_genitive);

        $this->actingAs($student)
            ->get(route('account.user.edit'))
            ->assertOk()
            ->assertDontSee('id="full_name_genitive"', false);
    }

    public function test_account_edit_page_shows_genitive_field_and_old_value(): void
    {
        $student = $this->actingAsStudent([
            'full_name_genitive' => 'Показана Ученика ВПадеже',
        ]);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('ФИО ученика в родительном падеже', false)
            ->assertSee('name="full_name_genitive"', false)
            ->assertSee('id="full_name_genitive"', false)
            ->assertSee('maxlength="300"', false)
            ->assertSee('Показана Ученика ВПадеже', false)
            ->getContent();

        $namePos = strpos($html, 'id="name"');
        $genitivePos = strpos($html, 'id="full_name_genitive"');
        $birthdayPos = strpos($html, 'id="birthday"');

        $this->assertNotFalse($namePos);
        $this->assertNotFalse($genitivePos);
        $this->assertNotFalse($birthdayPos);
        $this->assertLessThan($genitivePos, $namePos);
        $this->assertLessThan($birthdayPos, $genitivePos);
    }

    public function test_patch_without_x_requested_with_still_persists_child_genitive_as_json(): void
    {
        $student = $this->actingAsStudent(['full_name_genitive' => null]);

        $response = $this->patch(route('account.user.update'), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'full_name_genitive' => 'JsonLk Ученика',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message']);
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertSame('JsonLk Ученика', $student->fresh()->full_name_genitive);
    }

    public function test_account_edit_page_without_genitive_permission_still_ok_smoke(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');

        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $roleId,
            'full_name_genitive' => 'СкрытоВКабинете',
        ]);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId('account.user.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $page = $this->actingAs($student)->get(route('account.user.edit'));
        $page->assertOk()
            ->assertDontSee('id="full_name_genitive"', false);
        $this->assertNotSame('', trim((string) $page->getContent()));
    }
}
