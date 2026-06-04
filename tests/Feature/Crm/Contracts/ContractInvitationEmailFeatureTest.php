<?php

namespace Tests\Feature\Crm\Contracts;

use App\Mail\ContractClientFillInvitationMail;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ContractInvitationEmailFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function template_mode_sends_email_with_system_defaults_when_version_has_null_email_fields(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 0]);
        $this->partner->wallet_balance = 100;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'email'      => 'parent@example.com',
            'name'       => 'Мария',
            'lastname'   => 'Козлова',
        ]);

        $template = $this->makeTemplateWithNullEmailFields();

        $this->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertStatus(302);

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
