<?php

namespace Tests\Feature\Crm\Users;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net для welcome-credentials: redirect вместо пустого 200.
 */
final class ClientWelcomeCredentialsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->asAdmin();
        $this->grantUsersView();
        $this->grantPermission($this->user, 'schoolLeads.view');
    }

    public function test_store_from_lead_non_ajax_redirects_and_creates_user_with_welcome_email(): void
    {
        $lead = $this->createLeadWithParentEmail();

        $payload = [
            'name'             => 'NonAjax',
            'lastname'         => 'LeadClient',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_email'     => $lead->parent_email,
            'parent_lastname'  => 'Родитель',
            'parent_firstname' => 'Тест',
        ];

        $response = $this->post(route('admin.user.store'), $payload);

        $response->assertRedirect(route('admin.user1'));

        $user = User::query()->where('email', $lead->parent_email)->first();
        $this->assertNotNull($user);
        $this->assertSame($user->id, (int) $lead->fresh()->user_id);
        $this->assertNotNull($user->password);

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user, $lead) {
            return $mail->hasTo($lead->parent_email)
                && $mail->student->is($user);
        });
    }

    public function test_store_from_lead_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Без email',
            'phone'                 => '+7 900 131-31-31',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.user.store'), [
                'name'           => 'Fail',
                'lastname'       => 'NonAjax',
                'role_id'        => $this->studentRoleId(),
                'is_enabled'     => 1,
                'school_lead_id' => $lead->id,
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $response->assertSessionHasErrors(['parent_email']);
        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_store_from_lead_ajax_returns_json_contract_with_user_and_welcome_flag(): void
    {
        $lead = $this->createLeadWithParentEmail();

        $response = $this->postJson(route('admin.user.store'), [
            'name'           => 'Ajax',
            'lastname'       => 'LeadClient',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
            'parent_email'   => $lead->parent_email,
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
            ->assertJsonPath('welcome_email_sent', true);

        $this->assertGreaterThan(0, (int) $response->json('user.id'));
    }

    public function test_send_welcome_credentials_non_ajax_redirects_and_updates_password(): void
    {
        $oldPassword = 'OldPass12345';
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'non-ajax-resend@example.com',
            'password'   => Hash::make($oldPassword),
        ]);

        $response = $this->post(
            route('admin.user.send-welcome-credentials', $student),
            [],
            ['HTTP_ACCEPT' => 'text/html']
        );

        $response->assertRedirect(route('admin.user1'));
        $response->assertSessionHas('success');

        $student->refresh();
        $this->assertFalse(Hash::check($oldPassword, (string) $student->password));

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($student) {
            return $mail->hasTo('non-ajax-resend@example.com')
                && $mail->student->is($student);
        });
    }

    public function test_send_welcome_credentials_non_ajax_validation_failure_redirects_with_errors(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => null,
        ]);

        $response = $this->post(
            route('admin.user.send-welcome-credentials', $student),
            [],
            ['HTTP_ACCEPT' => 'text/html']
        );

        $response->assertRedirect(route('admin.user1'));
        $response->assertSessionHasErrors(['welcome_credentials']);
    }

    public function test_send_welcome_credentials_ajax_returns_json_message(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'ajax-resend@example.com',
        ]);

        $this->postJson(
            route('admin.user.send-welcome-credentials', $student),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonFragment(['message' => 'Новый пароль отправлен на ajax-resend@example.com.']);
    }

    private function createLeadWithParentEmail(): SchoolLead
    {
        return SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'NonAjax lead',
            'phone'                 => '+7 900 141-41-41',
            'parent_email'          => $this->schoolLeadClientParentEmail('non-ajax-lead'),
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    private function grantUsersView(): void
    {
        $this->grantPermission($this->user, 'users.view');
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
