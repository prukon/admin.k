<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ParentProfile;
use App\Services\Contracts\ContractPdfGenerationService;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разбиение ФИО на части в форме заполнения договора, сборка при generate,
 * сброс устаревших ошибок генерации и poll-поведение модалки.
 */
class AccountContractFillSplitNameFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        config(['queue.default' => 'sync']);
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_template_with_parent_full_name_renders_split_fields_instead_of_composite(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
                ['key' => 'parent_phone', 'label' => 'Родитель: телефон', 'required' => true],
            ],
            ['parent_full_name', 'parent_phone'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('name="fields[parent_lastname]"', $html);
        $this->assertStringContainsString('name="fields[parent_firstname]"', $html);
        $this->assertStringContainsString('name="fields[parent_middlename]"', $html);
        $this->assertStringNotContainsString('name="fields[parent_full_name]"', $html);
    }

    public function test_template_with_child_full_name_renders_split_child_fields(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'child_full_name', 'label' => 'Ребёнок: ФИО', 'required' => true],
                ['key' => 'child_birthday', 'label' => 'Ребёнок: дата рождения', 'required' => true],
            ],
            ['child_full_name', 'child_birthday'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('name="fields[child_lastname]"', $html);
        $this->assertStringContainsString('name="fields[child_firstname]"', $html);
        $this->assertStringNotContainsString('name="fields[child_full_name]"', $html);
    }

    public function test_full_template_schema_like_production_contract_renders_split_name_parts(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true, 'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME],
                ['key' => 'child_full_name', 'label' => 'Ребёнок: ФИО', 'required' => true, 'prefill_source' => ContractTemplatePrefillSources::CHILD_FULL_NAME],
                ['key' => 'child_birthday', 'label' => 'Ребёнок: дата рождения', 'required' => true, 'prefill_source' => ContractTemplatePrefillSources::CHILD_BIRTHDAY],
                ['key' => 'parent_passport', 'label' => 'Родитель: паспорт', 'required' => true],
                ['key' => 'parent_phone', 'label' => 'Родитель: телефон', 'required' => true],
            ],
            ['parent_full_name', 'child_full_name', 'child_birthday', 'parent_passport', 'parent_phone'],
        );

        $html = $this->getContractFillModalHtml($contract);

        foreach ([
            'parent_lastname',
            'parent_firstname',
            'parent_middlename',
            'child_lastname',
            'child_firstname',
            'child_birthday',
            'parent_passport',
            'parent_phone',
        ] as $fieldKey) {
            $this->assertStringContainsString('name="fields[' . $fieldKey . ']"', $html, 'Поле ' . $fieldKey . ' должно быть в форме');
        }

        $this->assertStringNotContainsString('name="fields[parent_full_name]"', $html);
        $this->assertStringNotContainsString('name="fields[child_full_name]"', $html);
    }

    public function test_prefill_populates_split_parent_name_from_linked_parent_profile(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Баранова',
            'firstname'  => 'Мальвина',
            'middlename' => 'Алексеевич',
        ]);
        $this->user->forceFill(['parent_id' => $parent->id])->save();

        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true, 'prefill_source' => ContractTemplatePrefillSources::PARENT_FULL_NAME],
            ],
            ['parent_full_name'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('value="Баранова"', $html);
        $this->assertStringContainsString('value="Мальвина"', $html);
        $this->assertStringContainsString('value="Алексеевич"', $html);
    }

    public function test_generate_without_parent_full_name_post_key_composes_full_name(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'   => 'Сидоров',
                'parent_firstname'  => 'Сидор',
                'parent_middlename' => 'Сидорович',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('Сидоров Сидор Сидорович', $contract->filled_data['parent_full_name'] ?? null);
        $this->assertSame('Сидоров', $contract->filled_data['parent_lastname'] ?? null);
    }

    public function test_generate_without_middlename_composes_parent_full_name_without_middle(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
            ],
        ])->assertRedirect();

        $contract->refresh();
        $this->assertSame('Иванов Иван', $contract->filled_data['parent_full_name'] ?? null);
    }

    public function test_generate_without_child_full_name_post_key_composes_child_full_name(): void
    {
        $this->user->forceFill([
            'lastname' => 'Старое',
            'name'     => 'Имя',
            'birthday' => '2010-05-01',
        ])->save();

        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
                ['key' => 'child_full_name', 'label' => 'Ребёнок: ФИО', 'required' => true],
                ['key' => 'child_birthday', 'label' => 'Дата рождения', 'required' => true],
            ],
            ['parent_full_name', 'child_full_name', 'child_birthday'],
        );

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
                'child_lastname'   => 'Петров',
                'child_firstname'  => 'Пётр',
                'child_birthday'   => '2010-05-01',
            ],
        ])->assertRedirect();

        $contract->refresh();
        $this->assertSame('Петров Пётр', $contract->filled_data['child_full_name'] ?? null);
        $this->assertSame('01.05.2010', $contract->filled_data['child_birthday'] ?? null);
    }

    public function test_resubmit_after_stale_parent_full_name_error_succeeds_and_clears_error(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $contract->forceFill([
            'filled_data' => [
                'parent_lastname'   => 'Баранова',
                'parent_firstname'  => 'Мальвина',
                'parent_middlename' => 'Алексеевич',
                '_generation_error' => 'Поле «Родитель: ФИО» обязательно для заполнения.',
            ],
        ])->save();

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'   => 'Баранова',
                'parent_firstname'  => 'Мальвина',
                'parent_middlename' => 'Алексеевич',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]))
            ->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('Баранова Мальвина Алексеевич', $contract->filled_data['parent_full_name'] ?? null);
        $this->assertNull($contract->pdfGenerationError());
    }

    public function test_generate_http_request_does_not_require_parent_full_name_field_key(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Козлов',
                    'parent_firstname' => 'Козел',
                ],
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]))
            ->assertSessionDoesntHaveErrors(['fields.parent_full_name']);
    }

    public function test_validate_field_input_composes_names_before_required_check(): void
    {
        $schema = ContractTemplateVariablePresets::schemaFieldsForParentForm([
            ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ['key' => 'child_full_name', 'label' => 'Ребёнок: ФИО', 'required' => true],
        ]);

        app(ContractPdfGenerationService::class)->validateFieldInput($schema, [
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
            'child_lastname'   => 'Петров',
            'child_firstname'  => 'Пётр',
        ]);

        $this->assertTrue(true);
    }

    public function test_poll_fill_request_returns_200_and_keeps_generation_error(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true]],
        );
        $contract->update([
            'filled_data' => ['_generation_error' => 'Тестовая ошибка генерации.'],
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'poll' => 1]))
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'poll']);

        $this->assertSame('Тестовая ошибка генерации.', $contract->fresh()->pdfGenerationError());
    }

    public function test_documents_index_js_passes_poll_query_on_refresh(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true]],
        );

        $html = (string) $this->get(route('account.documents.index', ['fill' => $contract->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("loadContractFill(contractId, true)", $html);
        $this->assertStringContainsString("query.push('poll=1')", $html);
        $this->assertStringContainsString("submit', '#contractFillModal .contract-fill-form'", $html);
    }

    public function test_generate_ajax_returns_422_when_template_docx_missing_on_server(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $contract->templateVersion->update(['docx_path' => 'contract-templates/missing-' . uniqid() . '.docx']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['contract']);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->pdfGenerationError());
    }

    public function test_contract_clear_pdf_generation_error_removes_internal_key(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true]],
        );
        $contract->update([
            'filled_data' => [
                'parent_lastname'   => 'Тест',
                '_generation_error' => 'Старая ошибка',
            ],
        ]);

        $this->assertTrue($contract->clearPdfGenerationError());
        $this->assertNull($contract->fresh()->pdfGenerationError());
        $this->assertSame('Тест', $contract->fresh()->filled_data['parent_lastname'] ?? null);
    }
}
