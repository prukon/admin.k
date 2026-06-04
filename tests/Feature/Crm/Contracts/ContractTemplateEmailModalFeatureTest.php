<?php

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Facades\Auth;

/**
 * Модалка «Письмо клиенту» для шаблона: JSON API, валидация, доступ.
 */
class ContractTemplateEmailModalFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function show_email_json_returns_full_payload_for_modal(): void
    {
        $template = $this->createContractTemplateWithVersion([], [
            'email_subject'   => 'Тема {{child_full_name}}',
            'email_body_html' => '<p>Текст для {{partner_name}}</p>',
        ]);

        $this->getJson(route('contract-templates.email.show', $template))
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'title',
                'email_subject',
                'email_body_html',
                'update_url',
            ])
            ->assertJsonPath('id', $template->id)
            ->assertJsonPath('title', $template->title)
            ->assertJsonPath('update_url', route('contract-templates.update-email', $template));
    }

    /** @test */
    public function update_email_json_validates_max_length_and_returns_422(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => str_repeat('а', 256),
            'email_body_html' => '<p>ok</p>',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email_subject']);
    }

    /** @test */
    public function update_email_json_validates_body_max_length(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Тема',
            'email_body_html' => str_repeat('x', 50001),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email_body_html']);
    }

    /** @test */
    public function guest_cannot_access_email_endpoints(): void
    {
        Auth::logout();

        $template = $this->createContractTemplateWithVersion();

        $this->getJson(route('contract-templates.email.show', $template))->assertUnauthorized();
        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject' => 'Hack',
        ])->assertUnauthorized();
    }

    /** @test */
    public function user_without_contracts_view_cannot_access_email_endpoints(): void
    {
        $template = $this->createContractTemplateWithVersion();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('contract-templates.email.show', $template))->assertForbidden();
        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject' => 'Hack',
        ])->assertForbidden();
    }

    /** @test */
    public function foreign_partner_cannot_access_email_endpoints(): void
    {
        $template = $this->createContractTemplateWithVersion([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->asForeignUser()
            ->getJson(route('contract-templates.email.show', $template))
            ->assertForbidden();

        $this->asForeignUser()
            ->putJson(route('contract-templates.update-email', $template), [
                'email_subject' => 'Hack',
            ])
            ->assertForbidden();
    }

    /** @test */
    public function templates_index_with_email_and_edit_prefers_edit_modal_flag(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.index', [
            'edit'  => $template->id,
            'email' => $template->id,
        ]))
            ->assertOk()
            ->assertViewHas('editTemplate')
            ->assertViewHas('openEmailTemplateId', $template->id)
            ->assertSee('shouldOpenEditModal = true', false);
    }
}
