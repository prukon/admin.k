<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\OutgoingEmailLog;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Welcome-письмо с паролем при создании клиента из заявки и повторная отправка.
 */
final class SchoolLeadClientWelcomeCredentialsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    public function test_store_from_lead_requires_parent_email(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Без email',
            'phone'                  => '+7 900 111-22-33',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Иван',
            'lastname'       => 'Тестов',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email'])
            ->assertJsonFragment([
                'message' => 'Укажите email родителя для создания клиента и отправки данных для входа.',
            ]);

        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_store_from_lead_copies_parent_email_to_user_email(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Email copy',
            'phone'                  => '+7 900 121-21-21',
            'parent_email'           => 'copy-parent@example.com',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Email',
            'lastname'       => 'Copy',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
            'parent_email'   => 'copy-parent@example.com',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame('copy-parent@example.com', $user->email);
    }

    public function test_store_from_lead_mail_failure_still_creates_user_and_reports_welcome_email_not_sent(): void
    {
        $this->mock(\App\Services\Users\ClientWelcomeCredentialsService::class, function ($mock): void {
            $mock->shouldReceive('generatePassword')->once()->andReturn('GeneratedPass12');
            $mock->shouldReceive('send')->once()->andReturn([
                'sent'  => false,
                'error' => 'SMTP down',
            ]);
        });

        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Mail fail',
            'phone'                  => '+7 900 131-31-31',
            'parent_email'           => 'mail-fail@example.com',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Mail',
            'lastname'       => 'Fail',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
            'parent_email'   => 'mail-fail@example.com',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', false)
            ->assertJsonFragment([
                'message' => 'Клиент создан, но не удалось отправить письмо на mail-fail@example.com.',
            ]);

        $this->assertSame(
            (int) $response->json('user.id'),
            (int) $lead->fresh()->user_id
        );
    }

    public function test_store_from_lead_sets_email_generates_password_and_sends_mail(): void
    {
        Mail::fake();
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Мария',
            'phone'                  => '+7 900 222-33-44',
            'parent_email'           => 'welcome-lead@example.com',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'             => 'Мария',
            'lastname'         => 'Клиентова',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_email'     => 'welcome-lead@example.com',
            'parent_lastname'  => 'Клиентова',
            'parent_firstname' => 'Анна',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonFragment(['message' => 'Клиент создан. Письмо с данными для входа отправлено на welcome-lead@example.com.']);

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame('welcome-lead@example.com', $user->email);
        $this->assertNotNull($user->password);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user) {
            return $mail->hasTo('welcome-lead@example.com')
                && $mail->student->is($user)
                && $mail->partnerId === (int) $this->partner->id
                && $mail->plainPassword !== '';
        });
    }

    public function test_store_from_lead_welcome_mail_is_logged_for_outgoing_email_report(): void
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Лог',
            'phone'                  => '+7 900 333-44-55',
            'parent_email'           => 'logged-lead@example.com',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'             => 'Лог',
            'lastname'         => 'Почты',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_email'     => 'logged-lead@example.com',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $log = OutgoingEmailLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('status', OutgoingEmailLog::STATUS_SENT)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString($this->partner->title, (string) $log->subject);
        $this->assertStringContainsString('logged-lead@example.com', (string) $log->to_summary);
    }

    public function test_send_welcome_credentials_regenerates_password_and_sends_mail(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $oldPassword = 'OldPass123';
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'resend@example.com',
            'password'   => Hash::make($oldPassword),
        ]);

        $response = $this->postJson(
            route('admin.user.send-welcome-credentials', $student),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Новый пароль отправлен на resend@example.com.']);

        $student->refresh();
        $this->assertFalse(Hash::check($oldPassword, (string) $student->password));

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($student) {
            return $mail->hasTo('resend@example.com')
                && $mail->student->is($student)
                && $mail->plainPassword !== '';
        });
    }

    public function test_send_welcome_credentials_requires_email(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => null,
        ]);

        $this->postJson(
            route('admin.user.send-welcome-credentials', $student),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonStructure(['message'])
            ->assertJsonFragment(['message' => 'У ученика не указан email.']);
    }

    public function test_send_welcome_credentials_rejects_trainer_role(): void
    {
        Mail::fake();

        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $trainerRoleId,
            'email'      => 'trainer@example.com',
        ]);

        $this->postJson(
            route('admin.user.send-welcome-credentials', $trainer),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Отправка доступна только для учеников.']);

        Mail::assertNothingSent();
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
