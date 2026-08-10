<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Поле «Адрес проживания» ученика (users.address): HTTP/AJAX/non-AJAX, UI, JS-контракт заполнения.
 *
 * @see /docs/documentation/admin-users.html §4.5.1
 */
final class UserAddressFieldFeatureTest extends CrmTestCase
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
    }

    public function test_users_table_has_address_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'address'));
    }

    public function test_guest_cannot_create_or_update_student_address(): void
    {
        Auth::logout();

        $this->postJson('/admin/users', [
            'name'     => 'Гость',
            'lastname' => 'Адрес',
            'role_id'  => $this->studentRoleId(),
            'address'  => 'ул. Гостевая, 1',
        ])->assertUnauthorized();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'address'    => 'старый',
        ]);

        $this->patchJson('/admin/users/' . $student->id, [
            'name'     => $student->name,
            'lastname' => $student->lastname,
            'address'  => 'новый',
        ])->assertUnauthorized();

        $this->assertSame('старый', $student->fresh()->address);
    }

    public function test_manager_without_users_view_gets_403_on_address_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->postJson('/admin/users', [
                'name'     => 'Нет',
                'lastname' => 'Прав',
                'role_id'  => $this->studentRoleId(),
                'address'  => 'ул. Запрет, 1',
            ])
            ->assertForbidden();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'address'    => 'сохранить',
        ]);

        $this->actingAs($actor)->withSession($session)
            ->patchJson('/admin/users/' . $student->id, [
                'name'     => $student->name,
                'lastname' => $student->lastname,
                'address'  => 'изменён',
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson('/admin/users/' . $student->id . '/edit')
            ->assertForbidden();

        $this->assertSame('сохранить', $student->fresh()->address);
    }

    public function test_ajax_store_persists_address_and_returns_json_contract(): void
    {
        $response = $this->postJson('/admin/users', [
            'name'               => 'Иван',
            'lastname'           => 'Адресный',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'address'            => 'г. Казань, ул. Тестовая, д. 1',
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id', 'name']])
            ->assertJsonPath('user.name', 'Иван');

        $user = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Адресный')
            ->first();

        $this->assertNotNull($user);
        $this->assertSame('г. Казань, ул. Тестовая, д. 1', $user->address);
        $this->assertSame($user->id, (int) $response->json('user.id'));
    }

    public function test_ajax_update_persists_address_and_returns_message(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Иван',
            'lastname'   => 'Адресный',
            'address'    => 'старый адрес',
        ]);

        $this->patchJson('/admin/users/' . $user->id, [
            'name'     => 'Иван',
            'lastname' => 'Адресный',
            'address'  => 'г. Казань, ул. Новая, д. 5',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь успешно обновлён');

        $this->assertSame('г. Казань, ул. Новая, д. 5', $user->fresh()->address);
    }

    public function test_ajax_update_clears_address_when_empty_string_sent(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Иван',
            'lastname'   => 'Очистка',
            'address'    => 'был адрес',
        ]);

        $this->patchJson('/admin/users/' . $user->id, [
            'name'     => 'Иван',
            'lastname' => 'Очистка',
            'address'  => '   ',
        ])->assertOk();

        $this->assertNull($user->fresh()->address);
    }

    public function test_ajax_store_validation_returns_422_with_address_field_error(): void
    {
        $this->postJson('/admin/users', [
            'name'               => 'Иван',
            'lastname'           => 'ДлинныйАдрес',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'address'            => str_repeat('а', 1001),
            'send_welcome_email' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'ДлинныйАдрес',
        ]);
    }

    public function test_edit_json_includes_address_for_modal_fill(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'address'    => 'г. Москва, ул. Примерная, д. 10',
        ]);

        $this->getJson('/admin/users/' . $user->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.address', 'г. Москва, ул. Примерная, д. 10');
    }

    public function test_edit_json_returns_null_address_when_not_set(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'address'    => null,
        ]);

        $this->getJson('/admin/users/' . $user->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.address', null);
    }

    public function test_foreign_partner_student_address_is_not_accessible(): void
    {
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'address'    => 'чужой адрес',
        ]);

        $this->getJson('/admin/users/' . $foreign->id . '/edit')->assertNotFound();

        $this->patchJson('/admin/users/' . $foreign->id, [
            'name'     => $foreign->name,
            'lastname' => $foreign->lastname,
            'address'  => 'взлом',
        ])->assertNotFound();

        $this->assertSame('чужой адрес', $foreign->fresh()->address);
    }

    public function test_store_non_ajax_redirects_and_creates_student_with_address(): void
    {
        $this->post(route('admin.user.store'), [
            'name'               => 'NonAjax',
            'lastname'           => 'САдресом',
            'role_id'            => $this->studentRoleId(),
            'address'            => 'г. Уфа, ул. NonAjax, 3',
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ])->assertRedirect(route('admin.user1'));

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'САдресом')
            ->first();

        $this->assertNotNull($student);
        $this->assertSame('г. Уфа, ул. NonAjax, 3', $student->address);
    }

    public function test_update_non_ajax_redirects_and_updates_address(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'NonAjax',
            'lastname'   => 'Патч',
            'address'    => 'до',
        ]);

        $this->patch(route('admin.user.update', $user), [
            'name'     => 'NonAjax',
            'lastname' => 'Патч',
            'address'  => 'после non-ajax',
        ])->assertRedirect(route('admin.user1'));

        $this->assertSame('после non-ajax', $user->fresh()->address);
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_address_error_not_empty_200(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'name'               => 'Валидация',
                'lastname'           => 'АдресNonAjax',
                'role_id'            => $this->studentRoleId(),
                'address'            => str_repeat('б', 1001),
                'send_welcome_email' => 0,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['address']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'АдресNonAjax',
        ]);
    }

    public function test_users_page_modals_render_address_field_after_phone(): void
    {
        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="create-address"', $html);
        $this->assertStringContainsString('name="address"', $html);
        $this->assertStringContainsString('>Адрес проживания<', $html);
        $this->assertStringContainsString('id="edit-address"', $html);
        $this->assertStringContainsString('maxlength="1000"', $html);

        $createPhonePos = strpos($html, 'id="create-phone"');
        $createAddressPos = strpos($html, 'id="create-address"');
        $this->assertNotFalse($createPhonePos);
        $this->assertNotFalse($createAddressPos);
        $this->assertLessThan($createAddressPos, $createPhonePos);

        $editPhonePos = strpos($html, 'id="edit-phone"');
        $editAddressPos = strpos($html, 'id="edit-address"');
        $this->assertNotFalse($editPhonePos);
        $this->assertNotFalse($editAddressPos);
        $this->assertLessThan($editAddressPos, $editPhonePos);

        // Адрес ученика не путать с адресом родителя
        $this->assertStringContainsString('name="parent_address"', $html);
        $this->assertNotSame(
            $createAddressPos,
            strpos($html, 'id="create-parent-address"')
        );
    }

    public function test_edit_user_modal_js_fills_address_on_both_open_paths(): void
    {
        $path = resource_path('views/includes/modal/editUser.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("\$('#edit-user-form #edit-address').val(response.user.address || '')", $content);

        // Два делегированных пути открытия edit (регрессия часто в дубликате)
        $fillOccurrences = substr_count(
            $content,
            "\$('#edit-user-form #edit-address').val(response.user.address || '')"
        );
        $this->assertGreaterThanOrEqual(
            2,
            $fillOccurrences,
            'Оба JS-пути открытия модалки edit должны заполнять #edit-address'
        );

        $this->assertStringContainsString("window.PhoneInputMask?.setValue('#edit-user-form #edit-phone'", $content);

        // Fallback: пустой address не должен оставлять предыдущее значение без явного сброса —
        // контракт «|| ''» гарантирует очистку input при повторном открытии другого ученика.
        $this->assertStringContainsString('response.user.address || \'\'', $content);
    }

    public function test_create_user_modal_has_empty_address_by_default(): void
    {
        $path = resource_path('views/includes/modal/createUser.blade.php');
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="create-address"', $content);
        $this->assertStringContainsString('name="address"', $content);
        $this->assertStringContainsString('>Адрес проживания<', $content);
        $this->assertStringContainsString("old('address')", $content);
        $this->assertStringNotContainsString(
            'parent_address',
            substr($content, (int) strpos($content, 'id="create-address"'), 400)
        );
    }
}
