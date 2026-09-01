<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UserDeleteClearsEmailTestHelpers;

/**
 * Доступ: гость / без права / чужой партнёр не обнуляют email; admin DELETE — обнуляет.
 *
 * @see /docs/documentation/admin-users.html#user-delete-clears-email
 */
final class UserDeleteClearsEmailAccessFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;
    use UserDeleteClearsEmailTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_cannot_delete_student_and_email_stays(): void
    {
        $email = $this->uniqueEmail('access-guest');
        $student = $this->makeStudent(['email' => $email]);

        Auth::logout();

        $json = $this->deleteJson(route('admin.user.delete', $student->id), [], $this->ajaxHeaders());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertContains($json->getStatusCode(), [401, 403, 419]);

        $web = $this->delete(route('admin.user.delete', $student->id));
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertContains($web->getStatusCode(), [302, 401, 403, 419]);

        $this->assertEmailUnchanged($student->id, $email);
    }

    public function test_manager_without_users_view_gets_403_and_email_stays(): void
    {
        $email = $this->uniqueEmail('access-403');
        $student = $this->makeStudent(['email' => $email]);

        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);

        $this->deleteJson(route('admin.user.delete', $student->id), [], $this->ajaxHeaders())
            ->assertForbidden();

        $this->assertEmailUnchanged($student->id, $email);
    }

    public function test_admin_delete_of_foreign_student_is_not_found_and_email_stays(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $email = $this->uniqueEmail('access-foreign');
        $foreign = $this->makeStudent([
            'partner_id' => $this->foreignPartner->id,
            'email'      => $email,
        ]);

        $this->deleteJson(route('admin.user.delete', $foreign->id), [], $this->ajaxHeaders())
            ->assertNotFound();

        $this->assertEmailUnchanged($foreign->id, $email);
    }

    public function test_admin_with_users_view_can_delete_and_email_is_cleared(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $email = $this->uniqueEmail('access-200');
        $student = $this->makeStudent(['email' => $email]);

        $this->deleteJson(route('admin.user.delete', $student->id), [], $this->ajaxHeaders())
            ->assertOk();

        $this->assertEmailCleared($student->id);
    }

    public function test_unsupported_methods_on_student_delete_do_not_clear_email(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $email = $this->uniqueEmail('access-method');
        $student = $this->makeStudent(['email' => $email]);
        $url = route('admin.user.delete', $student->id);

        foreach (['get', 'post', 'put', 'patch'] as $method) {
            $response = $this->{$method}($url);
            $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url}");
            $this->assertContains($response->getStatusCode(), [404, 405], "{$method} {$url}");
        }

        $this->assertEmailUnchanged($student->id, $email);
    }

    public function test_manager_without_trainers_view_cannot_destroy_trainer_and_email_stays(): void
    {
        $email = $this->uniqueEmail('access-trainer-403');
        $profile = $this->makeTrainerProfile(['email' => $email]);

        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $this->deleteJson(route('admin.trainers.destroy', $profile->id), [], $this->ajaxHeaders())
            ->assertForbidden();

        $this->assertEmailUnchanged((int) $profile->user_id, $email);
    }
}
