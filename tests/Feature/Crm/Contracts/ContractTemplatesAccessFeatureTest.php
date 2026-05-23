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
    }

    /** @test */
    public function templates_section_endpoints_return_ok_with_contracts_view(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.index'))->assertOk();
        $this->get(route('contract-templates.create'))->assertOk();
        $this->get(route('contract-templates.edit', $template))->assertOk();

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
        ])->assertRedirect(route('contract-templates.edit', $template));

        $this->get(route('contract-templates.download-docx', $template))->assertOk();
    }

    /** @test */
    public function store_template_redirects_to_edit_with_contracts_view(): void
    {
        $resp = $this->post(route('contract-templates.store'), [
            'title'         => 'Новый шаблон доступа',
            'docx'          => $this->fakeDocxUploadedFile(['client_name']),
            'email_subject' => 'Тема',
        ]);

        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();
        $this->followRedirects($resp)->assertOk();
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
