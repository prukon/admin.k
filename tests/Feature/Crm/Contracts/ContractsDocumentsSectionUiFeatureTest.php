<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Новый UI раздела «Документы»: вкладки, тулбар, модалки шаблонов, создание договора без шаблонов.
 */
class ContractsDocumentsSectionUiFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function contracts_index_renders_tabs_and_new_toolbar(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertViewIs('contracts.index')
            ->assertViewHas('activeTab', 'contracts')
            ->assertSee('id="contractsSectionTabs"', false)
            ->assertSee('>Договоры</a>', false)
            ->assertSee('>Шаблоны</a>', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('>Договоры</h1>', false)
            ->assertSee('id="contractsReportFiltersCollapse"', false)
            ->assertSee('id="contractsColumnsDropdown"', false)
            ->assertSee('id="contracts-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('js-dt-nav-link', false)
            ->assertSee('data-href="/client-contracts/', false)
            ->assertSee('<th>№</th>', false);
    }

    /** @test */
    public function templates_index_renders_tabs_toolbar_datatable_and_create_modal(): void
    {
        $this->createContractTemplateWithVersion(['title' => 'UI Шаблон списка']);

        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertViewIs('contract-templates.index')
            ->assertViewHas('activeTab', 'templates')
            ->assertSee('id="contractsSectionTabs"', false)
            ->assertSee('nav-link active', false)
            ->assertSee('>Шаблоны</a>', false)
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('Шаблоны договоров', false)
            ->assertSee('id="createContractTemplateModal"', false)
            ->assertSee('Добавить шаблон', false)
            ->assertSee('id="contract-templates-table"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('<th>№</th>', false)
            ->assertSee('id="editContractTemplateModal"', false)
            ->assertSee('js-contract-template-edit-link', false)
            ->assertSee('data-edit-show-url-template', false);
    }

    /** @test */
    public function templates_create_route_redirects_to_index_with_create_flag(): void
    {
        $this->get(route('contract-templates.create'))
            ->assertRedirect(route('contract-templates.index', ['create' => 1]));

        $this->get(route('contract-templates.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('id="createContractTemplateModal"', false)
            ->assertSee('shouldOpenCreateModal = true', false)
            ->assertSee('shouldOpenEditModal = false', false);
    }

    /** @test */
    public function templates_edit_route_redirects_to_index_with_edit_flag_and_modal_form(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'UI Редактируемый']);

        $this->get(route('contract-templates.edit', $template))
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertViewHas('editTemplate')
            ->assertSee('id="editContractTemplateModal"', false)
            ->assertSee('id="contractTemplateEditForm"', false)
            ->assertSee('id="fields-editor-card"', false)
            ->assertSee('UI Редактируемый', false)
            ->assertSee('shouldOpenEditModal = true', false)
            ->assertSee('shouldOpenCreateModal = false', false);
    }

    /** @test */
    public function store_template_validation_errors_return_to_index_for_create_modal(): void
    {
        $response = $this->from(route('contract-templates.index'))
            ->post(route('contract-templates.store'), [
                'title' => '',
            ]);

        $response->assertSessionHasErrors('title');
        $response->assertRedirect(route('contract-templates.index'));

        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('id="createContractTemplateModal"', false)
            ->assertSee('shouldOpenCreateModal = true', false);
    }

    /** @test */
    public function update_template_validation_errors_return_to_index_with_edit_modal(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $response = $this->from(route('contract-templates.index', ['edit' => $template->id]))
            ->put(route('contract-templates.update', $template), [
                'title' => '',
                'fields' => [
                    [
                        'key'      => 'parent_full_name',
                        'label'    => 'ФИО',
                        'required' => true,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('title');
        $response->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertSee('id="editContractTemplateModal"', false)
            ->assertSee('shouldOpenEditModal = true', false);
    }

    /** @test */
    public function store_template_redirects_to_templates_index_with_success_message(): void
    {
        $response = $this->post(route('contract-templates.store'), [
            'title'         => 'UI Новый из модалки',
            'docx'          => $this->fakeDocxUploadedFile(['parent_full_name']),
            'email_subject' => 'Тема',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('contract-templates.index'));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Шаблон «UI Новый из модалки» создан.', false)
            ->assertSee('UI Новый из модалки', false);
    }

    /** @test */
    public function update_template_redirects_to_templates_index_with_success_message(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'До сохранения']);

        $response = $this->put(route('contract-templates.update', $template), [
            'title'           => 'После сохранения',
            'email_subject'   => 'Тема',
            'email_body_html' => '<p>Текст</p>',
            'fields'          => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('contract-templates.index'));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Шаблон «После сохранения» сохранён.', false)
            ->assertSee('После сохранения', false);
    }

    /** @test */
    public function create_contract_page_shows_warning_instead_of_template_select_when_no_templates(): void
    {
        ContractTemplate::query()->where('partner_id', $this->partner->id)->delete();

        $this->get(route('contracts.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('Шаблонов нет.', false)
            ->assertSee(route('contract-templates.index', ['create' => 1]), false)
            ->assertSee('const hasContractTemplates = false', false)
            ->assertDontSee('id="contract_template_id"', false);
    }

    /** @test */
    public function create_contract_page_shows_template_select_when_templates_exist(): void
    {
        $this->createContractTemplateWithVersion(['title' => 'Выбираемый шаблон']);

        $this->get(route('contracts.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('id="contract_template_id"', false)
            ->assertSee('Выбираемый шаблон', false)
            ->assertSee('const hasContractTemplates = true', false)
            ->assertDontSee('Шаблонов нет.', false);
    }

    /** @test */
    public function templates_datatable_json_provides_edit_link_for_row(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.edit_url', route('contract-templates.index', ['edit' => $template->id]));
    }
}
