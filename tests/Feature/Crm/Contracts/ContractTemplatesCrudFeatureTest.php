<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Storage;

class ContractTemplatesCrudFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function update_changes_title_and_email_without_new_docx(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->put(route('contract-templates.update', $template), [
            'title'           => 'Договор v2',
            'email_subject'   => 'Обновлённая тема',
            'email_body_html' => '<p>Обновлённое письмо</p>',
            'fields'          => [
                [
                    'key'            => 'fio_parent',
                    'label'          => 'ФИО родителя (ред.)',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
        ])->assertRedirect(route('contract-templates.index'));

        $template->refresh();
        $template->load('currentVersion');

        $this->assertSame('Договор v2', $template->title);
        $this->assertSame('Обновлённая тема', $template->currentVersion->email_subject);
        $this->assertSame('ФИО родителя (ред.)', $template->currentVersion->fields_schema[0]['label'] ?? null);
    }

    /** @test */
    public function update_with_new_docx_creates_new_version(): void
    {
        $template = $this->createContractTemplateWithVersion();
        $oldVersionId = $template->current_version_id;

        $this->put(route('contract-templates.update', $template), [
            'title' => 'С новым DOCX',
            'docx'  => $this->fakeDocxUploadedFile(['passport']),
        ])->assertRedirect(route('contract-templates.index'));

        $template->refresh();
        $template->load('currentVersion');

        $this->assertNotSame($oldVersionId, $template->current_version_id);
        $this->assertSame(2, (int) $template->currentVersion->version);
        $keys = array_column($template->currentVersion->fields_schema ?? [], 'key');
        $this->assertContains('passport', $keys);
    }

    /** @test */
    public function download_docx_returns_file_for_current_partner(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $resp = $this->get(route('contract-templates.download-docx', $template));

        $resp->assertOk();
        $resp->assertHeader('content-disposition');
        $this->assertStringContainsString('.docx', (string) $resp->headers->get('content-disposition'));
    }

    /** @test */
    public function archive_flag_persisted_on_update(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->put(route('contract-templates.update', $template), [
            'title'        => $template->title,
            'is_archived'  => '1',
            'fields'       => [
                [
                    'key'      => 'fio_parent',
                    'label'    => 'ФИО',
                    'required' => true,
                ],
            ],
        ])->assertRedirect();

        $this->assertTrue($template->fresh()->is_archived);
    }

    /** @test */
    public function index_lists_only_current_partner_templates(): void
    {
        $mine = $this->createContractTemplateWithVersion(['title' => 'Шаблон нашего партнёра']);

        ContractTemplate::create([
            'partner_id'  => $this->foreignPartner->id,
            'title'       => 'Чужой шаблон',
            'is_archived' => false,
        ]);

        $response = $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]));

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains($mine->title, $titles);
        $this->assertNotContains('Чужой шаблон', $titles);
    }
}
