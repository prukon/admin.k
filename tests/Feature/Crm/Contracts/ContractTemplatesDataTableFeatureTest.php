<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplate;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;

class ContractTemplatesDataTableFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function guest_cannot_access_templates_datatable_json(): void
    {
        Auth::logout();

        $this->getJson(route('contract-templates.data', ['draw' => 1]))->assertStatus(401);
        $this->getJson(route('contract-templates.columns-settings.get'))->assertStatus(401);
    }

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
    public function templates_datatable_search_filters_by_title_and_id(): void
    {
        $alpha = $this->createContractTemplateWithVersion(['title' => 'Alpha Template']);
        $this->createContractTemplateWithVersion(['title' => 'Beta Template']);

        $byTitle = $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
            'search' => ['value' => 'Alpha'],
        ]));
        $byTitle->assertOk();
        $this->assertSame(1, (int) $byTitle->json('recordsFiltered'));
        $this->assertSame('Alpha Template', $byTitle->json('data.0.title'));

        $byId = $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
            'search' => ['value' => (string) $alpha->id],
        ]));
        $byId->assertOk();
        $this->assertSame(1, (int) $byId->json('recordsFiltered'));
        $this->assertSame($alpha->id, (int) $byId->json('data.0.id'));
    }

    /** @test */
    public function templates_datatable_paginates_results(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createContractTemplateWithVersion(['title' => 'Paginate ' . $i]);
        }

        $page = $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 2,
        ]));

        $page->assertOk();
        $this->assertSame(3, (int) $page->json('recordsTotal'));
        $this->assertSame(3, (int) $page->json('recordsFiltered'));
        $this->assertCount(2, $page->json('data'));
    }

    /** @test */
    public function templates_datatable_returns_status_and_edit_url_for_each_state(): void
    {
        $active = $this->createContractTemplateWithVersion(['title' => 'Active Template']);

        $archived = $this->createContractTemplateWithVersion([
            'title'       => 'Archived Template',
            'is_archived' => true,
        ]);

        $noVersion = ContractTemplate::create([
            'partner_id'         => $this->partner->id,
            'title'              => 'No Version Template',
            'is_archived'        => false,
            'current_version_id' => null,
        ]);

        $response = $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
            'search' => ['value' => 'Template'],
            'order'  => [['column' => 0, 'dir' => 'asc']],
            'columns'=> [
                ['name' => 'id'],
                ['name' => 'title'],
            ],
        ]));

        $response->assertOk();
        $rows = collect($response->json('data'))->keyBy('title');

        $this->assertSame('active', $rows['Active Template']['status_key']);
        $this->assertSame('Активен', $rows['Active Template']['status_label']);
        $this->assertSame(1, $rows['Active Template']['fields_count']);
        $this->assertStringContainsString(
            'edit=' . $active->id,
            $rows['Active Template']['edit_url']
        );

        $this->assertSame('archived', $rows['Archived Template']['status_key']);
        $this->assertSame('В архиве', $rows['Archived Template']['status_label']);

        $this->assertSame('no_version', $rows['No Version Template']['status_key']);
        $this->assertSame('Нет версии', $rows['No Version Template']['status_label']);
        $this->assertSame(0, $rows['No Version Template']['fields_count']);
    }

    /** @test */
    public function templates_datatable_orders_by_title_when_requested(): void
    {
        $this->createContractTemplateWithVersion(['title' => 'Zulu']);
        $this->createContractTemplateWithVersion(['title' => 'Alpha']);

        $response = $this->getJson(route('contract-templates.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 50,
            'order'   => [['column' => 1, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'id'],
                ['name' => 'title'],
                ['name' => 'version'],
                ['name' => 'fields_count'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
        ]));

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->values()->all();
        $sorted = $titles;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $titles);
    }

    /** @test */
    public function templates_index_renders_toolbar_datatable_and_column_menu(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('Шаблоны договоров', false)
            ->assertSee('Добавить шаблон', false)
            ->assertSee('contractTemplatesColumnsDropdown', false)
            ->assertSee('id="contract-templates-table"', false)
            ->assertSee('<th>№</th>', false)
            ->assertSee('data-column-key="title"', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('columnsSettings:', false);
    }

    /** @test */
    public function templates_datatable_row_edit_url_is_available_via_json(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $response = $this->getJson(route('contract-templates.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $this->assertSame(
            route('contract-templates.index', ['edit' => $template->id]),
            $response->json('data.0.edit_url')
        );
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
    public function templates_columns_settings_post_validates_columns(): void
    {
        $this->postJson(route('contract-templates.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => 'invalid',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    /** @test */
    public function templates_columns_settings_save_and_load(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'contract_templates_index')
            ->delete();

        $this->getJson(route('contract-templates.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);

        $this->postJson(route('contract-templates.columns-settings.save'), [
            'columns' => [
                'title'   => true,
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
