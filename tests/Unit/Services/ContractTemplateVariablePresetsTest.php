<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Contracts\ContractTemplateVariablePresets;
use App\Services\Contracts\DocxPlaceholderExtractor;
use Tests\TestCase;
use ZipArchive;

class ContractTemplateVariablePresetsTest extends TestCase
{
    /** @test */
    public function child_full_name_and_child_birthday_apply_defaults_when_building_schema(): void
    {
        $path = $this->makeDocxWithText('{{child_full_name}} {{child_birthday}}');

        $extractor = new DocxPlaceholderExtractor();
        $schema = $extractor->buildFieldsSchema($extractor->extractFromPath($path));

        $this->assertSame('child_full_name', $schema[0]['key']);
        $this->assertSame('Ребёнок: ФИО', $schema[0]['label']);
        $this->assertSame(ContractTemplatePrefillSources::CHILD_FULL_NAME, $schema[0]['prefill_source']);

        $this->assertSame('child_birthday', $schema[1]['key']);
        $this->assertSame('Ребёнок: дата рождения', $schema[1]['label']);
        $this->assertSame(ContractTemplatePrefillSources::CHILD_BIRTHDAY, $schema[1]['prefill_source']);

        @unlink($path);
    }

    /** @test */
    public function build_fields_schema_renames_child_birth_year_to_child_birthday(): void
    {
        $extractor = new DocxPlaceholderExtractor();
        $schema = $extractor->buildFieldsSchema(['child_birth_year']);

        $this->assertSame('child_birthday', $schema[0]['key']);
        $this->assertSame('Ребёнок: дата рождения', $schema[0]['label']);
        $this->assertSame(ContractTemplatePrefillSources::CHILD_BIRTHDAY, $schema[0]['prefill_source']);
    }

    /** @test */
    public function group_labels_has_no_other_and_spouse_fields_are_under_contract(): void
    {
        $labels = ContractTemplateVariablePresets::groupLabels();

        $this->assertArrayNotHasKey('other', $labels);
        $this->assertNotContains('Дополнительно', $labels);

        $contractKeys = array_column(
            ContractTemplateVariablePresets::recommendedForGroup(ContractTemplateVariablePresets::GROUP_CONTRACT),
            'key',
        );

        $this->assertContains('spouse_full_name', $contractKeys);
        $this->assertContains('trusted_person_1_fio', $contractKeys);
        $this->assertContains('contract_date', $contractKeys);
        $this->assertContains('documents_url', $contractKeys);
        $this->assertContains('contract_id', $contractKeys);
    }

    /** @test */
    public function sort_fields_for_admin_editor_puts_crm_first_parent_and_system_last(): void
    {
        $sorted = ContractTemplateVariablePresets::sortFieldsForAdminEditor([
            ['key' => 'contract_date'],
            ['key' => 'spouse_phones'],
            ['key' => 'parent_full_name'],
            ['key' => 'child_full_name'],
            ['key' => 'trusted_person_1_fio'],
        ]);

        $this->assertSame(
            ['parent_full_name', 'child_full_name', 'spouse_phones', 'trusted_person_1_fio', 'contract_date'],
            array_column($sorted, 'key'),
        );
    }

    /** @test */
    public function enrich_field_marks_contract_date_as_system_autofill(): void
    {
        $enriched = ContractTemplateVariablePresets::enrichField([
            'key'            => 'contract_date',
            'label'          => 'contract date',
            'required'       => true,
            'prefill_source' => 'parent_phone',
        ]);

        $this->assertSame('contract_date', $enriched['key']);
        $this->assertSame('Дата договора', $enriched['label']);
        $this->assertFalse($enriched['required']);
        $this->assertNull($enriched['prefill_source']);
        $this->assertSame(ContractTemplateVariablePresets::FILL_MODE_SYSTEM, ContractTemplateVariablePresets::fillModeForKey('contract_date'));
    }

    /** @test */
    public function system_placeholders_include_contract_date(): void
    {
        $contract = new \App\Models\Contract(['id' => 42]);
        $values = \App\Services\Contracts\ContractTemplateSystemPlaceholders::forContract($contract);

        $this->assertArrayHasKey('contract_date', $values);
        $this->assertMatchesRegularExpression('/^\d{2}\.\d{2}\.\d{4}$/', $values['contract_date']);
    }

    /** @test */
    public function schema_fields_for_parent_form_excludes_contract_date(): void
    {
        $filtered = ContractTemplateVariablePresets::schemaFieldsForParentForm([
            ['key' => 'parent_full_name', 'label' => 'ФИО', 'required' => true],
            ['key' => 'contract_date', 'label' => 'Дата', 'required' => false],
        ]);

        $this->assertCount(3, $filtered);
        $keys = array_column($filtered, 'key');
        $this->assertSame(
            ['parent_lastname', 'parent_firstname', 'parent_middlename'],
            $keys,
        );
        $this->assertTrue($filtered[0]['required']);
        $this->assertTrue($filtered[1]['required']);
        $this->assertFalse($filtered[2]['required']);
    }

    /** @test */
    public function schema_fields_for_parent_form_splits_child_full_name_into_parts(): void
    {
        $filtered = ContractTemplateVariablePresets::schemaFieldsForParentForm([
            ['key' => 'child_full_name', 'label' => 'ФИО ребёнка', 'required' => true],
        ]);

        $this->assertSame(
            ['child_lastname', 'child_firstname'],
            array_column($filtered, 'key'),
        );
    }

    /** @test */
    public function compose_name_fields_for_pdf_builds_full_name_from_parts(): void
    {
        $composed = ContractTemplateVariablePresets::composeNameFieldsForPdf([
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_middlename' => 'Иванович',
            'child_lastname'   => 'Петров',
            'child_firstname'  => 'Пётр',
        ]);

        $this->assertSame('Иванов Иван Иванович', $composed['parent_full_name']);
        $this->assertSame('Петров Пётр', $composed['child_full_name']);
    }

    /** @test */
    public function enrich_field_marks_spouse_phones_as_optional(): void
    {
        $preset = ContractTemplateVariablePresets::recommendedByKey()['spouse_phones'];

        $enriched = ContractTemplateVariablePresets::enrichField([
            'key'      => $preset['key'],
            'label'    => 'spouse phones',
            'required' => true,
        ]);

        $this->assertSame($preset['key'], $enriched['key']);
        $this->assertSame($preset['label'], $enriched['label']);
        $this->assertSame($preset['required_default'], $enriched['required']);
    }

    /** @test */
    public function enrich_field_renames_child_birth_year_to_child_birthday(): void
    {
        $enriched = ContractTemplateVariablePresets::enrichField([
            'key'            => 'child_birth_year',
            'label'          => 'child birth year',
            'required'       => true,
            'prefill_source' => null,
        ]);

        $this->assertSame('child_birthday', $enriched['key']);
        $this->assertSame('Ребёнок: дата рождения', $enriched['label']);
        $this->assertSame(ContractTemplatePrefillSources::CHILD_BIRTHDAY, $enriched['prefill_source']);
    }

    /** @test */
    public function enrich_field_renames_legacy_child_birth_year_even_with_old_russian_label(): void
    {
        $enriched = ContractTemplateVariablePresets::enrichField([
            'key'            => 'child_birth_year',
            'label'          => 'Ребёнок: год рождения',
            'required'       => true,
            'prefill_source' => null,
        ]);

        $this->assertSame('child_birthday', $enriched['key']);
        $this->assertSame('Ребёнок: дата рождения', $enriched['label']);
        $this->assertTrue($enriched['required']);
        $this->assertSame(ContractTemplatePrefillSources::CHILD_BIRTHDAY, $enriched['prefill_source']);
    }

    /** @test */
    public function enrich_field_applies_preset_for_child_full_name(): void
    {
        $enriched = ContractTemplateVariablePresets::enrichField([
            'key'            => 'child_full_name',
            'label'          => 'child full name',
            'required'       => true,
            'prefill_source' => null,
        ]);

        $this->assertSame('Ребёнок: ФИО', $enriched['label']);
        $this->assertSame(ContractTemplatePrefillSources::CHILD_FULL_NAME, $enriched['prefill_source']);
    }

    /** @test */
    public function recommended_preset_applies_defaults_when_building_schema(): void
    {
        $path = $this->makeDocxWithText('{{parent_full_name}} {{parent_passport}}');

        $extractor = new DocxPlaceholderExtractor();
        $schema = $extractor->buildFieldsSchema($extractor->extractFromPath($path));

        $this->assertSame('parent_full_name', $schema[0]['key']);
        $this->assertSame('Родитель: ФИО', $schema[0]['label']);
        $this->assertSame(ContractTemplatePrefillSources::PARENT_FULL_NAME, $schema[0]['prefill_source']);

        $this->assertSame('parent_passport', $schema[1]['key']);
        $this->assertSame('Родитель: паспорт', $schema[1]['label']);
        $this->assertSame(ContractTemplatePrefillSources::PARENT_PASSPORT, $schema[1]['prefill_source']);

        @unlink($path);
    }

    /** @test */
    public function prefill_source_keys_use_snake_case_without_dots(): void
    {
        foreach (ContractTemplatePrefillSources::keys() as $key) {
            $this->assertDoesNotMatchRegularExpression('/\./', $key);
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key);
        }
    }

    /** @test */
    public function fill_form_date_helpers_convert_between_html_and_docx_formats(): void
    {
        $this->assertTrue(ContractTemplateVariablePresets::isFillFormDateField('child_birthday'));
        $this->assertFalse(ContractTemplateVariablePresets::isFillFormDateField('contract_date'));

        $this->assertSame('2018-05-10', ContractTemplateVariablePresets::dateValueForFillInput('10.05.2018'));
        $this->assertSame('10.05.2018', ContractTemplateVariablePresets::normalizeFillFormDateValue('2018-05-10'));
    }

    /** @test */
    public function group_fields_for_parent_form_puts_spouse_fields_at_the_end(): void
    {
        $grouped = ContractTemplateVariablePresets::groupFieldsForParentForm([
            ['key' => 'spouse_phones'],
            ['key' => 'parent_phone', 'label' => 'Телефон'],
            ['key' => 'spouse_full_name', 'label' => 'Супруг(а): ФИО'],
            ['key' => 'parent_lastname', 'label' => 'Фамилия'],
            ['key' => 'custom_parent_note', 'label' => 'Примечание'],
        ]);

        $this->assertSame(
            [
                'parent_lastname',
                'parent_phone',
                'spouse_full_name',
                'spouse_phones',
                'custom_parent_note',
            ],
            array_column($grouped[ContractTemplateVariablePresets::GROUP_PARENT], 'key'),
        );
    }

    /** @test */
    public function group_fields_for_parent_form_respects_fill_sort_order_override(): void
    {
        $grouped = ContractTemplateVariablePresets::groupFieldsForParentForm([
            ['key' => 'custom_early', 'fill_sort_order' => 15],
            ['key' => 'parent_lastname'],
        ]);

        $this->assertSame(
            ['parent_lastname', 'custom_early'],
            array_column($grouped[ContractTemplateVariablePresets::GROUP_PARENT], 'key'),
        );
    }

    /** @test */
    public function group_fields_for_parent_form_orders_parent_fields_by_profile_blocks(): void
    {
        $grouped = ContractTemplateVariablePresets::groupFieldsForParentForm([
            ['key' => 'parent_address', 'label' => 'Адрес'],
            ['key' => 'parent_passport', 'label' => 'Паспорт'],
            ['key' => 'parent_email', 'label' => 'Email'],
            ['key' => 'parent_phone', 'label' => 'Телефон'],
            ['key' => 'parent_lastname', 'label' => 'Фамилия'],
            ['key' => 'parent_firstname', 'label' => 'Имя'],
            ['key' => 'parent_middlename', 'label' => 'Отчество'],
        ]);

        $this->assertSame(
            [
                'parent_lastname',
                'parent_firstname',
                'parent_middlename',
                'parent_phone',
                'parent_email',
                'parent_passport',
                'parent_address',
            ],
            array_column($grouped[ContractTemplateVariablePresets::GROUP_PARENT], 'key'),
        );
    }

    /** @test */
    public function group_fields_for_parent_form_orders_child_fields_by_profile_blocks(): void
    {
        $grouped = ContractTemplateVariablePresets::groupFieldsForParentForm([
            ['key' => 'child_birthday', 'label' => 'Дата рождения'],
            ['key' => 'student_email', 'label' => 'Email'],
            ['key' => 'student_phone', 'label' => 'Телефон'],
            ['key' => 'child_firstname', 'label' => 'Имя'],
            ['key' => 'child_lastname', 'label' => 'Фамилия'],
        ]);

        $this->assertSame(
            ['child_lastname', 'child_firstname', 'student_phone', 'student_email', 'child_birthday'],
            array_column($grouped[ContractTemplateVariablePresets::GROUP_CHILD], 'key'),
        );
    }

    /** @test */
    public function group_fields_for_parent_form_splits_parent_and_child(): void
    {
        $grouped = ContractTemplateVariablePresets::groupFieldsForParentForm([
            ['key' => 'parent_full_name', 'label' => 'Родитель: ФИО'],
            ['key' => 'child_full_name', 'label' => 'Ребёнок: ФИО'],
            ['key' => 'custom_parent_note', 'label' => 'Примечание'],
        ]);

        $this->assertCount(2, $grouped[ContractTemplateVariablePresets::GROUP_PARENT]);
        $this->assertCount(1, $grouped[ContractTemplateVariablePresets::GROUP_CHILD]);
        $this->assertSame('custom_parent_note', $grouped[ContractTemplateVariablePresets::GROUP_PARENT][1]['key']);
        $this->assertSame('ФИО', ContractTemplateVariablePresets::fillFormFieldLabel('Родитель: ФИО', ContractTemplateVariablePresets::GROUP_PARENT));
        $this->assertSame('ФИО', ContractTemplateVariablePresets::fillFormFieldLabel('Ребёнок: ФИО', ContractTemplateVariablePresets::GROUP_CHILD));
        $this->assertSame('Паспорт', ContractTemplateVariablePresets::fillFormFieldLabel('Родитель: паспорт', ContractTemplateVariablePresets::GROUP_PARENT));
        $this->assertSame('Адрес регистрации', ContractTemplateVariablePresets::fillFormFieldLabel('Родитель: адрес регистрации', ContractTemplateVariablePresets::GROUP_PARENT));
        $this->assertSame('Паспорт, кем и когда выдан', ContractTemplateVariablePresets::fillFormFieldLabel('Родитель: паспорт, кем и когда выдан', ContractTemplateVariablePresets::GROUP_PARENT));
        $this->assertSame('Телефон', ContractTemplateVariablePresets::fillFormFieldLabel('Родитель: телефон', ContractTemplateVariablePresets::GROUP_PARENT));
        $this->assertSame('Email', ContractTemplateVariablePresets::fillFormFieldLabel('Родитель: email', ContractTemplateVariablePresets::GROUP_PARENT));
        $this->assertSame('Дата рождения', ContractTemplateVariablePresets::fillFormFieldLabel('Ребёнок: дата рождения', ContractTemplateVariablePresets::GROUP_CHILD));
    }

    private function makeDocxWithText(string $text): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>' . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r></w:p></w:body></w:document>'
        );
        $zip->close();

        return $tmp;
    }
}
