<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Доступ к страницам «Договоры» / «Шаблоны» и связанным эндпоинтам (contracts.view → 200, без права → 403).
 */
class ContractsDocumentsSectionFullAccessFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    /** @test */
    public function guest_cannot_access_documents_section_endpoints(): void
    {
        Auth::logout();

        $this->get(route('contracts.index'))->assertStatus(302);
        $this->get(route('contract-templates.index'))->assertStatus(302);
        $this->get(route('contracts.create'))->assertStatus(302);
        $this->get(route('contract-templates.create'))->assertStatus(302);

        $this->getJson(route('contracts.data', ['draw' => 1]))->assertStatus(401);
        $this->getJson(route('contracts.columns-settings.get'))->assertStatus(401);
    }

    /** @test */
    public function documents_section_forbidden_without_contracts_view(): void
    {
        $template = $this->createContractTemplateWithVersion();
        $contract = $this->createDraftContractForAccessTest();

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertStatus(403);
        $this->get(route('contract-templates.index'))->assertStatus(403);
        $this->get(route('contracts.create'))->assertStatus(403);
        $this->get(route('contract-templates.create'))->assertStatus(403);
        $this->get(route('contract-templates.edit', $template))->assertStatus(403);

        $this->getJson(route('contracts.data', ['draw' => 1]))->assertStatus(403);
        $this->getJson(route('contracts.columns-settings.get'))->assertStatus(403);
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['user_name' => true],
        ])->assertStatus(403);
        $this->getJson(route('contracts.users.search', ['q' => 'test']))->assertStatus(403);
        $this->getJson(route('contracts.user.group', ['user_id' => $contract->user_id]))->assertStatus(403);
        $this->postJson('/client-contracts/check-balance')->assertStatus(403);

        $this->post(route('contract-templates.store'), [
            'title' => 'Forbidden',
            'docx'  => $this->fakeDocxUploadedFile(),
        ])->assertStatus(403);

        $this->put(route('contract-templates.update', $template), [
            'title' => 'Forbidden',
        ])->assertStatus(403);

        $this->get(route('contract-templates.download-docx', $template))->assertStatus(403);
        $this->get(route('contracts.show', $contract))->assertStatus(403);
    }

    /** @test */
    public function contracts_index_page_returns_200_with_contracts_view(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertViewIs('contracts.index')
            ->assertViewHas(['allTeams', 'activeTab']);
    }

    /** @test */
    public function templates_index_page_returns_200_with_contracts_view(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertViewIs('contract-templates.index')
            ->assertViewHas(['templates', 'prefillSources', 'activeTab']);
    }

    /** @test */
    public function user_with_only_contracts_view_can_access_all_documents_section_endpoints(): void
    {
        Storage::fake();
        Mail::fake();

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Access Группа']);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'name'       => 'AccessStudent',
            'phone'      => '+79991112233',
        ]);

        $contract = $this->createDraftContractForAccessTest($student);
        Storage::put($contract->source_pdf_path, '%PDF-access-test');

        $template = $this->createContractTemplateWithVersion(['title' => 'Access Template']);

        $this->get(route('contracts.index'))->assertOk();
        $this->get(route('contract-templates.index'))->assertOk();
        $this->get(route('contracts.create'))->assertOk();
        $this->get(route('contract-templates.create'))
            ->assertRedirect(route('contract-templates.index', ['create' => 1]));
        $this->get(route('contract-templates.index', ['create' => 1]))->assertOk();
        $this->get(route('contract-templates.edit', $template))
            ->assertRedirect(route('contract-templates.index', ['edit' => $template->id]));
        $this->get(route('contract-templates.index', ['edit' => $template->id]))->assertOk();

        $this->getJson(route('contracts.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk();
        $this->getJson(route('contracts.data', [
            'draw'         => 1,
            'search_value' => 'Access',
            'group_id'     => (string) $team->id,
            'status'       => Contract::STATUS_DRAFT,
        ]))->assertOk();

        $this->getJson(route('contracts.columns-settings.get'))->assertOk();
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'user_name'    => true,
                'user_lastname'=> true,
                'team_title'   => false,
                'status_label' => true,
                'actions'      => true,
            ],
        ])->assertOk();

        $this->getJson(route('contracts.users.search', ['q' => 'Access']))->assertOk();
        $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))->assertOk();

        $this->postJson('/client-contracts/check-balance')
            ->assertOk()
            ->assertJsonStructure(['ok', 'fee']);

        $this->get(route('contracts.show', $contract))->assertOk();
        $this->get(route('contracts.downloadOriginal', $contract))->assertOk();
        $this->get(route('contract-templates.download-docx', $template))->assertOk();

        $this->postJson(route('contracts.sendEmail', $contract), [
            'email' => 'access-test@example.com',
        ])->assertOk();

        $storeTemplate = $this->post(route('contract-templates.store'), [
            'title'         => 'Access Created Template',
            'docx'          => $this->fakeDocxUploadedFile(['fio_parent']),
            'email_subject' => 'Тема',
        ]);
        $storeTemplate->assertSessionHasNoErrors();
        $storeTemplate->assertRedirect(route('contract-templates.index'));
        $this->followRedirects($storeTemplate)->assertOk();

        $this->put(route('contract-templates.update', $template), [
            'title'           => 'Access Updated Template',
            'email_subject'   => 'Новая тема',
            'email_body_html' => '<p>Новый текст</p>',
            'fields'          => [
                [
                    'key'            => 'fio_parent',
                    'label'          => 'ФИО',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-templates.index'));

        $pdf = UploadedFile::fake()->create('access-contract.pdf', 20, 'application/pdf');
        $storeContract = $this->from(route('contracts.create'))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'pdf'           => $pdf,
            ]);
        $storeContract->assertSessionHasNoErrors();
        $storeContract->assertRedirect();
        $this->followRedirects($storeContract)->assertOk();
    }

    private function createDraftContractForAccessTest(?User $student = null): Contract
    {
        $student ??= User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => 'documents/access/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);
    }
}
