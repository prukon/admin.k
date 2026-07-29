<?php

namespace Tests\Feature\Crm\Users;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

final class RoleStaffCreateWelcomeCredentialsFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_administrators_store_with_send_welcome_email_requires_email(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $this->postJson(route('admin.administrators.store'), [
            'name' => 'Новый',
            'lastname' => 'Админ',
            'is_enabled' => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_administrators_store_with_send_welcome_email_sends_mail(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $email = 'admin-welcome-' . uniqid('', true) . '@example.test';

        $response = $this->postJson(route('admin.administrators.store'), [
            'name' => 'Новый',
            'lastname' => 'Админ',
            'email' => $email,
            'password' => 'ShouldBeIgnored99',
            'is_enabled' => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonFragment([
                'message' => "Пользователь создан. Письмо с данными для входа отправлено на {$email}.",
            ]);

        $user = User::query()
            ->where('email', $email)
            ->where('role_id', $this->adminRoleId())
            ->firstOrFail();

        $this->assertFalse(Hash::check('ShouldBeIgnored99', $user->password));

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user, $email) {
            return $mail->hasTo($email)
                && $mail->student->is($user)
                && $mail->plainPassword !== 'ShouldBeIgnored99';
        });
    }

    public function test_administrators_store_without_flag_does_not_send_mail(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $email = 'admin-no-mail-' . uniqid('', true) . '@example.test';

        $this->postJson(route('admin.administrators.store'), [
            'name' => 'Тихий',
            'lastname' => 'Админ',
            'email' => $email,
            'password' => 'ManualPass12',
            'is_enabled' => 1,
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->assertJsonPath('welcome_email_sent', false);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('ManualPass12', $user->password));
        Mail::assertNothingSent();
    }

    public function test_administrators_store_welcome_mail_failure_still_creates_user(): void
    {
        $email = 'fail-admin-' . uniqid('', true) . '@example.test';

        $this->mock(\App\Services\Users\ClientWelcomeCredentialsService::class, function ($mock) use ($email): void {
            $mock->shouldReceive('generatePassword')->once()->andReturn('GeneratedPass12');
            $mock->shouldReceive('send')->once()->andReturn([
                'sent'  => false,
                'error' => 'SMTP down',
            ]);
            $mock->shouldReceive('createResponseMessage')->once()->andReturn(
                "Пользователь создан, но не удалось отправить письмо на {$email}."
            );
        });

        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $response = $this->postJson(route('admin.administrators.store'), [
            'name' => 'Fail',
            'lastname' => 'Mail',
            'email' => $email,
            'is_enabled' => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', false)
            ->assertJsonFragment([
                'message' => "Пользователь создан, но не удалось отправить письмо на {$email}.",
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'partner_id' => $this->partner->id,
        ]);
    }
}
