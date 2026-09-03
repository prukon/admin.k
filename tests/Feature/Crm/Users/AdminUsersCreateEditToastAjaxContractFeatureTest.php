<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;

/**
 * AJAX-контракт create/edit ученика: JSON 200 с message для toast, 422 errors по полям.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersCreateEditToastAjaxContractFeatureTest extends AdminUsersCreateEditToastTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUsersViewer();
    }

    public function test_ajax_store_returns_message_that_toast_shows_and_user_payload(): void
    {
        $email = 'toast-ajax-store-'.uniqid('', true).'@example.test';

        $this->postJson(route('admin.user.store'), $this->studentPayload([
            'email' => $email,
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Клиент создан успешно')
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'birthday', 'start_date', 'team', 'email', 'is_enabled'],
            ]);

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'email'      => $email,
            'name'       => 'Иван',
            'lastname'   => 'Тостов',
            'role_id'    => $this->studentRoleId(),
        ]);
    }

    public function test_ajax_store_without_name_and_lastname_returns_422_field_errors_not_toast_payload(): void
    {
        $this->postJson(route('admin.user.store'), [
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'lastname'])
            ->assertJsonPath('errors.name.0', 'Пожалуйста, укажите имя.')
            ->assertJsonPath('errors.lastname.0', 'Пожалуйста, укажите фамилию.');

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'Тостов',
            'name'       => 'Иван',
        ]);
    }

    public function test_ajax_update_returns_message_that_toast_shows(): void
    {
        $student = $this->makeStudent(['name' => 'До']);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => 'После',
            'lastname'   => 'Тостов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Клиент успешно обновлён')
            ->assertJsonStructure(['message']);

        $this->assertSame('После', $student->fresh()->name);
    }

    public function test_ajax_update_without_name_returns_422_on_name_field(): void
    {
        $student = $this->makeStudent();

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => '',
            'lastname'   => $student->lastname,
            'role_id'    => $this->studentRoleId(),
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('errors.name.0', 'Поле "Имя" обязательно для заполнения.');

        $this->assertSame($student->name, $student->fresh()->name);
    }

    public function test_ajax_update_without_lastname_returns_422_on_lastname_field(): void
    {
        $student = $this->makeStudent();

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => $student->name,
            'lastname'   => '',
            'role_id'    => $this->studentRoleId(),
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lastname'])
            ->assertJsonPath('errors.lastname.0', 'Поле "Фамилия" обязательно для заполнения.');

        $this->assertSame($student->lastname, $student->fresh()->lastname);
    }

    public function test_ajax_edit_returns_user_json_for_modal_not_empty_200(): void
    {
        $student = $this->makeStudent();

        $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('user.id', $student->id)
            ->assertJsonPath('user.name', $student->name)
            ->assertJsonStructure(['user', 'currentUser', 'fields', 'roles']);
    }

    public function test_ajax_update_foreign_partner_student_is_not_found(): void
    {
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Чужой',
            'lastname'   => 'Клиент',
        ]);

        $this->patchJson(route('admin.user.update', $foreign->id), [
            'name'       => 'Взлом',
            'lastname'   => 'Клиент',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertNotFound();

        $this->assertSame('Чужой', $foreign->fresh()->name);
    }
}
