<?php

namespace Tests\Unit\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ParentProfile;
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
        $this->assertStringNotContainsString('Петрович', $subject);
        $this->assertStringContainsString('KidsCRM.online', $subject);
        $this->assertStringNotContainsString('{{child_full_name}}', $subject);

        $this->assertStringContainsString('Здравствуйте, Иванов Пётр!', $body);
        $this->assertStringContainsString('подготовлен договор', $body);
        $this->assertStringNotContainsString('{{addressee_name}}', $body);
        $this->assertStringContainsString('Пожалуйста, заполните до', $body);
        $expectedDocumentsUrl = $this->renderer->documentsUrl($contract, $student);
        $this->assertStringContainsString('student=' . $student->id, $expectedDocumentsUrl);
        $this->assertStringContainsString('fill=' . $contract->id, $expectedDocumentsUrl);
        $this->assertStringContainsString('href="' . e($expectedDocumentsUrl) . '"', $body);
        $this->assertStringContainsString('Номер договора в системе: ' . $contract->id, $body);
        $this->assertStringNotContainsString('{{partner_name}}', $body);
    }

    /** @test */
    public function it_includes_child_patronymic_in_subject_and_body_when_present(): void
    {
        [$contract, $student] = $this->makeContractWithVersion(emailSubject: null, emailBody: null);

        $student->name = 'Пётр';
        $student->lastname = 'Иванов';
        $student->middlename = 'Петрович';
        $student->save();

        $subject = $this->renderer->renderSubject($contract, $student);
        $body = $this->renderer->renderBodyHtml($contract, $student);

        $this->assertStringContainsString('Договор для Иванов Пётр Петрович — в личном кабинете', $subject);
        $this->assertStringContainsString('Здравствуйте, Иванов Пётр Петрович!', $body);
        $this->assertStringContainsString('Иванов Пётр Петрович', $body);
    }

    /** @test */
    public function it_addresses_parent_full_name_and_keeps_child_name_in_the_body(): void
    {
        [$contract, $student] = $this->makeContractWithVersion(emailSubject: null, emailBody: null);

        $student->name = 'Пётр';
        $student->lastname = 'Иванов';
        $student->middlename = 'Петрович';
        $student->save();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванова',
            'firstname'  => 'Мария',
            'middlename' => 'Сергеевна',
        ]);
        $student->forceFill(['parent_id' => $parent->id])->save();
        $student->unsetRelation('parentProfile');

        $subject = $this->renderer->renderSubject($contract, $student->fresh());
        $body = $this->renderer->renderBodyHtml($contract, $student->fresh());

        $this->assertStringContainsString('Договор для Иванова Мария Сергеевна — в личном кабинете', $subject);
        $this->assertStringNotContainsString('Пётр', $subject);
        $this->assertStringContainsString('Здравствуйте, Иванова Мария Сергеевна!', $body);
        $this->assertStringContainsString('Иванов Пётр Петрович', $body);
    }

    /** @test */
    public function it_addresses_the_client_when_parent_full_name_is_empty(): void
    {
        [$contract, $student] = $this->makeContractWithVersion(
            emailSubject: 'Здравствуйте, {{addressee_name}}',
            emailBody: '<p>{{addressee_name}}</p>',
        );

        $student->name = 'Пётр';
        $student->lastname = 'Иванов';
        $student->save();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => ' ',
            'firstname'  => '',
            'middlename' => null,
        ]);
        $student->forceFill(['parent_id' => $parent->id])->save();

        $subject = $this->renderer->renderSubject($contract, $student->fresh());
        $body = $this->renderer->renderBodyHtml($contract, $student->fresh());

        $this->assertSame('Здравствуйте, Иванов Пётр', $subject);
        $this->assertStringContainsString('Иванов Пётр', $body);
        $this->assertStringNotContainsString('{{addressee_name}}', $body);
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
        $expectedDocumentsUrl = $this->renderer->documentsUrl($contract, $student);
        $this->assertStringContainsString(e($expectedDocumentsUrl), $body);
        $this->assertStringContainsString('student=' . $student->id, $expectedDocumentsUrl);
        $this->assertStringContainsString('fill=' . $contract->id, $expectedDocumentsUrl);
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
