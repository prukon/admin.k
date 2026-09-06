<?php

namespace Tests\Feature\Crm\Contracts;

use App\Mail\ContractClientFillInvitationMail;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ContractInvitationEmailFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 0]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();
    }

    /** @test */
    public function template_mode_sends_email_with_system_defaults_when_version_has_null_email_fields(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'parent@example.com', 'name' => 'Мария', 'lastname' => 'Козлова']);
        $template = $this->makeTemplateWithNullEmailFields();

        $this->postContractFromTemplate($student, $template)->assertStatus(302);

        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) use ($student) {
            $renderer = app(\App\Services\Contracts\ContractInvitationEmailRenderer::class);
            $subject = $renderer->renderSubject($mail->contract, $mail->student);
            $body = $renderer->renderBodyHtml($mail->contract, $mail->student);

            $this->assertStringContainsString('Козлова', $subject);
            $this->assertStringContainsString('KidsCRM.online', $subject);
            $this->assertStringContainsString('подготовлен договор', $body);
            $this->assertStringNotContainsString('{{child_full_name}}', $body);
            $this->assertStringNotContainsString('{{partner_name}}', $body);

            return $mail->student->id === $student->id;
        });
    }

    /** @test */
    public function invitation_sends_two_separate_emails_when_student_and_parent_addresses_differ(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'student-invite@example.com']);
        $this->attachParent($student, 'parent-invite@example.com');
        $template = $this->makeTemplateWithNullEmailFields();

        $this->postContractFromTemplate($student, $template)->assertStatus(302);

        Mail::assertSent(ContractClientFillInvitationMail::class, 2);
        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) {
            return $mail->hasTo('student-invite@example.com') && ! $mail->hasTo('parent-invite@example.com');
        });
        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) {
            return $mail->hasTo('parent-invite@example.com') && ! $mail->hasTo('student-invite@example.com');
        });

        $payload = $this->latestInvitePayload();
        $this->assertSame(
            ['student-invite@example.com', 'parent-invite@example.com'],
            $payload['emails'] ?? null
        );
        $this->assertSame('student-invite@example.com', $payload['email'] ?? null);
    }

    /** @test */
    public function invitation_sends_one_email_when_student_and_parent_addresses_match_ignoring_case(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'Shared@Example.com']);
        $this->attachParent($student, 'shared@example.com');
        $template = $this->makeTemplateWithNullEmailFields();

        $this->postContractFromTemplate($student, $template)->assertStatus(302);

        Mail::assertSent(ContractClientFillInvitationMail::class, 1);
        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) {
            return $mail->hasTo('Shared@Example.com');
        });

        $payload = $this->latestInvitePayload();
        $this->assertSame(['Shared@Example.com'], $payload['emails'] ?? null);
    }

    /** @test */
    public function invitation_sends_to_parent_when_student_email_is_empty(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => '']);
        $this->attachParent($student, 'only-parent@example.com');
        $template = $this->makeTemplateWithNullEmailFields();

        $this->postContractFromTemplate($student, $template)->assertStatus(302);

        Mail::assertSent(ContractClientFillInvitationMail::class, 1);
        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) {
            return $mail->hasTo('only-parent@example.com');
        });
    }

    /** @test */
    public function invitation_sends_nothing_when_student_and_parent_emails_are_empty(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => '']);
        $this->attachParent($student, null);
        $template = $this->makeTemplateWithNullEmailFields();

        $this->postContractFromTemplate($student, $template)->assertStatus(302);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('contracts', [
            'user_id' => $student->id,
            'status'  => Contract::STATUS_AWAITING_CLIENT_FILL,
        ]);

        $payload = $this->latestInvitePayload();
        $this->assertSame([], $payload['emails'] ?? null);
        $this->assertArrayHasKey('email', $payload);
        $this->assertNull($payload['email']);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function makeStudent(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ], $attrs));
    }

    private function attachParent(User $student, ?string $email): ParentProfile
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => $email,
        ]);
        $student->forceFill(['parent_id' => $parent->id])->save();

        return $parent;
    }

    private function postContractFromTemplate(User $student, ContractTemplate $template)
    {
        return $this->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function latestInvitePayload(): array
    {
        $event = ContractEvent::query()
            ->where('type', 'client_invited_to_fill')
            ->latest('id')
            ->firstOrFail();

        $payload = json_decode((string) ($event->payload_json ?? ''), true);

        return is_array($payload) ? $payload : [];
    }

    private function makeTemplateWithNullEmailFields(): ContractTemplate
    {
        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Без кастомного письма',
            'is_archived' => false,
        ]);

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => 'contract-templates/test.docx',
            'docx_sha256'          => str_repeat('a', 64),
            'fields_schema'        => [
                ['key' => 'parent_full_name', 'label' => 'ФИО', 'required' => true, 'prefill_source' => null],
            ],
            'email_subject'   => null,
            'email_body_html' => null,
        ]);

        $template->current_version_id = $version->id;
        $template->save();

        return $template->fresh();
    }
}
