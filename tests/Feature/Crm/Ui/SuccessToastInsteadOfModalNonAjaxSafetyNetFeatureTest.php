<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use App\Models\Location;
use App\Models\Role;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;

/**
 * Non-AJAX safety-net для операций с toast-успехом:
 * без X-Requested-With запись создаётся/обновляется/удаляется,
 * не пустой бессмысленный 200 и не 500.
 *
 * Юр. лица, типы тренера, доп. платежи и create администратора уже покрыты своими сюитами.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SuccessToastInsteadOfModalNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
            'trainers.view',
            'locations.view',
            'locations.manage',
            'settings.roles.view',
            'account.user.view',
        ]);
    }

    public function test_non_ajax_trainer_store_redirects_and_creates_trainer(): void
    {
        $email = 'non-ajax-trainer-' . uniqid('', true) . '@example.test';

        $response = $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainers.store'), [
                '_token'     => csrf_token(),
                'lastname'   => 'Неайакс',
                'name'       => 'Тренер',
                'email'      => $email,
                'password'   => 'password123',
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Create тренера без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.trainers.index'));

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'email'      => $email,
            'lastname'   => 'Неайакс',
        ]);
    }

    public function test_non_ajax_trainer_store_validation_redirects_with_field_errors(): void
    {
        $response = $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainers.store'), [
                '_token'     => csrf_token(),
                'is_enabled' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'lastname']);
    }

    public function test_non_ajax_trainer_delete_redirects_and_soft_deletes(): void
    {
        $profile = $this->makeTrainerProfile();

        $response = $this->from(route('admin.trainers.index'))
            ->delete(route('admin.trainers.destroy', $profile->id), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 200]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
        } else {
            $response->assertRedirect(route('admin.trainers.index'));
        }

        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);
    }

    public function test_non_ajax_location_delete_redirects_and_deletes_record(): void
    {
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Non-ajax объект',
        ]);

        $response = $this->from(route('admin.locations.index'))
            ->delete(route('admin.locations.destroy', $location), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Удаление объекта без AJAX — redirect, не пустой 200');
        $response->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_non_ajax_student_delete_persists_and_is_not_empty_200(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => 'NonAjax',
            'lastname'   => 'Ученик',
        ]);

        $response = $this->from(route('admin.user1'))
            ->delete(route('admin.user.delete', $student), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', 'Пользователь успешно удалён');
        }

        $this->assertSoftDeleted('users', ['id' => $student->id]);
    }

    public function test_non_ajax_role_create_persists_and_is_not_empty_200(): void
    {
        $label = 'NonAjax роль ' . uniqid('', true);

        $response = $this->from(route('admin.setting.rule'))
            ->post(route('admin.setting.role.create'), [
                '_token' => csrf_token(),
                'name'   => $label,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
        }

        $this->assertDatabaseHas('roles', ['label' => $label]);
    }

    public function test_non_ajax_role_delete_persists_and_is_not_empty_200(): void
    {
        $create = $this->postJson(route('admin.setting.role.create'), [
            'name' => 'NonAjax роль удалить ' . uniqid('', true),
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $roleId = (int) $create->json('role.id');
        $this->assertGreaterThan(0, $roleId);

        $response = $this->from(route('admin.setting.rule'))
            ->delete(route('admin.setting.role.delete'), [
                '_token'  => csrf_token(),
                'role_id' => $roleId,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
        }

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_non_ajax_role_create_empty_name_redirects_with_name_field_error(): void
    {
        $response = $this->from(route('admin.setting.rule'))
            ->post(route('admin.setting.role.create'), [
                '_token' => csrf_token(),
                'name'   => '',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация роли не должна давать успешный 200');
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    public function test_non_ajax_own_password_change_updates_record_and_is_not_empty_200(): void
    {
        $response = $this->from(route('account.user.edit'))
            ->put(route('account.user.password.update'), [
                '_token'   => csrf_token(),
                'password' => 'non-ajax-88',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);
        if ($response->getStatusCode() === 200) {
            $this->assertNotSame('', trim((string) $response->getContent()));
            $response->assertJsonPath('success', true);
        }

        $this->assertTrue(Hash::check('non-ajax-88', $this->user->fresh()->password));
    }

    public function test_non_ajax_own_password_short_value_redirects_with_password_field_error(): void
    {
        $response = $this->from(route('account.user.edit'))
            ->put(route('account.user.password.update'), [
                '_token'   => csrf_token(),
                'password' => 'short7',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
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
