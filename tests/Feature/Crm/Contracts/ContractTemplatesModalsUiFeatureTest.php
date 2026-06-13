<?php

namespace Tests\Feature\Crm\Contracts;

/**
 * UI модалок раздела «Шаблоны договоров»: создание, редактирование, письмо клиенту.
 */
class ContractTemplatesModalsUiFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function templates_index_renders_create_modal_with_form_and_variables_reference(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('id="createContractTemplateModal"', false)
            ->assertSee('id="contractTemplateCreateForm"', false)
            ->assertSee('id="template-create-title"', false)
            ->assertSee('id="template-create-docx"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="docx"', false)
            ->assertSee('contract-template-variables-reference', false)
            ->assertSee('shouldOpenCreateModal = false', false)
            ->assertSee('shouldOpenEditModal = false', false)
            ->assertSee('id="editContractTemplateModal"', false)
            ->assertSee('data-edit-show-url-template', false);
    }

    /** @test */
    public function templates_index_with_create_flag_opens_create_modal(): void
    {
        $this->get(route('contract-templates.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('shouldOpenCreateModal = true', false)
            ->assertSee('shouldOpenEditModal = false', false);
    }

    /** @test */
    public function templates_index_with_edit_flag_renders_edit_modal_and_fields_editor(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'UI Редактируемый шаблон']);

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertViewHas('editTemplate')
            ->assertSee('id="editContractTemplateModal"', false)
            ->assertSee('id="contractTemplateEditForm"', false)
            ->assertSee('id="template-edit-title"', false)
            ->assertSee('id="template-edit-activity"', false)
            ->assertSee('id="fields-editor-card"', false)
            ->assertSee('id="contract-template-fields-table"', false)
            ->assertSee('contract-template-docx-update-panel', false)
            ->assertSee('UI Редактируемый шаблон', false)
            ->assertSee('shouldOpenEditModal = true', false)
            ->assertSee('shouldOpenCreateModal = false', false);
    }

    /** @test */
    public function templates_index_renders_email_edit_modal_shell(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('id="editTemplateEmailModal"', false)
            ->assertSee('id="contractTemplateEmailForm"', false)
            ->assertSee('id="template-email-subject"', false)
            ->assertSee('id="template-email-body"', false)
            ->assertSee('js-contract-template-edit-email', false)
            ->assertSee('data-email-show-url-template', false);
    }

    /** @test */
    public function templates_index_with_email_query_sets_open_email_template_id(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.index', ['email' => $template->id]))
            ->assertOk()
            ->assertViewHas('openEmailTemplateId', $template->id);
    }

    /** @test */
    public function store_validation_reopens_create_modal_on_index(): void
    {
        $response = $this->from(route('contract-templates.index', ['create' => 1]))
            ->post(route('contract-templates.store'), ['title' => '']);

        $response->assertSessionHasErrors('docx')
            ->assertRedirect(route('contract-templates.index'));

        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('shouldOpenCreateModal = true', false);
    }

    /** @test */
    public function update_validation_reopens_edit_modal_on_index(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $response = $this->from(route('contract-templates.index', ['edit' => $template->id]))
            ->put(route('contract-templates.update', $template), ['title' => '']);

        $response->assertSessionHasErrors('title')
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertSee('shouldOpenEditModal = true', false);
    }
}
