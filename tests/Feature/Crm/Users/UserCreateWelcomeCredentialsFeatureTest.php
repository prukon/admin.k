<?php

namespace Tests\Feature\Crm\Users;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Welcome-письмо при ручном создании ученика (чекбокс send_welcome_email).
 */
final class UserCreateWelcomeCredentialsFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_store_with_send_welcome_email_requires_email(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $this->postJson(route('admin.user.store'), [
            'name'               => 'Иван',
            'lastname'           => 'Тестов',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_with_send_welcome_email_generates_password_and_sends_mail(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantUsersView($this->user);

        $response = $this->postJson(route('admin.user.store'), [
            'name'               => 'Мария',
            'lastname'           => 'Ученикова',
            'email'              => 'student-welcome@example.com',
            'password'           => 'ShouldBeIgnored99',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonFragment([
                'message' => 'Клиент создан. Письмо с данными для входа отправлено на student-welcome@example.com.',
            ]);

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame('student-welcome@example.com', $user->email);
        $this->assertNotNull($user->password);
        $this->assertFalse(Hash::check('ShouldBeIgnored99', $user->password));

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user) {
            return $mail->hasTo('student-welcome@example.com')
                && $mail->student->is($user)
                && $mail->plainPassword !== ''
                && $mail->plainPassword !== 'ShouldBeIgnored99';
        });
    }

    public function test_store_without_send_welcome_email_does_not_send_mail(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantUsersView($this->user);

        $response = $this->postJson(route('admin.user.store'), [
            'name'               => 'Пётр',
            'lastname'           => 'Безписьмов',
            'email'              => 'student-no-mail@example.com',
            'password'           => 'ManualPass12',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', false)
            ->assertJsonFragment(['message' => 'Клиент создан успешно']);

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertTrue(Hash::check('ManualPass12', $user->password));

        Mail::assertNothingSent();
    }

    public function test_store_welcome_mail_failure_still_creates_user(): void
    {
        $this->mock(\App\Services\Users\ClientWelcomeCredentialsService::class, function ($mock): void {
            $mock->shouldReceive('generatePassword')->once()->andReturn('GeneratedPass12');
            $mock->shouldReceive('send')->once()->andReturn([
                'sent'  => false,
                'error' => 'SMTP down',
            ]);
            $mock->shouldReceive('createResponseMessage')->once()->andReturn(
                'Клиент создан, но не удалось отправить письмо на fail-student@example.com.'
            );
        });

        $this->asAdmin();
        $this->grantUsersView($this->user);

        $response = $this->postJson(route('admin.user.store'), [
            'name'               => 'Fail',
            'lastname'           => 'Mail',
            'email'              => 'fail-student@example.com',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', false)
            ->assertJsonFragment([
                'message' => 'Клиент создан, но не удалось отправить письмо на fail-student@example.com.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'fail-student@example.com',
            'partner_id' => $this->partner->id,
        ]);
    }
}
