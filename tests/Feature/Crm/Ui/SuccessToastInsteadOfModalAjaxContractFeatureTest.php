<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use App\Enums\AuditEvent;
use App\Models\Location;
use App\Models\MyLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;

/**
 * AJAX-контракт операций, чей успех UI показывает toast:
 * JSON 200 с message/success, 422 errors[field], 403, гость, чужие глаголы не 500.
 *
 * Полные CRUD-сюиты разделов не дублируем.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SuccessToastInsteadOfModalAjaxContractFeatureTest extends CrmTestCase
{
    use SuccessToastInsteadOfModalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
        $this->asAdminWith([
            'users.view',
            'users.role.update',
            'trainers.view',
            'legal_entities.view',
            'legal_entities.manage',
            'locations.view',
            'locations.manage',
            'settings.roles.view',
            'setPrices.view',
            'setPrices.customPayments.view',
            'account.user.view',
        ]);
    }

    public function test_ajax_trainer_create_returns_message_toast_shows_and_validation_errors_by_field(): void
    {
        $email = 'toast-trainer-' . uniqid('', true) . '@example.test';

        $this->postJson(route('admin.trainers.store'), [
            'lastname'   => 'Иванов',
            'name'       => 'Иван',
            'email'      => $email,
            'password'   => 'password123',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Тренер создан')
            ->assertJsonStructure(['message', 'trainer']);

        $this->postJson(route('admin.trainers.store'), [
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'lastname']);
    }

    public function test_ajax_trainer_edit_and_delete_return_messages_toast_uses_as_fallback(): void
    {
        $profile = $this->makeTrainerProfile();

        $this->putJson(route('admin.trainers.update', $profile->id), [
            'lastname'   => 'Петров',
            'name'       => 'Пётр',
            'email'      => $profile->user->email,
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Тренер обновлён');

        $this->deleteJson(route('admin.trainers.destroy', $profile->id), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Тренер удалён');

        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);
    }

    public function test_manager_without_trainers_view_gets_403_on_trainer_store(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);

        $this->postJson(route('admin.trainers.store'), [
            'lastname'   => 'Нет',
            'name'       => 'Прав',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertStatus(403);
    }

    public function test_ajax_admin_create_returns_message_that_toast_shows(): void
    {
        $email = 'toast-admin-' . uniqid('', true) . '@example.test';

        $this->postJson(route('admin.administrators.store'), [
            'name'       => 'Новый',
            'lastname'   => 'Админ',
            'email'      => $email,
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь создан')
            ->assertJsonStructure(['message', 'user']);

        $this->postJson(route('admin.administrators.store'), [
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'lastname']);
    }

    public function test_ajax_student_delete_returns_success_text_toast_hardcodes(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => 'Удаляемый',
            'lastname'   => 'Ученик',
        ]);

        $this->deleteJson(route('admin.user.delete', $student), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', 'Клиент успешно удалён');

        $this->assertSoftDeleted('users', ['id' => $student->id]);
    }

    public function test_manager_without_users_view_gets_403_on_student_delete(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
        ]);

        $this->deleteJson(route('admin.user.delete', $student), [], $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_ajax_legal_entity_create_returns_message_toast_shows(): void
    {
        $this->postJson(route('admin.legal-entities.store'), [
            'business_type'     => 'ANO',
            'organization_name' => 'АНО Toast Contract',
            'tax_id'            => '7701987654',
            'is_enabled'        => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Юр. лицо создано')
            ->assertJsonStructure(['message', 'legal_entity']);

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'UNKNOWN',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_type']);
    }

    public function test_ajax_location_create_and_edit_return_messages_toast_shows(): void
    {
        $this->postJson(route('admin.locations.store'), [
            'name'       => 'Объект toast create',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Объект создан')
            ->assertJsonStructure(['message', 'location']);

        $this->postJson(route('admin.locations.store'), [
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Объект toast edit',
        ]);

        $this->putJson(route('admin.locations.update', $location), [
            'name'       => 'Объект toast updated',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Объект обновлён');

        $this->assertDatabaseHas('locations', [
            'id'   => $location->id,
            'name' => 'Объект toast updated',
        ]);
    }

    public function test_ajax_location_delete_returns_message_toast_does_not_copy_verbatim(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Объект toast',
        ]);

        $this->deleteJson(route('admin.locations.destroy', $location), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Объект удалён');

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_manager_without_locations_manage_gets_403_on_location_mutate(): void
    {
        $actor = $this->createUserWithoutPermission('locations.manage', $this->partner);
        $this->grantPermissionsTo($actor, ['locations.view']);
        $this->actingAs($actor);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name'       => 'Forbidden toast create',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertStatus(403);

        $this->putJson(route('admin.locations.update', $location), [
            'name'       => 'Forbidden toast update',
            'is_enabled' => 1,
        ], $this->ajaxHeaders())->assertStatus(403);

        $this->deleteJson(route('admin.locations.destroy', $location), [], $this->ajaxHeaders())
            ->assertStatus(403);
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_ajax_role_create_returns_success_json_and_empty_name_422(): void
    {
        $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Роль toast',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'role', 'permission_ids']);

        $this->postJson(route('admin.setting.role.create'), [
            'name' => '',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_ajax_role_delete_returns_success_json_and_missing_id_422(): void
    {
        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Роль toast удалить',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $roleId = (int) $create->json('role.id');
        $this->assertGreaterThan(0, $roleId);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => $roleId,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);

        $this->deleteJson(route('admin.setting.role.delete'), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_manager_without_roles_view_gets_403_on_role_create(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->actingAs($actor);

        $this->postJson(route('admin.setting.role.create'), [
            'name' => 'Запрещено',
        ], $this->ajaxHeaders())->assertStatus(403);

        $this->deleteJson(route('admin.setting.role.delete'), [
            'role_id' => 1,
        ], $this->ajaxHeaders())->assertStatus(403);
    }

    public function test_ajax_own_password_change_returns_success_and_short_password_422(): void
    {
        $this->putJson(route('account.user.password.update'), [
            'password' => 'account-pass-88',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('account-pass-88', $this->user->fresh()->password));

        $this->putJson(route('account.user.password.update'), [
            'password' => 'short7',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_ajax_own_password_same_as_current_returns_422_and_does_not_log(): void
    {
        $this->user->password = 'current-pass-8';
        $this->user->save();

        $this->putJson(route('account.user.password.update'), [
            'password' => 'current-pass-8',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Новый пароль совпадает с текущим.');

        $this->assertTrue(Hash::check('current-pass-8', $this->user->fresh()->password));

        $this->assertNull(
            MyLog::query()
                ->where('target_type', User::class)
                ->where('target_id', $this->user->id)
                ->where('event', AuditEvent::UserPasswordChanged->value)
                ->first()
        );
    }

    public function test_ajax_own_password_after_same_rejection_a_different_password_still_saves(): void
    {
        $this->user->password = 'current-pass-8';
        $this->user->save();
        $url = route('account.user.password.update');

        $this->putJson($url, ['password' => 'current-pass-8'], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->putJson($url, ['password' => 'account-pass-99'], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('account-pass-99', $this->user->fresh()->password));

        $this->putJson($url, ['password' => 'account-pass-99'], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Новый пароль совпадает с текущим.');
    }

    public function test_ajax_custom_payment_create_returns_success_json_without_message_toast_is_hardcoded(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа toast платежа',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        $this->postJson(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $student->id,
            'team_id' => $team->id,
            'amount'  => 250,
            'note'    => 'Toast store',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'custom_payment' => ['id']]);

        $this->postJson(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $student->id,
            'amount'  => 250,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_guest_is_denied_on_toast_mutations_and_nothing_is_persisted(): void
    {
        $profile = $this->makeTrainerProfile();
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
        ]);
        Auth::logout();

        $calls = [
            ['POST', route('admin.trainers.store'), ['lastname' => 'Гость', 'name' => 'Тренер', 'is_enabled' => 1]],
            ['DELETE', route('admin.trainers.destroy', $profile->id), []],
            ['POST', route('admin.administrators.store'), ['name' => 'Гость', 'lastname' => 'Админ', 'is_enabled' => 1]],
            ['DELETE', route('admin.user.delete', $student), []],
            ['POST', route('admin.locations.store'), ['name' => 'Гость объект', 'is_enabled' => 1]],
            ['PUT', route('admin.locations.update', $location), ['name' => 'Гость объект', 'is_enabled' => 1]],
            ['DELETE', route('admin.locations.destroy', $location), []],
            ['POST', route('admin.setting.role.create'), ['name' => 'Гость']],
            ['DELETE', route('admin.setting.role.delete'), ['role_id' => 1]],
            ['PUT', route('account.user.password.update'), ['password' => 'guestpass88']],
        ];

        foreach ($calls as [$method, $url, $data]) {
            $response = $this->json($method, $url, $data);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость {$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode(), "Гость {$method} {$url} → 500");
        }

        $this->assertDatabaseHas('trainer_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'deleted_at' => null]);
    }

    public function test_wrong_http_methods_on_toast_mutations_never_return_500_or_empty_200(): void
    {
        $profile = $this->makeTrainerProfile();
        $location = Location::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
        ]);

        $items = [
            ['GET', route('admin.trainers.store')],
            ['GET', route('admin.trainers.destroy', $profile->id)],
            ['PATCH', route('admin.trainers.store')],
            ['GET', route('admin.user.delete', $student)],
            ['POST', route('admin.user.delete', $student)],
            ['GET', route('admin.locations.store')],
            ['GET', route('admin.locations.update', $location)],
            ['GET', route('admin.locations.destroy', $location)],
            ['POST', route('admin.locations.destroy', $location)],
            ['GET', route('admin.setting.role.create')],
            ['PUT', route('admin.setting.role.create')],
            ['GET', route('admin.setting.role.delete')],
            ['POST', route('admin.setting.role.delete')],
            ['GET', route('account.user.password.update')],
            ['POST', route('account.user.password.update')],
        ];

        foreach ($items as [$method, $url]) {
            foreach ([true, false] as $asJson) {
                $response = $asJson
                    ? $this->json($method, $url)
                    : $this->call($method, $url, $method === 'GET' ? [] : ['_token' => csrf_token()]);

                $label = ($asJson ? 'JSON' : 'web')." {$method} {$url}";
                $this->assertNotSame(500, $response->getStatusCode(), "{$label} → 500");
                $this->assertContains(
                    $response->getStatusCode(),
                    [200, 302, 401, 403, 404, 405, 419, 422],
                    "{$label} → {$response->getStatusCode()}"
                );
                if ($response->getStatusCode() === 200 && in_array($method, ['GET', 'PATCH', 'PUT'], true)
                    && ! str_contains($url, 'password')
                ) {
                    $this->assertNotSame('', trim((string) $response->getContent()), "{$label} пустой 200");
                }
            }
        }
    }

    private function makeTrainerProfile(): TrainerProfile
    {
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $trainerRoleId,
            'team_id'    => null,
        ]);

        return TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $user->id,
        ]);
    }
}
