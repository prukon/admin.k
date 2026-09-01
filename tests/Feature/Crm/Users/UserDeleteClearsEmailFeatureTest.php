<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UserDeleteClearsEmailTestHelpers;

/**
 * Soft delete пользователя обнуляет users.email: HTTP-пути ученика, тренера и сотрудника.
 *
 * @see /docs/documentation/admin-users.html#user-delete-clears-email
 */
final class UserDeleteClearsEmailFeatureTest extends CrmTestCase
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

    public function test_http_delete_student_clears_email_and_allows_creating_user_with_same_address(): void
    {
        $email = $this->uniqueEmail('reuse-student');
        $student = $this->makeStudent(['email' => $email]);

        $this->deleteJson(route('admin.user.delete', $student->id), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', 'Клиент успешно удалён');

        $this->assertEmailCleared($student->id);

        $this->postJson(route('admin.user.store'), $this->studentStorePayload($email), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Клиент создан успешно');

        $this->assertLiveUserHasEmail($email, $student->id);
    }

    public function test_http_delete_trainer_clears_email_and_allows_creating_trainer_with_same_address(): void
    {
        $email = $this->uniqueEmail('reuse-trainer');
        $profile = $this->makeTrainerProfile(['email' => $email]);
        $userId = (int) $profile->user_id;

        $this->deleteJson(route('admin.trainers.destroy', $profile->id), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Тренер удалён');

        $this->assertEmailCleared($userId);
        $this->assertSoftDeleted('trainer_profiles', ['id' => $profile->id]);

        $this->postJson(route('admin.trainers.store'), $this->trainerStorePayload($email), $this->ajaxHeaders())
            ->assertOk();

        $this->assertLiveUserHasEmail($email, $userId);
    }

    public function test_http_delete_administrator_clears_email_and_allows_creating_staff_with_same_address(): void
    {
        $email = $this->uniqueEmail('reuse-staff');
        $staff = $this->makeStaff(['email' => $email]);

        $this->deleteJson(route('admin.administrators.destroy', ['user' => $staff->id]), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь удалён');

        $this->assertEmailCleared($staff->id);

        $this->postJson(route('admin.administrators.store'), $this->staffStorePayload($email), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь создан');

        $this->assertLiveUserHasEmail($email, $staff->id);
        $this->assertSame($this->adminRoleId(), (int) User::query()->where('email', $email)->value('role_id'));
    }

    public function test_model_soft_delete_clears_email_for_any_user(): void
    {
        $email = $this->uniqueEmail('reuse-model');
        $staff = $this->makeStaff(['email' => $email]);

        $staff->delete();

        $this->assertEmailCleared($staff->id);
    }

    public function test_delete_user_without_email_still_soft_deletes(): void
    {
        $student = $this->makeStudent(['email' => null]);

        $this->deleteJson(route('admin.user.delete', $student->id), [], $this->ajaxHeaders())
            ->assertOk();

        $this->assertEmailCleared($student->id);
    }
}
