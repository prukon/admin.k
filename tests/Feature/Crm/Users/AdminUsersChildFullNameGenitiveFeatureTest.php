<?php

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * ФИО ученика в родительном падеже (users.full_name_genitive) в админке /admin/users.
 *
 * @see /docs/documentation/admin-users.html §2.1.7
 */
final class AdminUsersChildFullNameGenitiveFeatureTest extends CrmTestCase
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
        $this->grantPermission($this->user, 'users.name.update');
        $this->grantPermission($this->user, 'users.full_name_genitive');
    }

    /** @return array<string, string> */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function revokeChildGenitivePermission(): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->where('permission_id', $this->permissionId('users.full_name_genitive'))
            ->delete();
    }

    public function test_guest_cannot_save_or_read_child_genitive(): void
    {
        Auth::logout();

        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Иванова Ивана Ивановича',
        ]);

        $this->get(route('admin.user1'))->assertRedirect();
        $this->getJson(route('admin.user.edit', $student->id))->assertUnauthorized();
        $this->postJson(route('admin.user.store'), [
            'name'               => 'A',
            'lastname'           => 'B',
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Петрова Петра Петровича',
        ])->assertUnauthorized();
        $this->patchJson(route('admin.user.update', $student->id), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'role_id'            => $student->role_id,
            'full_name_genitive' => 'Сидорова Сидора Сидоровича',
        ])->assertUnauthorized();
    }

    public function test_manager_without_users_view_gets_403_on_child_genitive_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
        ]);

        $this->actingAs($actor)->withSession($session)
            ->get(route('admin.user1'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('admin.user.edit', $student->id))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('admin.user.store'), [
                'name'               => 'X',
                'lastname'           => 'Y',
                'role_id'            => $this->studentRoleId(),
                'full_name_genitive' => 'Тестова Теста Тестовича',
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->patchJson(route('admin.user.update', $student->id), [
                'name'               => $student->name,
                'lastname'           => $student->lastname,
                'role_id'            => $student->role_id,
                'full_name_genitive' => 'Тестова Теста Тестовича',
            ])
            ->assertForbidden();
    }

    public function test_store_ajax_creates_student_with_genitive_and_returns_user(): void
    {
        $response = $this->postJson(route('admin.user.store'), [
            'name'               => 'Ребёнок',
            'lastname'           => 'ChildGenAjax',
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'ChildGenAjax Ребёнка',
            'is_enabled'         => 1,
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id']]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $student = User::query()->findOrFail((int) $response->json('user.id'));
        $this->assertSame('ChildGenAjax Ребёнка', $student->full_name_genitive);
    }

    public function test_store_ajax_validation_returns_422_when_genitive_too_long(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'               => 'Fail',
            'lastname'           => 'ChildGen',
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => str_repeat('а', 301),
            'is_enabled'         => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_name_genitive']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'ChildGen',
            'name'       => 'Fail',
        ]);
    }

    public function test_update_ajax_updates_and_clears_genitive(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Старое Родительное',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'role_id'            => $student->role_id,
            'full_name_genitive' => 'Новое Родительное Ученика',
            'is_enabled'         => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь успешно обновлён');

        $this->assertSame('Новое Родительное Ученика', $student->fresh()->full_name_genitive);

        $clear = $this->patchJson(route('admin.user.update', $student->id), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'role_id'            => $student->role_id,
            'full_name_genitive' => '',
            'is_enabled'         => 1,
        ], $this->ajaxHeaders());

        $clear->assertOk();
        $this->assertNotSame('', trim((string) $clear->getContent()));
        $this->assertNull($student->fresh()->full_name_genitive);
    }

    public function test_update_ajax_validation_returns_422_when_genitive_too_long(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Было',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'role_id'            => $student->role_id,
            'full_name_genitive' => str_repeat('ж', 301),
            'is_enabled'         => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_name_genitive']);

        $this->assertSame('Было', $student->fresh()->full_name_genitive);
    }

    public function test_edit_json_includes_full_name_genitive(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Редактирова Ученика Учениковича',
        ]);

        $response = $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('user.full_name_genitive', 'Редактирова Ученика Учениковича');

        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_edit_json_returns_null_full_name_genitive_when_not_set(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => null,
        ]);

        $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('user.full_name_genitive', null);
    }

    public function test_store_non_ajax_redirects_and_persists_genitive(): void
    {
        $this->post(route('admin.user.store'), [
            'name'               => 'NonAjax',
            'lastname'           => 'ChildGenSave',
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'ChildGenSave NonAjax',
            'is_enabled'         => 1,
        ])->assertRedirect(route('admin.user1'));

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'ChildGenSave')
            ->first();

        $this->assertNotNull($student);
        $this->assertSame('ChildGenSave NonAjax', $student->full_name_genitive);
    }

    public function test_update_non_ajax_redirects_and_updates_genitive(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Старое',
        ]);

        $this->patch(route('admin.user.update', $student->id), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'role_id'            => $student->role_id,
            'full_name_genitive' => 'ПослеNonAjax Ученика',
            'is_enabled'         => 1,
        ])->assertRedirect(route('admin.user1'));

        $this->assertSame('ПослеNonAjax Ученика', $student->fresh()->full_name_genitive);
    }

    public function test_non_ajax_validation_failure_for_genitive_redirects_with_field_error(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'name'               => 'Fail',
                'lastname'           => 'NonAjaxChildGen',
                'role_id'            => $this->studentRoleId(),
                'full_name_genitive' => str_repeat('б', 301),
                'is_enabled'         => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['full_name_genitive']);
    }

    public function test_update_non_ajax_validation_failure_for_genitive_redirects_with_field_error(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Оставить',
        ]);

        $this->from(route('admin.user1'))
            ->patch(route('admin.user.update', $student->id), [
                'name'               => $student->name,
                'lastname'           => $student->lastname,
                'role_id'            => $student->role_id,
                'full_name_genitive' => str_repeat('в', 301),
                'is_enabled'         => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['full_name_genitive']);

        $this->assertSame('Оставить', $student->fresh()->full_name_genitive);
    }

    public function test_users_page_renders_child_genitive_field_after_lastname_before_birthday(): void
    {
        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('ФИО ученика в родительном падеже', false)
            ->assertSee('name="full_name_genitive"', false)
            ->assertSee('id="create-full-name-genitive"', false)
            ->assertSee('id="edit-full-name-genitive"', false)
            ->assertSee('maxlength="300"', false)
            ->getContent();

        $createLastnamePos = strpos($html, 'id="create-lastname"');
        $createGenitivePos = strpos($html, 'id="create-full-name-genitive"');
        $createBirthdayPos = strpos($html, 'id="create-birthday"');

        $this->assertNotFalse($createLastnamePos);
        $this->assertNotFalse($createGenitivePos);
        $this->assertNotFalse($createBirthdayPos);
        $this->assertLessThan($createGenitivePos, $createLastnamePos);
        $this->assertLessThan($createBirthdayPos, $createGenitivePos);

        $editLastnamePos = strpos($html, 'id="edit-lastname"');
        $editGenitivePos = strpos($html, 'id="edit-full-name-genitive"');
        $editBirthdayPos = strpos($html, 'id="edit-birthday"');

        $this->assertNotFalse($editLastnamePos);
        $this->assertNotFalse($editGenitivePos);
        $this->assertNotFalse($editBirthdayPos);
        $this->assertLessThan($editGenitivePos, $editLastnamePos);
        $this->assertLessThan($editBirthdayPos, $editGenitivePos);
    }

    public function test_create_user_modal_has_empty_genitive_by_default(): void
    {
        $html = $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="create-full-name-genitive"[^>]*value=""/',
            $html
        );
    }

    public function test_without_permission_field_is_hidden_and_value_not_saved_on_update(): void
    {
        $this->revokeChildGenitivePermission();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertDontSee('id="create-full-name-genitive"', false)
            ->assertDontSee('id="edit-full-name-genitive"', false);

        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Исходное',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'role_id'            => $student->role_id,
            'full_name_genitive' => 'Попытка изменить',
            'is_enabled'         => 1,
        ], $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame('Исходное', $student->fresh()->full_name_genitive);
    }

    public function test_store_without_permission_ignores_genitive(): void
    {
        $this->revokeChildGenitivePermission();

        $response = $this->postJson(route('admin.user.store'), [
            'name'               => 'БезПрава',
            'lastname'           => 'GenIgnore',
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Не Должно Сохраниться',
            'is_enabled'         => 1,
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id']]);

        $student = User::query()->findOrFail((int) $response->json('user.id'));
        $this->assertNull($student->full_name_genitive);
    }

    public function test_foreign_partner_student_genitive_is_not_accessible(): void
    {
        $foreign = User::factory()->create([
            'partner_id'         => $this->foreignPartner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Чужого Ученика',
        ]);

        $this->getJson(route('admin.user.edit', $foreign->id), $this->ajaxHeaders())
            ->assertNotFound();

        $this->patchJson(route('admin.user.update', $foreign->id), [
            'name'               => $foreign->name,
            'lastname'           => $foreign->lastname,
            'role_id'            => $foreign->role_id,
            'full_name_genitive' => 'Взлом',
            'is_enabled'         => 1,
        ], $this->ajaxHeaders())
            ->assertNotFound();

        $this->assertSame('Чужого Ученика', $foreign->fresh()->full_name_genitive);
    }

    public function test_authorized_child_genitive_endpoints_ok_smoke(): void
    {
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Смок Ученика',
        ]);

        $page = $this->get(route('admin.user1'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="create-full-name-genitive"', false);

        $edit = $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders());
        $edit->assertOk()
            ->assertJsonPath('user.full_name_genitive', 'Смок Ученика');
        $this->assertNotSame('', trim((string) $edit->getContent()));

        $update = $this->patchJson(route('admin.user.update', $student->id), [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'role_id'            => $student->role_id,
            'full_name_genitive' => 'Смок Обновлён',
            'is_enabled'         => 1,
        ], $this->ajaxHeaders());
        $update->assertOk();
        $this->assertNotSame('', trim((string) $update->getContent()));
        $this->assertSame('Смок Обновлён', $student->fresh()->full_name_genitive);
    }

    public function test_authorized_without_genitive_permission_page_ok_and_field_absent(): void
    {
        $this->revokeChildGenitivePermission();

        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'full_name_genitive' => 'Скрытое',
        ]);

        $page = $this->get(route('admin.user1'));
        $page->assertOk()
            ->assertDontSee('id="create-full-name-genitive"', false)
            ->assertDontSee('id="edit-full-name-genitive"', false);
        $this->assertNotSame('', trim((string) $page->getContent()));

        $edit = $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders());
        $edit->assertOk();
        $this->assertNotSame('', trim((string) $edit->getContent()));
        $this->assertSame('Скрытое', $edit->json('user.full_name_genitive'));
    }
}
