<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;

/**
 * Переменные юр. лица и child_address в шаблонах договоров:
 * UI-справочник, store DOCX→schema, блок отправки без юрлица, fill/generate.
 */
class ContractTemplateLegalEntityVariablesFeatureTest extends ContractsFeatureTestCase
{
    use InteractsWithAccountContractFill;

    /** @test */
    public function create_modal_shows_legal_entity_accordion_between_child_and_contract_groups(): void
    {
        $html = (string) $this->get(route('contract-templates.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Юр. лицо', $html);
        $this->assertStringContainsString('{{legal_entity_name}}', $html);
        $this->assertStringContainsString('{{legal_entity_ogrnip}}', $html);
        $this->assertStringContainsString('{{legal_entity_inn}}', $html);
        $this->assertStringContainsString('{{legal_entity_address}}', $html);
        $this->assertStringContainsString('{{legal_entity_bank_name}}', $html);
        $this->assertStringContainsString('{{legal_entity_bank_account}}', $html);
        $this->assertStringContainsString('{{legal_entity_bik}}', $html);
        $this->assertStringContainsString('{{legal_entity_bank_corr_account}}', $html);
        $this->assertStringContainsString('{{child_address}}', $html);
        $this->assertStringContainsString('Ребёнок: адрес проживания', $html);

        $childPos = strpos($html, 'createContractTemplateVariablesAccordion-child-heading');
        $legalPos = strpos($html, 'createContractTemplateVariablesAccordion-legal_entity-heading');
        $contractPos = strpos($html, 'createContractTemplateVariablesAccordion-contract-heading');

        $this->assertNotFalse($childPos);
        $this->assertNotFalse($legalPos);
        $this->assertNotFalse($contractPos);
        $this->assertGreaterThan($childPos, $legalPos);
        $this->assertGreaterThan($legalPos, $contractPos);
    }

    /** @test */
    public function edit_modal_also_shows_legal_entity_variable_reference(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'Edit LE vars']);

        $html = (string) $this->get(route('contract-templates.index', ['edit' => $template->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('editContractTemplateVariablesAccordion-legal_entity-heading', $html);
        $this->assertStringContainsString('{{legal_entity_inn}}', $html);
        $this->assertStringContainsString('{{child_address}}', $html);
    }

    /** @test */
    public function store_docx_with_legal_entity_placeholders_builds_system_schema_and_child_address_prefill(): void
    {
        $this->post(route('contract-templates.store'), [
            'title' => 'Шаблон с юрлицом',
            'docx'  => $this->fakeDocxUploadedFile([
                'legal_entity_name',
                'legal_entity_inn',
                'legal_entity_bank_corr_account',
                'child_address',
                'parent_full_name',
            ]),
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-templates.index'));

        $template = ContractTemplate::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Шаблон с юрлицом')
            ->firstOrFail();

        $schema = collect($template->currentVersion->fields_schema ?? [])->keyBy('key');

        $this->assertTrue($schema->has('legal_entity_name'));
        $this->assertTrue($schema->has('legal_entity_inn'));
        $this->assertTrue($schema->has('legal_entity_bank_corr_account'));
        $this->assertTrue($schema->has('child_address'));

        $this->assertSame('Юр. лицо: название', $schema['legal_entity_name']['label'] ?? null);
        $this->assertSame('Юр. лицо: ИНН', $schema['legal_entity_inn']['label'] ?? null);
        $this->assertFalse((bool) ($schema['legal_entity_inn']['required'] ?? true));
        $this->assertNull($schema['legal_entity_inn']['prefill_source'] ?? null);

        $this->assertSame('Ребёнок: адрес проживания', $schema['child_address']['label'] ?? null);
        $this->assertSame(
            ContractTemplatePrefillSources::CHILD_ADDRESS,
            $schema['child_address']['prefill_source'] ?? null,
        );

        $this->assertTrue(ContractTemplateVariablePresets::isSystemFillField('legal_entity_inn'));
        $this->assertFalse(ContractTemplateVariablePresets::isSystemFillField('child_address'));
        $this->assertTrue(ContractTemplateVariablePresets::schemaUsesLegalEntityFields(
            $template->currentVersion->fields_schema ?? [],
        ));
    }

    /** @test */
    public function guest_cannot_create_contract_from_legal_entity_template(): void
    {
        Mail::fake();
        $template = $this->makeLegalEntityTemplate();

        Auth::logout();

        $resp = $this->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $this->user->id,
            'contract_template_id' => $template->id,
        ]);

        $this->assertContains($resp->status(), [302, 401, 403]);
        $this->assertSame(0, Contract::query()->count());
        Mail::assertNothingSent();
    }

    /** @test */
    public function manager_without_contracts_view_cannot_create_contract_from_legal_entity_template(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        $template = $this->makeLegalEntityTemplate();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->post('/client-contracts', [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $this->user->id,
                'contract_template_id' => $template->id,
            ])
            ->assertStatus(403);

        $this->assertSame(0, Contract::query()->count());
        $this->partner->refresh();
        $this->assertSame(10000, (int) $this->partner->wallet_balance_cents);
        Mail::assertNothingSent();
    }

    /** @test */
    public function admin_cannot_send_template_contract_when_group_has_no_legal_entity_in_multi_mode(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);

        $team = Team::factory()->for($this->partner)->create(['legal_entity_id' => null]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'email'      => 'le-block@example.com',
        ]);
        $template = $this->makeLegalEntityTemplate();

        $this->from(route('contracts.index'))
            ->post('/client-contracts', [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'group_id'             => $team->id,
                'contract_template_id' => $template->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('group_id');

        $this->assertStringContainsString(
            'не привязано юр. лицо',
            session('errors')->first('group_id'),
        );
        $this->assertSame(0, Contract::query()->count());
        $this->partner->refresh();
        $this->assertSame(10000, (int) $this->partner->wallet_balance_cents);
        Mail::assertNothingSent();
    }

    /** @test */
    public function admin_cannot_send_template_contract_when_partner_has_no_active_legal_entity(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => null,
            'is_enabled' => 1,
            'email'      => 'no-le@example.com',
        ]);
        $template = $this->makeLegalEntityTemplate();

        $this->from(route('contracts.index'))
            ->post('/client-contracts', [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'contract_template_id' => $template->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('group_id');

        $this->assertStringContainsString(
            'Не найдено активное юр. лицо',
            session('errors')->first('group_id'),
        );
        $this->assertSame(0, Contract::query()->count());
        Mail::assertNothingSent();
    }

    /** @test */
    public function admin_can_send_template_without_legal_entity_fields_even_if_group_has_no_binding(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);

        $team = Team::factory()->for($this->partner)->create(['legal_entity_id' => null]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'email'      => 'no-le-fields@example.com',
        ]);

        $template = $this->createContractTemplateWithVersion(
            ['title' => 'Без юрлица'],
            [
                'fields_schema' => [
                    ['key' => 'parent_full_name', 'label' => 'ФИО', 'required' => true, 'prefill_source' => null],
                ],
            ],
        );

        $this->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'group_id'             => $team->id,
            'contract_template_id' => $template->id,
        ])
            ->assertSessionHasNoErrors()
            ->assertStatus(302);

        $this->assertSame(1, Contract::query()->count());
        Mail::assertSent(\App\Mail\ContractClientFillInvitationMail::class);
    }

    /** @test */
    public function admin_sends_template_contract_when_group_has_legal_entity_and_charges_balance(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Успешный',
            'tax_id'            => '500100732259',
        ]);
        $team = Team::factory()->for($this->partner)->create(['legal_entity_id' => $entity->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'email'      => 'le-ok@example.com',
        ]);
        $template = $this->makeLegalEntityTemplate();

        $this->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'group_id'             => $team->id,
            'contract_template_id' => $template->id,
        ])
            ->assertSessionHasNoErrors()
            ->assertStatus(302);

        $contract = Contract::query()->firstOrFail();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertSame((int) $team->id, (int) $contract->group_id);
        $this->partner->refresh();
        $this->assertSame(30.0, (float) ($this->partner->wallet_balance_cents / 100));
        Mail::assertSent(\App\Mail\ContractClientFillInvitationMail::class);
    }

    /** @test */
    public function parent_fill_form_hides_legal_entity_fields_but_shows_prefilled_child_address(): void
    {
        $this->useAccountContractFillStorage();

        $this->user->forceFill([
            'address' => 'г. Казань, ул. Ученическая, д. 3',
        ])->save();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create();
        $team = Team::factory()->for($this->partner)->create(['legal_entity_id' => $entity->id]);

        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'legal_entity_name', 'label' => 'Юр. лицо: название', 'required' => false],
                ['key' => 'legal_entity_inn', 'label' => 'Юр. лицо: ИНН', 'required' => false],
                [
                    'key'            => 'child_address',
                    'label'          => 'Ребёнок: адрес проживания',
                    'required'       => false,
                    'prefill_source' => ContractTemplatePrefillSources::CHILD_ADDRESS,
                ],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ],
            ['legal_entity_name', 'legal_entity_inn', 'child_address', 'parent_full_name'],
        );
        $contract->forceFill(['group_id' => $team->id])->save();

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringNotContainsString('name="fields[legal_entity_name]"', $html);
        $this->assertStringNotContainsString('name="fields[legal_entity_inn]"', $html);
        $this->assertStringContainsString('name="fields[child_address]"', $html);
        $this->assertStringContainsString('г. Казань, ул. Ученическая, д. 3', $html);
        $this->assertStringContainsString('name="fields[parent_lastname]"', $html);
    }

    /** @test */
    public function parent_generate_injects_legal_entity_values_and_syncs_child_address(): void
    {
        $this->useAccountContractFillStorage();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->individualEntrepreneur()->create([
            'organization_name'   => 'ИП Генерация',
            'registration_number' => '304500116000157',
            'tax_id'              => '500100732259',
            'address'             => 'юр. адрес 1',
            'bank_name'           => 'АО «ТБанк»',
            'bank_account'        => '40802810100000000001',
            'bank_bik'            => '044525974',
            'bank_corr_account'   => '30101810145250000974',
        ]);
        $team = Team::factory()->for($this->partner)->create(['legal_entity_id' => $entity->id]);

        $this->user->forceFill([
            'address'  => 'старый адрес',
            'lastname' => 'Старый',
            'name'     => 'Ребёнок',
        ])->save();

        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'legal_entity_name', 'label' => 'Название', 'required' => false],
                ['key' => 'legal_entity_ogrnip', 'label' => 'ОГРНИП', 'required' => false],
                ['key' => 'legal_entity_inn', 'label' => 'ИНН', 'required' => false],
                ['key' => 'legal_entity_address', 'label' => 'Адрес', 'required' => false],
                ['key' => 'legal_entity_bank_name', 'label' => 'Банк', 'required' => false],
                ['key' => 'legal_entity_bank_account', 'label' => 'Р/с', 'required' => false],
                ['key' => 'legal_entity_bik', 'label' => 'БИК', 'required' => false],
                ['key' => 'legal_entity_bank_corr_account', 'label' => 'К/с', 'required' => false],
                [
                    'key'            => 'child_address',
                    'label'          => 'Адрес ребёнка',
                    'required'       => false,
                    'prefill_source' => ContractTemplatePrefillSources::CHILD_ADDRESS,
                ],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            [
                'legal_entity_name',
                'legal_entity_ogrnip',
                'legal_entity_inn',
                'legal_entity_address',
                'legal_entity_bank_name',
                'legal_entity_bank_account',
                'legal_entity_bik',
                'legal_entity_bank_corr_account',
                'child_address',
                'parent_full_name',
            ],
        );
        $contract->forceFill(['group_id' => $team->id])->save();

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
                'child_address'    => 'г. Казань, ул. Новая, д. 5',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $this->user->refresh();
        $data = $contract->filled_data ?? [];

        $this->assertSame('ИП Генерация', $data['legal_entity_name'] ?? null);
        $this->assertSame('304500116000157', $data['legal_entity_ogrnip'] ?? null);
        $this->assertSame('500100732259', $data['legal_entity_inn'] ?? null);
        $this->assertSame('юр. адрес 1', $data['legal_entity_address'] ?? null);
        $this->assertSame('АО «ТБанк»', $data['legal_entity_bank_name'] ?? null);
        $this->assertSame('40802810100000000001', $data['legal_entity_bank_account'] ?? null);
        $this->assertSame('044525974', $data['legal_entity_bik'] ?? null);
        $this->assertSame('30101810145250000974', $data['legal_entity_bank_corr_account'] ?? null);
        $this->assertSame('г. Казань, ул. Новая, д. 5', $data['child_address'] ?? null);
        $this->assertSame('г. Казань, ул. Новая, д. 5', $this->user->address);
    }

    /** @test */
    public function parent_generate_ajax_returns_422_with_contract_error_when_legal_entity_missing(): void
    {
        $this->useAccountContractFillStorage();

        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);
        $team = Team::factory()->for($this->partner)->create(['legal_entity_id' => null]);

        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'legal_entity_inn', 'label' => 'ИНН', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['legal_entity_inn', 'parent_full_name'],
        );
        $contract->forceFill(['group_id' => $team->id])->save();

        $resp = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
            ],
        ]);

        $resp->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['contract']]);

        $this->assertStringContainsString(
            'не привязано юр. лицо',
            (string) $resp->json('errors.contract.0'),
        );
        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    /** @test */
    public function parent_generate_non_ajax_redirects_with_contract_error_when_legal_entity_missing(): void
    {
        $this->useAccountContractFillStorage();

        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);
        $team = Team::factory()->for($this->partner)->create(['legal_entity_id' => null]);

        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'legal_entity_inn', 'label' => 'ИНН', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['legal_entity_inn', 'parent_full_name'],
        );
        $contract->forceFill(['group_id' => $team->id])->save();

        $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('contract');

        $this->assertStringContainsString(
            'не привязано юр. лицо',
            session('errors')->first('contract'),
        );
    }

    private function makeLegalEntityTemplate(): ContractTemplate
    {
        return $this->createContractTemplateWithVersion(
            ['title' => 'LE template ' . uniqid()],
            [
                'fields_schema' => [
                    [
                        'key'            => 'legal_entity_inn',
                        'label'          => 'Юр. лицо: ИНН',
                        'required'       => false,
                        'prefill_source' => null,
                    ],
                    [
                        'key'            => 'parent_full_name',
                        'label'          => 'Родитель: ФИО',
                        'required'       => true,
                        'prefill_source' => null,
                    ],
                ],
            ],
        );
    }
}
