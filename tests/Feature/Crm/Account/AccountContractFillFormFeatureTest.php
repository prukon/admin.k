<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ParentProfile;
use App\Services\Contracts\ContractPdfGenerationService;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

class AccountContractFillFormFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_fill_form_shows_split_child_fields_and_date_input(): void
    {
        $this->user->forceFill([
            'lastname' => 'Петров',
            'name'     => 'Пётр',
            'birthday' => '2018-05-10',
        ])->save();

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
        $this->assertStringContainsString('name="fields[child_birthday]"', $html);
        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('value="2018-05-10"', $html);
        $this->assertStringContainsString('max="' . now()->format('Y-m-d') . '"', $html);
    }

    public function test_fill_form_marks_phone_fields_for_inputmask(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_phone', 'label' => 'Родитель: телефон', 'required' => true],
                ['key' => 'spouse_phones', 'label' => 'Супруг(а): телефон', 'required' => false],
            ],
            ['parent_phone', 'spouse_phones'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('name="fields[parent_phone]"', $html);
        $this->assertStringContainsString('js-contract-fill-phone', $html);
    }

    public function test_fill_form_orders_custom_fields_after_spouse_by_fill_sort_order(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true, 'fill_sort_order' => 11],
                ['key' => 'parent_phone', 'label' => 'Телефон', 'required' => true, 'fill_sort_order' => 20],
                ['key' => 'spouse_full_name', 'label' => 'Супруг(а): ФИО', 'required' => false, 'fill_sort_order' => 200],
                ['key' => 'spouse_phones', 'label' => 'Супруг(а): телефон', 'required' => false, 'fill_sort_order' => 201],
                ['key' => 'custom_parent_note', 'label' => 'Примечание', 'required' => false, 'fill_sort_order' => 350],
            ],
            ['parent_lastname', 'parent_phone', 'spouse_full_name', 'spouse_phones', 'custom_parent_note'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertFillFormFieldOrder($html, [
            'parent_lastname',
            'parent_phone',
            'spouse_full_name',
            'spouse_phones',
            'custom_parent_note',
        ]);
    }

    public function test_fill_form_uses_preset_fill_sort_order_when_schema_has_no_override(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => ContractTemplatePrefillSources::PARENT_PHONE, 'label' => 'Телефон', 'required' => true],
                ['key' => ContractTemplatePrefillSources::PARENT_LASTNAME, 'label' => 'Фамилия', 'required' => true],
            ],
            ['parent_phone', 'parent_lastname'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertFillFormFieldOrder($html, ['parent_lastname', 'parent_phone']);
    }

    public function test_generate_composes_names_and_normalizes_child_birthday(): void
    {
        $this->user->forceFill([
            'lastname' => 'Старое',
            'name'     => 'Имя',
        ])->save();

        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
                ['key' => 'child_lastname', 'label' => 'Фамилия ребёнка', 'required' => true],
                ['key' => 'child_firstname', 'label' => 'Имя ребёнка', 'required' => true],
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
                'child_birthday'   => '2018-05-10',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $this->user->refresh();

        $this->assertSame('Иванов Иван', $contract->filled_data['parent_full_name'] ?? null);
        $this->assertSame('Петров Пётр', $contract->filled_data['child_full_name'] ?? null);
        $this->assertSame('10.05.2018', $contract->filled_data['child_birthday'] ?? null);
        $this->assertSame('Петров', $this->user->lastname);
        $this->assertSame('Пётр', $this->user->name);
        $this->assertSame('2018-05-10', $this->user->birthday?->format('Y-m-d'));
    }

    public function test_generate_syncs_parent_name_parts_to_profile(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
                ['key' => 'parent_middlename', 'label' => 'Отчество', 'required' => false],
            ],
            ['parent_full_name'],
        );

        $this->assertNull($this->user->parent_id);

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'   => 'Сидоров',
                'parent_firstname'  => 'Сидор',
                'parent_middlename' => 'Сидорович',
            ],
        ])->assertRedirect();

        $this->user->refresh();
        $profile = ParentProfile::query()->find($this->user->parent_id);

        $this->assertNotNull($profile);
        $this->assertSame('Сидоров', $profile->lastname);
        $this->assertSame('Сидор', $profile->firstname);
        $this->assertSame('Сидорович', $profile->middlename);
    }

    public function test_documents_index_includes_wider_fill_modal_shell(): void
    {
        $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);

        $html = (string) $this->get(route('account.documents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="contractFillModal"', $html);
        $this->assertStringContainsString('contract-fill-modal-dialog', $html);
        $this->assertStringContainsString('max-width: 720px', $html);
        $this->assertStringContainsString('jquery.inputmask', $html);
    }

    public function test_generate_rejects_future_child_birthday(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
                ['key' => 'child_birthday', 'label' => 'Дата рождения', 'required' => true],
            ],
            ['parent_full_name', 'child_birthday'],
        );

        $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                    'child_birthday'   => now()->addDay()->format('Y-m-d'),
                ],
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]))
            ->assertSessionHasErrors(['fields.child_birthday']);
    }

    public function test_spouse_preset_default_sort_is_after_profile_fields(): void
    {
        $this->assertSame(
            200,
            ContractTemplateVariablePresets::recommendedByKey()['spouse_full_name']['fill_sort_order'] ?? null,
        );
        $this->assertSame(
            ContractTemplateVariablePresets::FILL_SORT_DEFAULT_CUSTOM,
            ContractTemplateVariablePresets::guessFillSortOrder('unknown_custom_field', ContractTemplateVariablePresets::GROUP_PARENT),
        );
    }

    public function test_fill_form_shows_generation_error_from_filled_data(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $contract->forceFill([
            'filled_data' => [
                'parent_lastname'    => 'Баранова',
                'parent_firstname'   => 'Мальвина',
                'parent_middlename'  => 'Алексеевич',
                '_generation_error'  => 'Поле «Родитель: ФИО» обязательно для заполнения.',
            ],
        ])->save();

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('Поле «Родитель: ФИО» обязательно для заполнения.', $html);
        $this->assertSame(
            'Поле «Родитель: ФИО» обязательно для заполнения.',
            $contract->fresh()->pdfGenerationError(),
        );
    }

    public function test_poll_fill_request_keeps_generation_error(): void
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
                '_generation_error' => 'Не удалось сформировать PDF. Обратитесь в организацию.',
            ],
        ])->save();

        $html = (string) $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'poll' => 1]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Не удалось сформировать PDF. Обратитесь в организацию.', $html);
        $this->assertSame(
            'Не удалось сформировать PDF. Обратитесь в организацию.',
            $contract->fresh()->pdfGenerationError(),
        );
    }

    public function test_validate_field_input_accepts_split_parent_name_without_parent_full_name_key(): void
    {
        $schema = ContractTemplateVariablePresets::schemaFieldsForParentForm([
            ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
        ]);

        app(ContractPdfGenerationService::class)->validateFieldInput($schema, [
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
        ]);

        $this->assertTrue(true);
    }

    public function test_generate_with_split_parent_fields_composes_parent_full_name_in_filled_data(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'   => 'Баранова',
                'parent_firstname'  => 'Мальвина',
                'parent_middlename' => 'Алексеевич',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('Баранова Мальвина Алексеевич', $contract->filled_data['parent_full_name'] ?? null);
        $this->assertNull($contract->pdfGenerationError());
    }
}
