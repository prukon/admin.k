<?php

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Полный контроль доступа к странице шаблонов и всем связанным эндпоинтам (DataTable, колонки, CRUD).
 */
class ContractTemplatesIndexFullAccessFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function guest_cannot_access_templates_index_and_json_endpoints(): void
    {
        Auth::logout();

        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.index'))->assertStatus(302);
        $this->get(route('contract-templates.create'))->assertStatus(302);
        $this->get(route('contract-templates.edit', $template))->assertStatus(302);

        $this->getJson(route('contract-templates.data', ['draw' => 1]))->assertStatus(401);
        $this->getJson(route('contract-templates.columns-settings.get'))->assertStatus(401);
        $this->getJson(route('logs.data.contract-template', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertStatus(401);
        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => ['title' => true],
        ])->assertStatus(401);
    }

    /** @test */
    public function user_without_contracts_view_gets_403_on_templates_page_and_all_endpoints(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contract-templates.index'))->assertStatus(403);
        $this->get(route('contract-templates.index', ['create' => 1]))->assertStatus(403);
        $this->get(route('contract-templates.index', ['edit' => $template->id]))->assertStatus(403);
        $this->get(route('contract-templates.create'))->assertStatus(403);
        $this->get(route('contract-templates.edit', $template))->assertStatus(403);

        $this->getJson(route('contract-templates.data', ['draw' => 1]))->assertStatus(403);
        $this->getJson(route('contract-templates.columns-settings.get'))->assertStatus(403);
        $this->getJson(route('logs.data.contract-template', ['draw' => 1]))->assertStatus(403);
        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => ['title' => true],
        ])->assertStatus(403);

        $this->post(route('contract-templates.store'), [
            'title' => 'Forbidden',
            'docx'  => $this->fakeDocxUploadedFile(),
        ])->assertStatus(403);

        $this->put(route('contract-templates.update', $template), [
            'title' => 'Forbidden',
        ])->assertStatus(403);

        $this->get(route('contract-templates.download-docx', $template))->assertStatus(403);
    }

    /** @test */
    public function user_with_contracts_view_gets_200_on_templates_index_page_variants(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'Access Page Template']);

        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertViewIs('contract-templates.index')
            ->assertViewHas('activeTab', 'templates')
            ->assertViewHas('prefillSources')
            ->assertViewHas('editTemplate', null)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false);

        $this->get(route('contract-templates.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('id="createContractTemplateModal"', false);

        $this->get(route('contract-templates.create'))
            ->assertRedirect(route('contract-templates.index', ['create' => 1]));

        $this->get(route('contract-templates.edit', $template))
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));

        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertViewHas('editTemplate')
            ->assertSee('id="editContractTemplateModal"', false);
    }

    /** @test */
    public function user_with_contracts_view_gets_200_on_all_templates_ajax_and_mutation_endpoints(): void
    {
        Storage::fake();

        $template = $this->createContractTemplateWithVersion(['title' => 'Access Ajax Template']);
        Storage::put($template->currentVersion->docx_path, $this->minimalDocxBytes());

        $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('contract-templates.data', [
            'draw'          => 1,
            'start'         => 0,
            'length'        => 10,
            'search'        => ['value' => 'Access Ajax'],
            'order'         => [['column' => 1, 'dir' => 'asc']],
            'columns'       => [
                ['name' => 'id'],
                ['name' => 'title'],
                ['name' => 'version'],
                ['name' => 'fields_count'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
        ]))->assertOk();

        $this->getJson(route('contract-templates.columns-settings.get'))->assertOk();

        $this->getJson(route('logs.data.contract-template', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => [
                'id'           => true,
                'title'        => true,
                'version'      => false,
                'fields_count' => true,
                'status_label' => true,
                'actions'      => true,
            ],
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->get(route('contract-templates.download-docx', $template))->assertOk();

        $store = $this->post(route('contract-templates.store'), [
            'title'         => 'Access Stored Template',
            'docx'          => $this->fakeDocxUploadedFile(['parent_full_name']),
            'email_subject' => 'Тема',
        ]);
        $store->assertSessionHasNoErrors();
        $store->assertRedirect(route('contract-templates.index'));
        $this->followRedirects($store)->assertOk();

        $this->put(route('contract-templates.update', $template), [
            'title'           => 'Access Updated Template',
            'email_subject'   => 'Новая тема',
            'email_body_html' => '<p>Новый текст</p>',
            'fields'          => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-templates.index'));
    }

    /** @test */
    public function dedicated_contracts_view_user_can_access_templates_index_and_json(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contract-templates.index'))->assertOk();
        $this->getJson(route('contract-templates.data', ['draw' => 1, 'start' => 0, 'length' => 5]))->assertOk();
        $this->getJson(route('contract-templates.columns-settings.get'))->assertOk();
        $this->getJson(route('logs.data.contract-template', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk();
        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => ['title' => true],
        ])->assertOk();
    }
}
