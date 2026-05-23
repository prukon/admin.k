<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplate;

class ContractTemplatesFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function templates_index_requires_permission(): void
    {
        $this->get('/client-contract-templates')->assertStatus(200);
    }

    /** @test */
    public function store_template_parses_placeholders_and_creates_version(): void
    {
        $docx = $this->fakeDocxUploadedFile();

        $resp = $this->post('/client-contract-templates', [
            'title' => 'Договор оферты',
            'docx'  => $docx,
            'email_subject' => 'Заполните договор',
        ]);

        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();

        $template = ContractTemplate::query()->where('partner_id', $this->partner->id)->firstOrFail();
        $this->assertSame($this->partner->id, (int) $template->partner_id);
        $this->assertNotNull($template->current_version_id);

        $template->load('currentVersion');
        $schema = $template->currentVersion->fields_schema;
        $this->assertIsArray($schema);
        $this->assertNotEmpty($schema);
        $this->assertSame('fio_parent', $schema[0]['key'] ?? null);
        $this->assertNotEmpty($template->currentVersion->docx_path);
    }
}
