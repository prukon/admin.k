<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Контроль доступа ко всем эндпоинтам раздела «Шаблоны договоров».
 */
class ContractTemplatesEndpointsAccessFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function guest_is_denied_on_all_contract_template_endpoints(): void
    {
        Auth::logout();

        $template = $this->createContractTemplateWithVersion();

        $this->get(route('contract-templates.index'))->assertStatus(302);
        $this->get(route('contract-templates.index', ['create' => 1]))->assertStatus(302);
        $this->get(route('contract-templates.index', ['edit' => $template->id]))->assertStatus(302);
        $this->get(route('contract-templates.index', ['email' => $template->id]))->assertStatus(302);
        $this->get(route('contract-templates.create'))->assertStatus(302);
        $this->get(route('contract-templates.edit', $template))->assertStatus(302);

        $this->getJson(route('contract-templates.data', ['draw' => 1]))->assertStatus(401);
        $this->getJson(route('contract-templates.columns-settings.get'))->assertStatus(401);
        $this->getJson(route('logs.data.contract-template', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertStatus(401);
        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => ['title' => true],
        ])->assertStatus(401);

        $this->post(route('contract-templates.store'), [
            'title' => 'Guest',
            'docx'  => $this->fakeDocxUploadedFile(),
        ])->assertStatus(302);

        $this->put(route('contract-templates.update', $template), [
            'title' => 'Guest',
        ])->assertStatus(302);

        $this->get(route('contract-templates.download-docx', $template))->assertStatus(302);
        $this->getJson(route('contract-templates.email.show', $template))->assertStatus(401);
        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject' => 'Тема',
        ])->assertStatus(401);
    }

    /** @test */
    public function user_without_contracts_view_gets_403_on_every_template_endpoint(): void
    {
        $template = $this->createContractTemplateWithVersion();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contract-templates.index'))->assertStatus(403);
        $this->get(route('contract-templates.index', ['create' => 1]))->assertStatus(403);
        $this->get(route('contract-templates.index', ['edit' => $template->id]))->assertStatus(403);
        $this->get(route('contract-templates.index', ['email' => $template->id]))->assertStatus(403);
        $this->get(route('contract-templates.create'))->assertStatus(403);
        $this->get(route('contract-templates.edit', $template))->assertStatus(403);

        $this->getJson(route('contract-templates.data', ['draw' => 1]))->assertStatus(403);
        $this->getJson(route('contract-templates.columns-settings.get'))->assertStatus(403);
        $this->getJson(route('logs.data.contract-template', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertStatus(403);
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
        $this->getJson(route('contract-templates.email.show', $template))->assertStatus(403);
        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Тема',
            'email_body_html' => '<p>Текст</p>',
        ])->assertStatus(403);
    }

    /** @test */
    public function user_with_contracts_view_gets_success_on_all_read_endpoints(): void
    {
        Storage::fake();

        $template = $this->createContractTemplateWithVersion(['title' => 'Access Read Template']);
        Storage::put($template->currentVersion->docx_path, $this->minimalDocxBytes());

        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertViewIs('contract-templates.index');

        $this->get(route('contract-templates.index', ['create' => 1]))->assertOk();
        $this->get(route('contract-templates.create'))
            ->assertRedirect(route('contract-templates.index', ['create' => 1]));

        $this->get(route('contract-templates.edit', $template))
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));

        $this->get(route('contract-templates.index', ['edit' => $template->id]))->assertOk();
        $this->get(route('contract-templates.index', ['email' => $template->id]))->assertOk();

        $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('contract-templates.columns-settings.get'))->assertOk();

        $this->getJson(route('logs.data.contract-template', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('contract-templates.download-docx', $template))->assertOk();

        $this->getJson(route('contract-templates.email.show', $template))
            ->assertOk()
            ->assertJsonPath('id', $template->id);

        $this->getJson(route('contract-templates.edit', $template))
            ->assertOk()
            ->assertJsonPath('id', $template->id)
            ->assertJsonStructure(['title', 'update_url', 'html']);
    }

    /** @test */
    public function user_with_contracts_view_can_mutate_templates_via_all_write_endpoints(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'Access Write Template']);

        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => [
                'title'   => true,
                'version' => true,
                'actions' => true,
            ],
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $store = $this->post(route('contract-templates.store'), [
            'title' => 'Access Created Template',
            'docx'  => $this->fakeDocxUploadedFile(['parent_full_name']),
        ]);
        $store->assertSessionHasNoErrors();
        $store->assertRedirect(route('contract-templates.index'));
        $this->followRedirects($store)->assertOk();

        $this->put(route('contract-templates.update', $template), [
            'title'  => 'Access Updated Template',
            'fields' => [
                [
                    'key'      => 'parent_full_name',
                    'label'    => 'ФИО',
                    'required' => 1,
                ],
            ],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-templates.index'));

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Доступная тема',
            'email_body_html' => '<p>Доступный текст</p>',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);
    }

    /** @test */
    public function foreign_partner_is_denied_on_template_scoped_endpoints(): void
    {
        $foreignTemplate = $this->createContractTemplateWithVersion([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->asForeignUser()
            ->get(route('contract-templates.edit', $foreignTemplate))
            ->assertStatus(403);

        $this->asForeignUser()
            ->put(route('contract-templates.update', $foreignTemplate), [
                'title' => 'Hack',
            ])
            ->assertStatus(403);

        $this->asForeignUser()
            ->get(route('contract-templates.download-docx', $foreignTemplate))
            ->assertStatus(403);

        $this->asForeignUser()
            ->getJson(route('contract-templates.email.show', $foreignTemplate))
            ->assertStatus(403);

        $this->asForeignUser()
            ->putJson(route('contract-templates.update-email', $foreignTemplate), [
                'email_subject' => 'Hack',
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function dedicated_contracts_view_user_can_use_entire_templates_section(): void
    {
        Storage::fake();

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $template = $this->createContractTemplateWithVersion(['title' => 'Dedicated Access Template']);
        Storage::put($template->currentVersion->docx_path, $this->minimalDocxBytes());

        $this->get(route('contract-templates.index'))->assertOk();
        $this->get(route('contract-templates.index', ['create' => 1]))->assertOk();
        $this->get(route('contract-templates.index', ['edit' => $template->id]))->assertOk();
        $this->getJson(route('contract-templates.data', ['draw' => 1, 'start' => 0, 'length' => 5]))->assertOk();
        $this->getJson(route('contract-templates.columns-settings.get'))->assertOk();
        $this->getJson(route('logs.data.contract-template', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk();
        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => ['title' => true],
        ])->assertOk();
        $this->get(route('contract-templates.download-docx', $template))->assertOk();
        $this->getJson(route('contract-templates.email.show', $template))->assertOk();

        $store = $this->post(route('contract-templates.store'), [
            'title' => 'Dedicated Created',
            'docx'  => $this->fakeDocxUploadedFile(['parent_full_name']),
        ]);
        $store->assertSessionHasNoErrors();
        $store->assertRedirect(route('contract-templates.index'));

        $this->put(route('contract-templates.update', $template), [
            'title'  => 'Dedicated Updated',
            'fields' => [
                [
                    'key'      => 'parent_full_name',
                    'label'    => 'ФИО',
                    'required' => 1,
                ],
            ],
        ])->assertRedirect(route('contract-templates.index'));

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Dedicated subject',
            'email_body_html' => '<p>Dedicated body</p>',
        ])->assertOk();
    }
}
