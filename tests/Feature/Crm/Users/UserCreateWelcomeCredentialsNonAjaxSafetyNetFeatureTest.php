<?php

namespace Tests\Feature\Crm\Users;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Non-AJAX safety-net + AJAX-контракт для store ученика с send_welcome_email.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ClientWelcomeCredentialsNonAjaxSafetyNetFeatureTest
 */
final class UserCreateWelcomeCredentialsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantUsersView($this->user);
    }

    public function test_store_with_send_welcome_email_non_ajax_redirects_and_creates_user(): void
    {
        $email = 'non-ajax-student-welcome@example.com';

        $response = $this->post(route('admin.user.store'), [
            'name'               => 'NonAjax',
            'lastname'           => 'Welcome',
            'email'              => $email,
            'password'           => 'ShouldBeIgnored99',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ]);

        $response->assertRedirect(route('admin.user1'));
        $this->assertNotSame(200, $response->getStatusCode());

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->password);
        $this->assertFalse(Hash::check('ShouldBeIgnored99', $user->password));

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user, $email) {
            return $mail->hasTo($email) && $mail->student->is($user);
        });
    }

    public function test_store_with_send_welcome_email_non_ajax_validation_failure_redirects_back_not_empty_200(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'name'               => 'Fail',
                'lastname'           => 'NonAjax',
                'role_id'            => $this->studentRoleId(),
                'is_enabled'         => 1,
                'send_welcome_email' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'NonAjax',
            'name'       => 'Fail',
        ]);
    }

    public function test_store_with_send_welcome_email_ajax_returns_json_contract(): void
    {
        $email = 'ajax-student-welcome-contract@example.com';

        $response = $this->postJson(route('admin.user.store'), [
            'name'               => 'Ajax',
            'lastname'           => 'Contract',
            'email'              => $email,
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'welcome_email_sent',
            ])
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonPath('user.email', $email);

        $this->assertGreaterThan(0, (int) $response->json('user.id'));
        $this->assertStringContainsString($email, (string) $response->json('message'));
    }

    public function test_store_with_send_welcome_email_ajax_validation_returns_422_with_email_error(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'               => 'Ajax',
            'lastname'           => 'Fail',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_without_send_welcome_email_non_ajax_redirects_without_mail(): void
    {
        $email = 'non-ajax-student-no-mail@example.com';

        $this->post(route('admin.user.store'), [
            'name'               => 'NoMail',
            'lastname'           => 'Student',
            'email'              => $email,
            'password'           => 'ManualPass12',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ])->assertRedirect(route('admin.user1'));

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('ManualPass12', $user->password));
        Mail::assertNothingSent();
    }
}
