<?php

namespace Tests\Feature\Crm\Trainers;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;

final class TrainerCreateWelcomeCredentialsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantTrainersView(): void
    {
        $permId = $this->permissionId('trainers.view');

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_store_with_send_welcome_email_requires_email(): void
    {
        $this->asAdmin();
        $this->grantTrainersView();

        $this->postJson(route('admin.trainers.store'), [
            'name' => 'Иван',
            'lastname' => 'Тренеров',
            'is_enabled' => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_with_send_welcome_email_sends_mail(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantTrainersView();

        $response = $this->postJson(route('admin.trainers.store'), [
            'name' => 'Анна',
            'lastname' => 'Тренер',
            'email' => 'trainer-welcome@example.com',
            'password' => 'ShouldBeIgnored99',
            'is_enabled' => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonFragment([
                'message' => 'Тренер создан. Письмо с данными для входа отправлено на trainer-welcome@example.com.',
            ]);

        $user = User::query()
            ->where('email', 'trainer-welcome@example.com')
            ->where('partner_id', $this->partner->id)
            ->firstOrFail();

        $this->assertFalse(Hash::check('ShouldBeIgnored99', $user->password));

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user) {
            return $mail->hasTo('trainer-welcome@example.com')
                && $mail->student->is($user)
                && $mail->plainPassword !== 'ShouldBeIgnored99';
        });
    }

    public function test_store_without_send_welcome_email_keeps_manual_password(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantTrainersView();

        $this->postJson(route('admin.trainers.store'), [
            'name' => 'Борис',
            'lastname' => 'Безписьмов',
            'email' => 'trainer-no-mail@example.com',
            'password' => 'ManualPass12',
            'is_enabled' => 1,
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->assertJsonPath('welcome_email_sent', false);

        $user = User::query()
            ->where('email', 'trainer-no-mail@example.com')
            ->firstOrFail();

        $this->assertTrue(Hash::check('ManualPass12', $user->password));
        Mail::assertNothingSent();
    }

    public function test_store_welcome_mail_failure_still_creates_trainer(): void
    {
        $this->mock(\App\Services\Users\ClientWelcomeCredentialsService::class, function ($mock): void {
            $mock->shouldReceive('generatePassword')->once()->andReturn('GeneratedPass12');
            $mock->shouldReceive('send')->once()->andReturn([
                'sent'  => false,
                'error' => 'SMTP down',
            ]);
            $mock->shouldReceive('createResponseMessage')->once()->andReturn(
                'Тренер создан, но не удалось отправить письмо на fail-trainer@example.com.'
            );
        });

        $this->asAdmin();
        $this->grantTrainersView();

        $response = $this->postJson(route('admin.trainers.store'), [
            'name' => 'Fail',
            'lastname' => 'Mail',
            'email' => 'fail-trainer@example.com',
            'is_enabled' => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', false)
            ->assertJsonFragment([
                'message' => 'Тренер создан, но не удалось отправить письмо на fail-trainer@example.com.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'fail-trainer@example.com',
            'partner_id' => $this->partner->id,
        ]);
    }
}
