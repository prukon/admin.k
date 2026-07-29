<?php

namespace Tests\Feature\Crm\Trainers;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX safety-net + AJAX-контракт для store тренера с send_welcome_email.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see UserCreateWelcomeCredentialsNonAjaxSafetyNetFeatureTest
 */
final class TrainerCreateWelcomeCredentialsNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

        $this->asAdmin();
        $this->grantTrainersView();
    }

    public function test_store_with_send_welcome_email_non_ajax_redirects_and_creates_trainer(): void
    {
        $email = 'non-ajax-trainer-welcome@example.com';

        $response = $this->post(route('admin.trainers.store'), [
            'name'               => 'NonAjax',
            'lastname'           => 'Trainer',
            'email'              => $email,
            'password'           => 'ShouldBeIgnored99',
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ]);

        $response->assertRedirect(route('admin.trainers.index'));
        $this->assertNotSame(200, $response->getStatusCode());

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertFalse(Hash::check('ShouldBeIgnored99', $user->password));
        $this->assertDatabaseHas('trainer_profiles', [
            'user_id'    => $user->id,
            'partner_id' => $this->partner->id,
        ]);

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) use ($user, $email) {
            return $mail->hasTo($email) && $mail->student->is($user);
        });
    }

    public function test_store_with_send_welcome_email_non_ajax_validation_failure_redirects_back_not_empty_200(): void
    {
        $this->from(route('admin.trainers.index'))
            ->post(route('admin.trainers.store'), [
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

    public function test_store_with_send_welcome_email_ajax_returns_json_contract(): void
    {
        $email = 'ajax-trainer-welcome-contract@example.com';

        $response = $this->postJson(route('admin.trainers.store'), [
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
                'trainer' => [
                    'id',
                    'email',
                ],
                'welcome_email_sent',
            ])
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonPath('trainer.email', $email);

        $this->assertStringContainsString($email, (string) $response->json('message'));
    }

    public function test_store_with_send_welcome_email_ajax_validation_returns_422(): void
    {
        $this->postJson(route('admin.trainers.store'), [
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

    public function test_store_without_send_welcome_email_non_ajax_redirects_without_mail(): void
    {
        $email = 'non-ajax-trainer-no-mail@example.com';

        $this->post(route('admin.trainers.store'), [
            'name'               => 'NoMail',
            'lastname'           => 'Trainer',
            'email'              => $email,
            'password'           => 'ManualPass12',
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ])->assertRedirect(route('admin.trainers.index'));

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check('ManualPass12', $user->password));
        Mail::assertNothingSent();
    }

    private function grantTrainersView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('trainers.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
