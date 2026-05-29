<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Модалка создания договора на GET /client-contracts (вместо отдельной страницы /create).
 */
class ContractCreateModalFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    /** @test */
    public function index_renders_create_contract_modal_with_form(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertViewIs('contracts.index')
            ->assertViewHas('shouldOpenCreateModal', false)
            ->assertViewHas('partner')
            ->assertViewHas('contractTemplates')
            ->assertViewHas('preselectedUser', null)
            ->assertSee('id="createContractModal"', false)
            ->assertSee('id="contract-create-form"', false)
            ->assertSee('data-bs-target="#createContractModal"', false)
            ->assertSee('id="contractHowItWorksToggle"', false)
            ->assertSee('id="contractCreateHowItWorks"', false)
            ->assertSee('theme: \'bootstrap-5\'', false)
            ->assertSee('name="creation_mode"', false)
            ->assertDontSee('form-check-inline', false);
    }

    /** @test */
    public function create_route_redirects_to_index_with_create_flag(): void
    {
        $this->get(route('contracts.create'))
            ->assertRedirect(route('contracts.index', ['create' => 1]));
    }

    /** @test */
    public function create_route_with_user_id_redirects_to_index_with_query(): void
    {
        $student = $this->createEnabledStudent();

        $this->get(route('contracts.create', ['user_id' => $student->id]))
            ->assertRedirect(route('contracts.index', [
                'create'  => 1,
                'user_id' => $student->id,
            ]));
    }

    /** @test */
    public function index_with_create_query_sets_modal_open_flag(): void
    {
        $this->get(route('contracts.index', ['create' => 1]))
            ->assertOk()
            ->assertViewHas('shouldOpenCreateModal', true);
    }

    /** @test */
    public function index_with_user_id_prefills_student_and_opens_modal(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа модалки',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'name'       => 'Иван',
            'lastname'   => 'Модалкин',
        ]);

        $this->get(route('contracts.index', ['user_id' => $student->id]))
            ->assertOk()
            ->assertViewHas('shouldOpenCreateModal', true)
            ->assertViewHas('preselectedUser', function ($pre) use ($student, $team) {
                return is_array($pre)
                    && (int) $pre['id'] === $student->id
                    && (int) $pre['team_id'] === $team->id
                    && $pre['team_title'] === $team->title
                    && str_contains((string) $pre['text'], 'Модалкин')
                    && str_contains((string) $pre['text'], 'Иван');
            });
    }

    /** @test */
    public function index_ignores_foreign_user_id_for_prefill(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $this->get(route('contracts.index', ['user_id' => $foreignStudent->id]))
            ->assertOk()
            ->assertViewHas('shouldOpenCreateModal', true)
            ->assertViewHas('preselectedUser', null);
    }

    /** @test */
    public function index_shows_template_select_when_templates_exist(): void
    {
        $this->createContractTemplateWithVersion(['title' => 'Шаблон в модалке']);

        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('id="contract_template_id"', false)
            ->assertSee('Шаблон в модалке', false)
            ->assertSee('const hasContractTemplates = true', false);
    }

    /** @test */
    public function index_shows_warning_when_no_templates(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('Шаблонов нет.', false)
            ->assertSee('const hasContractTemplates = false', false)
            ->assertDontSee('id="contract_template_id"', false);
    }

    /** @test */
    public function store_validation_errors_redirect_to_index_with_create_modal(): void
    {
        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [])
            ->assertRedirect(route('contracts.index', ['create' => 1]))
            ->assertSessionHasErrors(['user_id', 'creation_mode']);
    }

    /** @test */
    public function store_validation_errors_preserve_user_id_in_redirect_query(): void
    {
        $student = $this->createEnabledStudent();

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
            ])
            ->assertRedirect(route('contracts.index', [
                'create'  => 1,
                'user_id' => $student->id,
            ]))
            ->assertSessionHasErrors(['pdf']);
    }

    /** @test */
    public function store_validation_errors_reopen_modal_via_session_errors(): void
    {
        $response = $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
            ]);

        $response->assertRedirect(route('contracts.index', ['create' => 1]))
            ->assertSessionHasErrors(['user_id', 'pdf']);

        $this->followRedirects($response)
            ->assertOk()
            ->assertViewHas('shouldOpenCreateModal', true);
    }

    /** @test */
    public function store_from_index_modal_creates_contract_and_redirects_to_show(): void
    {
        Storage::fake();

        $student = $this->createEnabledStudent();
        $pdf = UploadedFile::fake()->create('modal-contract.pdf', 20, 'application/pdf');

        $response = $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'pdf'           => $pdf,
            ]);

        $contract = Contract::query()->firstOrFail();

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('contracts.show', $contract->id));

        $this->assertDatabaseHas('contracts', [
            'id'      => $contract->id,
            'user_id' => $student->id,
        ]);
    }

    private function createEnabledStudent(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
    }
}
