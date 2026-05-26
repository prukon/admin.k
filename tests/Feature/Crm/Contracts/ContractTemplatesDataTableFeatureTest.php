<?php

namespace Tests\Feature\Crm\Contracts;

class ContractTemplatesDataTableFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function templates_datatable_requires_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson(route('contract-templates.data', ['draw' => 1]))
            ->assertStatus(403);
    }

    /** @test */
    public function templates_datatable_returns_only_current_partner_templates(): void
    {
        $own = $this->createContractTemplateWithVersion(['title' => 'Свой шаблон']);
        $this->createContractTemplateWithVersion([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'Чужой шаблон',
        ]);

        $response = $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => ['id', 'title', 'version', 'fields_count', 'status_key', 'status_label', 'edit_url'],
            ],
        ]);

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Свой шаблон', $titles);
        $this->assertNotContains('Чужой шаблон', $titles);
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame($own->id, (int) $response->json('data.0.id'));
    }

    /** @test */
    public function templates_index_renders_datatable_markup(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('Шаблоны договоров', false)
            ->assertSee('Добавить шаблон', false)
            ->assertSee('contractTemplatesColumnsDropdown', false)
            ->assertSee('id="contract-templates-table"', false);
    }

    /** @test */
    public function templates_columns_settings_require_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson(route('contract-templates.columns-settings.get'))
            ->assertStatus(403);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->postJson(route('contract-templates.columns-settings.save'), [
                'columns' => ['title' => true],
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function templates_columns_settings_save_and_load(): void
    {
        $this->getJson(route('contract-templates.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);

        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => [
                'title'  => true,
                'version' => 'false',
                'actions' => 1,
            ],
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->getJson(route('contract-templates.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([
                'title'   => true,
                'version' => false,
                'actions' => true,
            ]);
    }
}
