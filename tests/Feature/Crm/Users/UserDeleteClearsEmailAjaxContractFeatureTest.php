<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UserDeleteClearsEmailTestHelpers;

/**
 * AJAX-контракт: unique email пока пользователь жив; после DELETE — 200 и повторное создание.
 *
 * @see /docs/documentation/admin-users.html#user-delete-clears-email
 */
final class UserDeleteClearsEmailAjaxContractFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;
    use UserDeleteClearsEmailTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantTrainersView($this->user);
        $this->grantRoleUpdate($this->user);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_store_student_with_taken_email_returns_422_under_email_field(): void
    {
        $email = $this->uniqueEmail('ajax-taken');
        $this->makeStudent(['email' => $email]);

        $this->postJson(route('admin.user.store'), $this->studentStorePayload($email), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Этот адрес электронной почты уже зарегистрирован.');
    }

    public function test_delete_ajax_returns_client_json_and_store_reuses_email(): void
    {
        $email = $this->uniqueEmail('ajax-reuse');
        $student = $this->makeStudent(['email' => $email]);

        $delete = $this->deleteJson(route('admin.user.delete', $student->id), [], $this->ajaxHeaders());
        $this->assertNotSame(500, $delete->getStatusCode());
        $this->assertNotSame('', trim((string) $delete->getContent()));
        $delete->assertOk()->assertJsonPath('success', 'Клиент успешно удалён');

        $this->assertEmailCleared($student->id);

        $store = $this->postJson(route('admin.user.store'), $this->studentStorePayload($email), $this->ajaxHeaders());
        $this->assertNotSame('', trim((string) $store->getContent()));
        $store->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id', 'email']])
            ->assertJsonPath('user.email', $email);
    }

    public function test_store_trainer_with_taken_email_returns_422_then_destroy_frees_it(): void
    {
        $email = $this->uniqueEmail('ajax-trainer');
        $profile = $this->makeTrainerProfile(['email' => $email]);

        $this->postJson(route('admin.trainers.store'), $this->trainerStorePayload($email), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Пользователь с таким email уже существует');

        $this->deleteJson(route('admin.trainers.destroy', $profile->id), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Тренер удалён');

        $this->assertEmailCleared((int) $profile->user_id);

        $this->postJson(route('admin.trainers.store'), $this->trainerStorePayload($email), $this->ajaxHeaders())
            ->assertOk();
    }

    public function test_store_staff_with_taken_email_returns_422_then_destroy_frees_it(): void
    {
        $email = $this->uniqueEmail('ajax-staff');
        $staff = $this->makeStaff(['email' => $email]);

        $this->postJson(route('admin.administrators.store'), $this->staffStorePayload($email), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Пользователь с таким email уже существует');

        $this->deleteJson(route('admin.administrators.destroy', ['user' => $staff->id]), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь удалён');

        $this->assertEmailCleared($staff->id);

        $this->postJson(route('admin.administrators.store'), $this->staffStorePayload($email), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь создан');
    }
}
