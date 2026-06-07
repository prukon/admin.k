<?php

namespace Tests\Feature\Crm\Account;

use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный доступ к личному кабинету (account-settings/user) и блок «Данные родителя».
 *
 * @see /docs/documentation/tests-standards.html
 * @see /docs/documentation/partner-scope-guide.html
 */
final class AccountParentFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_is_redirected_from_account_user_routes(): void
    {
        Auth::logout();

        $this->get(route('account.user.edit'))->assertRedirect(route('login'));

        $this->patchJson(route('account.user.update'), [
            'name'     => 'A',
            'lastname' => 'B',
        ])->assertUnauthorized();

        $this->putJson(route('account.user.password.update'), [
            'password' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_account_user_routes_forbidden_without_account_user_view(): void
    {
        $actor = $this->createUserWithoutPermission('account.user.view', $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('account.user.edit'))
            ->assertStatus(403);

        $this->actingAs($actor)
            ->patchJson(route('account.user.update'), [
                'name'     => $actor->name,
                'lastname' => $actor->lastname,
            ])
            ->assertStatus(403);

        $this->actingAs($actor)
            ->putJson(route('account.user.password.update'), ['password' => 'newpassword1'])
            ->assertStatus(403);

        $this->actingAs($actor)
            ->postJson(route('account.user.avatar.store'), [])
            ->assertStatus(403);

        $this->actingAs($actor)
            ->deleteJson(route('account.user.avatar.destroy'))
            ->assertStatus(403);
    }

    public function test_account_user_endpoints_accessible_with_account_user_view(): void
    {
        $this->actingAs($this->user);

        $this->get(route('account.user.edit'))->assertOk();

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson(route('account.user.password.update'), [
            'password' => 'newpassword99',
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->deleteJson(route('account.user.avatar.destroy'), [], $this->jsonHeaders())
            ->assertOk();
    }

    public function test_account_update_creates_parent_profile_when_none_linked(): void
    {
        $this->actingAs($this->user);

        $this->assertNull($this->user->parent_id);
        $this->assertNull($this->user->parentProfile);

        $this->patchJson(route('account.user.update'), [
            'name'              => $this->user->name,
            'lastname'          => $this->user->lastname,
            'parent_lastname'   => 'Новиков',
            'parent_firstname'  => 'Николай',
            'parent_middlename' => 'Николаевич',
        ], $this->jsonHeaders())
            ->assertOk();

        $this->user->refresh();
        $this->user->load('parentProfile');

        $this->assertNotNull($this->user->parent_id);
        $this->assertSame('Новиков', $this->user->parentProfile?->lastname);
        $this->assertSame('Николай', $this->user->parentProfile?->firstname);
        $this->assertDatabaseHas('parents', [
            'id'         => $this->user->parent_id,
            'partner_id' => $this->partner->id,
            'lastname'   => 'Новиков',
        ]);
    }

    public function test_account_update_validates_parent_field_lengths(): void
    {
        $this->actingAs($this->user);

        $tooLong = str_repeat('а', 101);

        $this->patchJson(route('account.user.update'), [
            'name'             => $this->user->name,
            'lastname'         => $this->user->lastname,
            'parent_lastname'  => $tooLong,
            'parent_firstname' => 'Тест',
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname']);
    }

    public function test_account_update_rejects_parent_profile_from_foreign_partner(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'Чужой',
            'firstname'  => 'Родитель',
        ]);

        $this->user->forceFill(['parent_id' => $foreignParent->id])->save();
        $this->actingAs($this->user);

        $this->patchJson(route('account.user.update'), [
            'name'             => $this->user->name,
            'lastname'         => $this->user->lastname,
            'parent_lastname'  => 'Попытка',
            'parent_firstname' => 'Родитель',
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname']);

        $foreignParent->refresh();
        $this->assertSame('Чужой', $foreignParent->lastname);
    }

    public function test_account_edit_shows_parent_section_readonly_without_parent_update_permission(): void
    {
        $this->revokePermissionFromRole('user', 'account.user.parent.update');

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Только',
            'firstname'  => 'Просмотр',
        ]);
        $this->user->forceFill(['parent_id' => $parent->id])->save();

        $this->actingAs($this->user)
            ->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('Данные родителя', false)
            ->assertSee('Нет прав на изменение данных родителя', false)
            ->assertSee('value="Только"', false);
    }

    public function test_regular_user_ignores_foreign_current_partner_in_session_on_update(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'До',
            'firstname'  => 'Смены',
        ]);
        $this->user->forceFill(['parent_id' => $parent->id])->save();

        $this->actingAs($this->user);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed'      => true,
        ])
            ->patchJson(route('account.user.update'), [
                'name'             => $this->user->name,
                'lastname'         => $this->user->lastname,
                'parent_lastname'  => 'После',
                'parent_firstname' => 'Смены',
            ], $this->jsonHeaders())
            ->assertOk();

        $parent->refresh();
        $this->assertSame('После', $parent->lastname);
        $this->assertSame($this->partner->id, (int) $parent->partner_id);
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function revokePermissionFromRole(string $roleName, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->roleId($roleName))
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }
}
