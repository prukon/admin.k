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
        $this->assertSame('parent_full_name', $schema[0]['key'] ?? null);
        $this->assertSame('Родитель: ФИО', $schema[0]['label'] ?? null);
        $this->assertNotEmpty($template->currentVersion->docx_path);
    }

    /** @test */
    public function variables_reference_is_shown_in_create_modal_on_index(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('contract-template-variables-reference', false)
            ->assertSee('parent_full_name', false);
    }
}
