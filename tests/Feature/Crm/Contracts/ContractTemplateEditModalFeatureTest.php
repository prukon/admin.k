<?php

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Facades\Auth;

/**
 * Модалка редактирования шаблона: JSON API для открытия без перезагрузки списка.
 */
class ContractTemplateEditModalFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function show_edit_json_returns_form_html_for_modal(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'JSON Edit Template']);

        $response = $this->getJson(route('contract-templates.edit', $template))
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'title',
                'update_url',
                'html',
            ])
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('title', 'JSON Edit Template')
            ->assertJsonPath('update_url', route('contract-templates.update', $template));

        $this->assertStringContainsString('id="template-edit-title"', $response->json('html'));
        $this->assertStringContainsString('id="fields-editor-card"', $response->json('html'));
        $this->assertStringContainsString('contract-template-docx-update-panel', $response->json('html'));
    }

    /** @test */
    public function show_edit_without_ajax_redirects_to_index_with_edit_flag(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.edit', $template))
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));
    }

    /** @test */
    public function guest_cannot_access_edit_json(): void
    {
        Auth::logout();

        $template = $this->createContractTemplateWithVersion();

        $this->getJson(route('contract-templates.edit', $template))->assertUnauthorized();
    }

    /** @test */
    public function show_edit_json_returns_403_without_contracts_view(): void
    {
        $template = $this->createContractTemplateWithVersion();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('contract-templates.edit', $template))->assertStatus(403);
    }
}
