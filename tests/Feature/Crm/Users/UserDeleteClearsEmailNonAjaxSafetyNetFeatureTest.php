<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UserDeleteClearsEmailTestHelpers;

/**
 * Non-AJAX safety-net: DELETE без X-Requested-With всё равно обнуляет email;
 * store без AJAX после удаления — 302 + запись; живой email — session errors.email.
 *
 * @see /docs/documentation/admin-users.html#user-delete-clears-email
 */
final class UserDeleteClearsEmailNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_delete_without_ajax_header_clears_email_and_returns_client_json(): void
    {
        $email = $this->uniqueEmail('nonajax-delete');
        $student = $this->makeStudent(['email' => $email]);

        $response = $this->delete(route('admin.user.delete', $student->id));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk()->assertJsonPath('success', 'Клиент успешно удалён');

        $this->assertEmailCleared($student->id);
    }

    public function test_store_non_ajax_with_taken_email_redirects_with_email_error(): void
    {
        $email = $this->uniqueEmail('nonajax-taken');
        $this->makeStudent(['email' => $email]);

        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), $this->studentStorePayload($email))
            ->assertStatus(302)
            ->assertSessionHasErrors(['email']);
    }

    public function test_store_non_ajax_after_delete_redirects_and_creates_student_with_same_email(): void
    {
        $email = $this->uniqueEmail('nonajax-reuse');
        $student = $this->makeStudent(['email' => $email]);

        $this->delete(route('admin.user.delete', $student->id))->assertOk();
        $this->assertEmailCleared($student->id);

        $response = $this->post(route('admin.user.store'), $this->studentStorePayload($email));

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());

        $this->assertLiveUserHasEmail($email, $student->id);
    }

    public function test_trainer_destroy_without_ajax_header_clears_email(): void
    {
        $email = $this->uniqueEmail('nonajax-trainer');
        $profile = $this->makeTrainerProfile(['email' => $email]);

        $response = $this->delete(route('admin.trainers.destroy', $profile->id));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect(route('admin.trainers.index'));

        $this->assertEmailCleared((int) $profile->user_id);
    }

    public function test_administrator_destroy_without_ajax_header_clears_email(): void
    {
        $email = $this->uniqueEmail('nonajax-staff');
        $staff = $this->makeStaff(['email' => $email]);

        $response = $this->from(route('admin.administrators.index'))
            ->delete(route('admin.administrators.destroy', ['user' => $staff->id]));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertRedirect();

        $this->assertEmailCleared($staff->id);
    }
}
