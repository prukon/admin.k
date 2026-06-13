<?php

namespace Tests\Feature\Crm\Contracts;

use App\Services\Contracts\ContractTemplateEmailDefaults;

class ContractTemplateEmailFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function show_email_returns_resolved_defaults_for_null_version_fields(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'email_subject'   => null,
            'email_body_html' => null,
        ]);

        $response = $this->getJson(route('contract-templates.email.show', $template));

        $response->assertOk()
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('email_subject', ContractTemplateEmailDefaults::subject())
            ->assertJsonPath('update_url', route('contract-templates.update-email', $template));
    }

    /** @test */
    public function update_email_persists_subject_and_body(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $response = $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Тема из модалки',
            'email_body_html' => '<p>Текст из модалки</p>',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', fn ($msg) => is_string($msg) && $msg !== '');

        $version = $template->fresh()->currentVersion;
        $this->assertSame('Тема из модалки', $version->email_subject);
        $this->assertSame('<p>Текст из модалки</p>', $version->email_body_html);
    }

    /** @test */
    public function update_email_with_empty_strings_stores_null(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'email_subject'   => 'Было',
            'email_body_html' => '<p>Было</p>',
        ]);

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => '   ',
            'email_body_html' => '',
        ])->assertOk();

        $version = $template->fresh()->currentVersion;
        $this->assertNull($version->email_subject);
        $this->assertNull($version->email_body_html);
        $this->assertStringContainsString('KidsCRM.online', $version->resolvedEmailSubject());
    }

    /** @test */
    public function main_template_update_does_not_change_email_anymore(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'email_subject'   => 'Исходная тема',
            'email_body_html' => '<p>Исходный текст</p>',
        ]);

        $this->put(route('contract-templates.update', $template), [
            'title'  => 'Новое название',
            'fields' => [
                [
                    'key'      => 'parent_full_name',
                    'label'    => 'ФИО',
                    'required' => true,
                ],
            ],
        ])->assertRedirect(route('contract-templates.index'));

        $version = $template->fresh()->currentVersion;
        $this->assertSame('Новое название', $template->fresh()->title);
        $this->assertSame('Исходная тема', $version->email_subject);
        $this->assertSame('<p>Исходный текст</p>', $version->email_body_html);
    }

    /** @test */
    public function templates_index_shows_email_edit_button_in_datatable_actions(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('js-contract-template-edit-link', false)
            ->assertSee('js-contract-template-edit-email', false)
            ->assertSee("aria-label=\"Письмо клиенту\">", false)
            ->assertSee("+ 'Письмо'", false);
    }
}
