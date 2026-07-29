<?php

namespace Tests\Feature\Crm\Users;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Non-AJAX safety-net + AJAX-контракт для store администратора с send_welcome_email.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see UserCreateWelcomeCredentialsNonAjaxSafetyNetFeatureTest
 */
final class RoleStaffCreateWelcomeCredentialsNonAjaxSafetyNetFeatureTest extends CrmTestCase
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
        $this->grantStaffSectionAccess($this->user);
    }

    public function test_administrators_store_with_send_welcome_email_non_ajax_redirects_and_creates_user(): void
    {
        $email = 'non-ajax-admin-welcome-' . uniqid('', true) . '@example.test';

        $response = $this->from(route('admin.administrators.index'))
            ->post(route('admin.administrators.store'), [
                'name'               => 'NonAjax',
                'lastname'           => 'Admin',
                'email'              => $email,
                'password'           => 'ShouldBeIgnored99',
                'is_enabled'         => 1,
                'send_welcome_email' => 1,
            ]);

        $response->assertRedirect(route('admin.administrators.index'));
        $this->assertNotSame(200, $response->getStatusCode());

        $user = User::query()
            ->where('email', $email)
            ->where('role_id', $this->adminRoleId())
            ->first();

        $this->assertNotNull($user);
        $this->assertFalse(Hash::check('ShouldBeIgnored99', $user->password));

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user, $email) {
            return $mail->hasTo($email) && $mail->student->is($user);
        });
    }

    public function test_administrators_store_with_send_welcome_email_non_ajax_validation_failure_redirects_back_not_empty_200(): void
    {
        $this->from(route('admin.administrators.index'))
            ->post(route('admin.administrators.store'), [
                'name'               => 'Fail',
                'lastname'           => 'NonAjax',
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

    public function test_administrators_store_with_send_welcome_email_ajax_returns_json_contract(): void
    {
        $email = 'ajax-admin-welcome-contract-' . uniqid('', true) . '@example.test';

        $response = $this->postJson(route('admin.administrators.store'), [
            'name'               => 'Ajax',
            'lastname'           => 'Contract',
            'email'              => $email,
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

    public function test_administrators_store_with_send_welcome_email_ajax_validation_returns_422(): void
    {
        $this->postJson(route('admin.administrators.store'), [
            'name'               => 'Ajax',
            'lastname'           => 'Fail',
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_administrators_store_without_send_welcome_email_non_ajax_redirects_without_mail(): void
    {
        $email = 'non-ajax-admin-no-mail-' . uniqid('', true) . '@example.test';

        $this->from(route('admin.administrators.index'))
            ->post(route('admin.administrators.store'), [
                'name'               => 'NoMail',
                'lastname'           => 'Admin',
                'email'              => $email,
                'password'           => 'ManualPass12',
                'is_enabled'         => 1,
                'send_welcome_email' => 0,
            ])
            ->assertRedirect(route('admin.administrators.index'));

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('ManualPass12', $user->password));
        Mail::assertNothingSent();
    }
}
