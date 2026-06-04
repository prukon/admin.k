<?php

namespace Tests\Feature\Crm\Contracts;

use App\Mail\ContractClientFillInvitationMail;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\User;
use App\Services\Contracts\ContractTemplateEmailDefaults;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;

/**
 * Передача шаблона родителю: создание договора админом, email, заполнение в кабинете.
 */
class ContractTemplateToParentWorkflowFeatureTest extends ContractsFeatureTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        config(['contracts.pdf_converter' => 'fake']);
        config(['queue.default' => 'sync']);

        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    /** @test */
    public function admin_create_template_contract_links_student_and_sends_invitation_email(): void
    {
        Mail::fake();

        $student = $this->makeStudent([
            'email'    => 'parent-workflow@example.com',
            'lastname' => 'Смирнов',
            'name'     => 'Алексей',
        ]);

        $template = $this->makeUsableTemplateWithDocx();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        $contract = Contract::query()->latest('id')->firstOrFail();

        $this->assertSame($student->id, (int) $contract->user_id);
        $this->assertSame(Contract::CREATION_MODE_TEMPLATE, $contract->creation_mode);
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertSame($template->current_version_id, $contract->contract_template_version_id);
        $this->assertNull($contract->source_pdf_path);
        $this->assertNotNull($contract->fill_expires_at);

        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) use ($student, $contract) {
            return $mail->student->id === $student->id
                && $mail->contract->id === $contract->id;
        });
    }

    /** @test */
    public function invitation_email_uses_custom_template_subject_and_body_with_resolved_placeholders(): void
    {
        Mail::fake();

        $student = $this->makeStudent([
            'email'    => 'custom-mail@example.com',
            'lastname' => 'Козлова',
            'name'     => 'Мария',
        ]);

        $template = $this->makeUsableTemplateWithDocx([
            'email_subject'   => 'Договор: {{child_full_name}} — {{partner_name}}',
            'email_body_html' => '<p>Откройте <a href="{{documents_url}}">Мои документы</a> до {{fill_deadline}}.</p>',
        ]);

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        $contract = Contract::query()->latest('id')->firstOrFail();
        $renderer = app(\App\Services\Contracts\ContractInvitationEmailRenderer::class);

        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) use ($student, $contract, $renderer) {
            $subject = $renderer->renderSubject($mail->contract, $mail->student);
            $body = $renderer->renderBodyHtml($mail->contract, $mail->student);

            $this->assertStringContainsString('Козлова', $subject);
            $this->assertStringContainsString($this->partner->title, $subject);
            $this->assertStringContainsString('/account-settings/documents', $body);
            $this->assertStringNotContainsString('{{documents_url}}', $body);
            $this->assertStringNotContainsString('{{fill_deadline}}', $body);

            return $mail->contract->id === $contract->id;
        });
    }

    /** @test */
    public function no_invitation_email_when_student_has_empty_email_but_contract_is_created(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => '']);
        $template = $this->makeUsableTemplateWithDocx();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('contracts', [
            'user_id' => $student->id,
            'status'  => Contract::STATUS_AWAITING_CLIENT_FILL,
        ]);
    }

    /** @test */
    public function create_template_contract_records_client_invited_event(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'event@example.com']);
        $template = $this->makeUsableTemplateWithDocx();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        $contract = Contract::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('contract_events', [
            'contract_id' => $contract->id,
            'type'        => 'client_invited_to_fill',
        ]);

        $event = ContractEvent::query()
            ->where('contract_id', $contract->id)
            ->where('type', 'client_invited_to_fill')
            ->first();

        $payload = json_decode((string) ($event->payload_json ?? ''), true);
        $this->assertSame('event@example.com', $payload['email'] ?? null);
    }

    /** @test */
    public function archived_template_is_rejected_on_contract_create(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'archived@example.com']);
        $template = $this->makeUsableTemplateWithDocx([], ['is_archived' => true]);

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'contract_template_id' => $template->id,
            ])
            ->assertSessionHasErrors('contract_template_id');

        Mail::assertNothingSent();
        $this->assertSame(0, Contract::query()->where('user_id', $student->id)->count());
    }

    /** @test */
    public function student_sees_awaiting_contract_on_documents_index_after_admin_create(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'visible@example.com']);
        $template = $this->makeUsableTemplateWithDocx();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        $contract = Contract::query()->latest('id')->firstOrFail();

        $this->actingAs($student)
            ->withSession($this->studentDocumentsSession())
            ->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('data-id="' . $contract->id . '"', false)
            ->assertSee('Требуется заполнение', false);
    }

    /** @test */
    public function student_can_open_fill_modal_and_generate_pdf_after_admin_create(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'fill@example.com']);
        $template = $this->makeUsableTemplateWithDocx();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        $contract = Contract::query()->latest('id')->firstOrFail();

        $html = (string) $this->actingAs($student)
            ->withSession($this->studentDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Сформировать договор', $html);
        $this->assertStringContainsString('name="fields[parent_lastname]"', $html);

        $this->actingAs($student)
            ->withSession($this->studentDocumentsSession())
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]))
            ->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNotNull($contract->source_pdf_path);
        Storage::disk()->assertExists($contract->source_pdf_path);
        $this->assertSame('Иванов Иван', $contract->filled_data['parent_full_name'] ?? null);
    }

    /** @test */
    public function fill_form_prefills_crm_fields_from_student_profile(): void
    {
        $student = $this->makeStudent([
            'email'    => 'prefill@example.com',
            'lastname' => 'Петров',
            'name'     => 'Пётр',
            'birthday' => '2018-03-15',
        ]);

        $contract = $this->makeAwaitingFillContractForStudent($student, [
            [
                'key'            => 'child_lastname',
                'label'          => 'Фамилия ребёнка',
                'required'       => true,
                'prefill_source' => \App\Services\Contracts\ContractTemplatePrefillSources::CHILD_LASTNAME,
            ],
            [
                'key'            => 'child_birthday',
                'label'          => 'Дата рождения',
                'required'       => true,
                'prefill_source' => \App\Services\Contracts\ContractTemplatePrefillSources::CHILD_BIRTHDAY,
            ],
        ], ['child_lastname', 'child_birthday']);

        $html = (string) $this->actingAs($student)
            ->withSession($this->studentDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('value="Петров"', $html);
        $this->assertStringContainsString('value="2018-03-15"', $html);
    }

    /** @test */
    public function expired_fill_returns_422_in_fill_json(): void
    {
        $student = $this->makeStudent(['email' => 'expired@example.com']);
        $contract = $this->makeAwaitingFillContractForStudent($student, [
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $contract->update(['fill_expires_at' => now()->subMinute()]);

        $this->actingAs($student)
            ->withSession($this->studentDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Срок заполнения договора истёк. Обратитесь в организацию.');
    }

    /** @test */
    public function default_invitation_email_contains_documents_url_placeholder_resolution(): void
    {
        Mail::fake();

        $student = $this->makeStudent(['email' => 'defaults@example.com', 'lastname' => 'Test', 'name' => 'User']);
        $template = $this->makeUsableTemplateWithDocx([
            'email_subject'   => null,
            'email_body_html' => null,
        ]);

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        $renderer = app(\App\Services\Contracts\ContractInvitationEmailRenderer::class);

        Mail::assertSent(ContractClientFillInvitationMail::class, function (ContractClientFillInvitationMail $mail) use ($renderer) {
            $body = $renderer->renderBodyHtml($mail->contract, $mail->student);
            $this->assertStringContainsString(ContractTemplateEmailDefaults::PLACEHOLDER_DOCUMENTS_URL, ContractTemplateEmailDefaults::bodyHtml());
            $this->assertStringContainsString('/account-settings/documents', $body);
            $this->assertStringContainsString('подготовлен договор', $body);

            return true;
        });
    }

    /**
     * @param array<string, mixed> $studentAttrs
     */
    private function makeStudent(array $studentAttrs = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ], $studentAttrs));
    }

    /**
     * @return array<string, int|bool>
     */
    private function studentDocumentsSession(): array
    {
        return [
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ];
    }

    /**
     * @param array<string, mixed> $versionOverrides
     * @param array<string, mixed> $templateOverrides
     */
    private function makeUsableTemplateWithDocx(array $versionOverrides = [], array $templateOverrides = []): ContractTemplate
    {
        $this->useAccountContractFillStorage();

        $docxPath = $this->createFillTestDocxOnDisk(['parent_full_name']);

        $template = ContractTemplate::create(array_merge([
            'partner_id'  => $this->partner->id,
            'title'       => 'Workflow шаблон',
            'is_archived' => false,
        ], $templateOverrides));

        $version = ContractTemplateVersion::create(array_merge([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => $docxPath,
            'docx_sha256'          => str_repeat('a', 64),
            'fields_schema'        => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО родителя',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
            'email_subject'   => 'Заполните договор',
            'email_body_html' => '<p>Текст приглашения</p>',
        ], $versionOverrides));

        $template->current_version_id = $version->id;
        $template->save();

        return $template->fresh(['currentVersion']);
    }

    /**
     * @param list<array<string, mixed>> $fieldsSchema
     * @param list<string> $docxPlaceholders
     */
    private function makeAwaitingFillContractForStudent(
        User $student,
        array $fieldsSchema,
        array $docxPlaceholders = ['parent_full_name'],
    ): Contract {
        $this->useAccountContractFillStorage();
        $docxPath = $this->createFillTestDocxOnDisk($docxPlaceholders);

        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Fill student template',
            'is_archived' => false,
        ]);

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => $docxPath,
            'docx_sha256'          => str_repeat('b', 64),
            'fields_schema'        => $fieldsSchema,
        ]);
        $template->current_version_id = $version->id;
        $template->save();

        return Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $student->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $version->id,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(Contract::FILL_TTL_DAYS),
            'provider'                     => 'podpislon',
        ]);
    }

    /**
     * @param list<string> $placeholders
     */
    private function createFillTestDocxOnDisk(array $placeholders): string
    {
        $inner = implode(' ', array_map(static fn (string $key): string => '{{' . $key . '}}', $placeholders));
        $rel = 'contract-templates/workflow-' . uniqid() . '.docx';
        $abs = Storage::disk()->path($rel);
        @mkdir(dirname($abs), 0775, true);

        $zip = new \ZipArchive();
        $zip->open($abs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>' . $inner . '</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        return $rel;
    }
}
