<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplateVersion;
use Illuminate\Support\Carbon;
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
    public function show_edit_json_lists_each_docx_version_author_and_date(): void
    {
        $this->user->forceFill([
            'lastname' => 'Иванов',
            'name'     => 'Иван',
        ])->save();

        $template = $this->createContractTemplateWithVersion([
            'title' => 'JSON Edit Template',
        ], [
            'created_by' => $this->user->id,
        ]);

        $firstVersion = $template->currentVersion;
        $firstVersion->created_at = Carbon::parse('2026-06-12 09:41:00');
        $firstVersion->save();

        $secondVersion = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 2,
            'docx_path'            => $firstVersion->docx_path,
            'docx_sha256'          => $firstVersion->docx_sha256,
            'fields_schema'        => $firstVersion->fields_schema,
            'created_by'           => null,
        ]);
        $secondVersion->created_at = Carbon::parse('2026-09-30 15:18:00');
        $secondVersion->save();

        $template->current_version_id = $secondVersion->id;
        $template->save();

        $html = $this->getJson(route('contract-templates.edit', $template))
            ->assertOk()
            ->json('html');

        $newer = 'v2 · — · 30.09.2026 15:18';
        $older = 'v1 · Иванов Иван · 12.06.2026 09:41';

        $this->assertStringContainsString($newer, $html);
        $this->assertStringContainsString($older, $html);
        $this->assertLessThan(strpos($html, $older), strpos($html, $newer));
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
