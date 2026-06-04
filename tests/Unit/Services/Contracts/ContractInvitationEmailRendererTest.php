<?php

namespace Tests\Unit\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\User;
use App\Services\Contracts\ContractInvitationEmailRenderer;
use App\Services\Contracts\ContractTemplateEmailDefaults;
use Carbon\Carbon;
use Tests\Feature\Crm\Contracts\ContractsFeatureTestCase;

class ContractInvitationEmailRendererTest extends ContractsFeatureTestCase
{
    private ContractInvitationEmailRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ContractInvitationEmailRenderer();
        Carbon::setLocale('ru');
    }

    /** @test */
    public function it_renders_system_defaults_when_version_email_fields_are_null(): void
    {
        [$contract, $student] = $this->makeContractWithVersion(emailSubject: null, emailBody: null);

        $student->name = 'Пётр';
        $student->lastname = 'Иванов';
        $student->save();

        $subject = $this->renderer->renderSubject($contract, $student);
        $body = $this->renderer->renderBodyHtml($contract, $student);

        $this->assertStringContainsString('Договор для Иванов Пётр — в личном кабинете', $subject);
        $this->assertStringContainsString('KidsCRM.online', $subject);
        $this->assertStringNotContainsString('{{child_full_name}}', $subject);

        $this->assertStringContainsString('подготовлен договор', $body);
        $this->assertStringContainsString('Пожалуйста, заполните до', $body);
        $this->assertStringContainsString('href="' . url('/account-settings/documents') . '"', $body);
        $this->assertStringContainsString('Номер договора в системе: ' . $contract->id, $body);
        $this->assertStringNotContainsString('{{partner_name}}', $body);
    }

    /** @test */
    public function it_uses_custom_template_and_substitutes_placeholders_in_subject_and_body(): void
    {
        [$contract, $student] = $this->makeContractWithVersion(
            emailSubject: 'Для {{child_full_name}} до {{fill_deadline}}',
            emailBody: '<p>{{partner_name}} — <a href="{{documents_url}}">ссылка</a></p>',
        );

        $subject = $this->renderer->renderSubject($contract, $student);
        $body = $this->renderer->renderBodyHtml($contract, $student);

        $this->assertMatchesRegularExpression('/Для .+ до \d+ \w+ \d{4}/u', $subject);
        $this->assertStringContainsString((string) $this->partner->title, $body);
        $this->assertStringContainsString(url('/account-settings/documents'), $body);
    }

    /** @test */
    public function version_resolved_methods_treat_blank_strings_as_system_default(): void
    {
        $version = new ContractTemplateVersion([
            'email_subject'   => '   ',
            'email_body_html' => '',
        ]);

        $this->assertSame(ContractTemplateEmailDefaults::subject(), $version->resolvedEmailSubject());
        $this->assertSame(ContractTemplateEmailDefaults::bodyHtml(), $version->resolvedEmailBodyHtml());
    }

    /**
     * @return array{0: Contract, 1: User}
     */
    private function makeContractWithVersion(?string $emailSubject, ?string $emailBody): array
    {
        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Шаблон',
            'is_archived' => false,
        ]);

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => 'contract-templates/test.docx',
            'docx_sha256'          => str_repeat('a', 64),
            'fields_schema'        => [],
            'email_subject'        => $emailSubject,
            'email_body_html'      => $emailBody,
        ]);

        $template->current_version_id = $version->id;
        $template->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Анна',
            'lastname'   => 'Смирнова',
        ]);

        $contract = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $version->id,
            'fill_expires_at'              => Carbon::parse('2026-06-10 12:00:00'),
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'provider'                     => 'podpislon',
        ]);

        $contract->load('templateVersion.template.partner');

        return [$contract, $student];
    }
}
