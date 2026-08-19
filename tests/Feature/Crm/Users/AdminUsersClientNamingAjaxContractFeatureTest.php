<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * AJAX-контракт нейминга ученика: JSON message «Клиент…», 200/422 errors по полям.
 * Сотрудник и ЛК не должны получить копирайт «Клиент».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersClientNamingAjaxContractFeatureTest extends CrmTestCase
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
        $this->grantStaffSectionAccess($this->user);
    }

    public function test_ajax_store_student_returns_client_created_message_and_user_payload(): void
    {
        $email = 'client-ajax-store-' . uniqid('', true) . '@example.test';

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
            'lastname'   => 'Клиентов',
            'role_id'    => $this->studentRoleId(),
        ]);
    }

    public function test_ajax_store_without_name_and_lastname_returns_422_field_errors(): void
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
            'lastname'   => 'Клиентов',
        ]);
    }

    public function test_ajax_update_student_returns_client_updated_message(): void
    {
        $student = $this->makeStudent(['name' => 'До', 'lastname' => 'Патча']);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'       => 'После',
            'lastname'   => 'Патча',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Клиент успешно обновлён');

        $this->assertSame('После', $student->fresh()->name);
    }

    public function test_ajax_update_without_name_returns_422_name_field_error(): void
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

    public function test_ajax_delete_student_returns_client_deleted_success(): void
    {
        $student = $this->makeStudent();

        $this->deleteJson(route('admin.user.delete', $student->id), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', 'Клиент успешно удалён');

        $this->assertSoftDeleted('users', ['id' => $student->id]);
    }

    public function test_ajax_edit_student_returns_user_json_not_empty_200(): void
    {
        $student = $this->makeStudent();

        $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('user.id', $student->id)
            ->assertJsonPath('user.name', $student->name)
            ->assertJsonStructure([
                'user',
                'currentUser' => ['role_id', 'isSuperadmin'],
                'fields',
                'roles',
            ]);
    }

    public function test_ajax_store_administrator_still_says_user_created_not_client(): void
    {
        $email = 'staff-ajax-' . uniqid('', true) . '@example.test';

        $response = $this->postJson(route('admin.administrators.store'), [
            'name'       => 'Новый',
            'lastname'   => 'Админ',
            'email'      => $email,
            'is_enabled' => 1,
        ], $this->ajaxHeaders());

        $response->assertOk()->assertJsonPath('message', 'Пользователь создан');
        $this->assertStringNotContainsString('Клиент', (string) $response->json('message'));

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'email'      => $email,
            'role_id'    => $this->adminRoleId(),
        ]);
    }

    public function test_account_own_update_still_says_user_updated_not_client(): void
    {
        $this->actingAs($this->user);

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Пользователь успешно обновлен');
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
        ], $this->ajaxHeaders())
            ->assertNotFound();

        $this->assertSame('Чужой', $foreign->fresh()->name);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'name'       => 'Иван',
            'lastname'   => 'Клиентов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Ученик',
            'lastname'   => 'Тестов',
        ], $attributes));
    }
}
