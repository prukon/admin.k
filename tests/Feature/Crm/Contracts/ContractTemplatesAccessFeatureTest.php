<?php

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Facades\Auth;

class ContractTemplatesAccessFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function guest_cannot_access_contract_templates_section(): void
    {
        Auth::logout();

        $this->get(route('contract-templates.index'))->assertStatus(302);
        $this->get(route('contract-templates.create'))->assertStatus(302);

        $this->getJson(route('contract-templates.data', ['draw' => 1]))->assertStatus(401);
        $this->getJson(route('contract-templates.columns-settings.get'))->assertStatus(401);
    }

    /** @test */
    public function templates_section_forbidden_without_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('contract-templates.index'))
            ->assertStatus(403);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('contract-templates.create'))
            ->assertStatus(403);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson(route('contract-templates.data', ['draw' => 1]))
            ->assertStatus(403);
    }

    /** @test */
    public function templates_section_endpoints_return_ok_with_contracts_view(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.index'))->assertOk();
        $this->get(route('contract-templates.create'))
            ->assertRedirect(route('contract-templates.index', ['create' => 1]));
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('createContractTemplateModal', false);
        $this->get(route('contract-templates.edit', $template))
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));
        $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->assertSee('editContractTemplateModal', false);

        $this->getJson(route('contract-templates.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk();
        $this->getJson(route('contract-templates.columns-settings.get'))->assertOk();
        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => ['title' => true, 'actions' => true],
        ])->assertOk();

        $this->put(route('contract-templates.update', $template), [
            'title'          => 'Обновлённый шаблон',
            'email_subject'  => 'Новая тема',
            'email_body_html'=> '<p>Новый текст</p>',
            'fields'         => [
                [
                    'key'            => 'fio_parent',
                    'label'          => 'ФИО',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
        ])->assertRedirect(route('contract-templates.index'));

        $this->get(route('contract-templates.download-docx', $template))->assertOk();
    }

    /** @test */
    public function store_template_redirects_to_index_with_contracts_view(): void
    {
        $resp = $this->post(route('contract-templates.store'), [
            'title'         => 'Новый шаблон доступа',
            'docx'          => $this->fakeDocxUploadedFile(['client_name']),
            'email_subject' => 'Тема',
        ]);

        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect(route('contract-templates.index'));
        $this->followRedirects($resp)
            ->assertOk()
            ->assertSee('Новый шаблон доступа', false);
    }

    /** @test */
    public function foreign_partner_cannot_edit_template(): void
    {
        $template = $this->createContractTemplateWithVersion([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->asForeignUser()
            ->get(route('contract-templates.edit', $template))
            ->assertStatus(403);
    }
}
