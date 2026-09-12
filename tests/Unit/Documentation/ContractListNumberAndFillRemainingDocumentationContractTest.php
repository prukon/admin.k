<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-list-number-and-fill-remaining-index совпадает со списком договоров:
 * колонка «Номер договора», остаток дней во «Срок подписания», дефолт сортировки по id.
 */
final class ContractListNumberAndFillRemainingDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_number_column_remaining_days_and_id_sort(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-list-number-and-fill-remaining-index"', $html);
        $start = strpos($html, 'id="contract-list-number-and-fill-remaining-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-list-fill-expires-at-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/client-contracts', $chunk);
        $this->assertStringContainsString('Номер договора', $chunk);
        $this->assertStringContainsString('contracts.id', $chunk);
        $this->assertStringContainsString('#colContractNumber', $chunk);
        $this->assertStringContainsString('data-column-key="contract_number"', $chunk);
        $this->assertStringContainsString("order: [[1, 'desc']]", $chunk);
        $this->assertStringContainsString('fill_expires_remaining', $chunk);
        $this->assertStringContainsString('fill_expires_remaining_warn', $chunk);
        $this->assertStringContainsString('Срок истек.', $chunk);
        $this->assertStringContainsString('Последний день', $chunk);
        $this->assertStringContainsString('CLIENT_FILL_REMAINING_LAST_DAY', $chunk);
        $this->assertStringContainsString('SCHOOL_LIST_FILL_REMAINING_EXPIRED', $chunk);
        $this->assertStringContainsString('CLIENT_FILL_REMAINING_DAYS_WARN', $chunk);
        $this->assertStringContainsString('text-danger', $chunk);
        $this->assertStringContainsString('индекс 10', $chunk);
        $this->assertStringContainsString('индекс 9', $chunk);
        $this->assertStringContainsString('contracts §1.5', $chunk);
        $this->assertStringContainsString('ContractsTableAndColumnsTest', $chunk);
        $this->assertStringContainsString('ContractSchoolListFillRemainingDaysTest', $chunk);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingUxFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-list-number-and-fill-remaining-index', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $contracts = $this->docFile('contracts.html');
        $fill = $this->docFile('account-contract-fill.html');
        $partners = $this->docFile('partners-permissions.html');

        $this->assertStringContainsString('/doc#contract-list-number-and-fill-remaining-index', $contracts);
        $this->assertStringContainsString('id="contract-list-number"', $contracts);
        $this->assertStringContainsString('#colContractNumber', $contracts);
        $this->assertStringContainsString('data-column-key="contract_number"', $contracts);
        $this->assertStringContainsString('fill_expires_remaining', $contracts);
        $this->assertStringContainsString('SCHOOL_LIST_FILL_REMAINING_EXPIRED', $contracts);
        $this->assertStringContainsString('schoolListFillRemainingDaysLabel', $contracts);
        $this->assertStringContainsString("order: [[1, 'desc']]", $contracts);
        $this->assertStringContainsString('индекс 9 DataTables', $contracts);
        $this->assertStringContainsString('индекс 10', $contracts);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingDocumentationContractTest.php', $contracts);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingAccessFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingFullAccessFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingUxFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingAjaxContractFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractListNumberAndFillRemainingNonAjaxSafetyNetFeatureTest.php', $contracts);

        $this->assertStringContainsString('/doc#contract-list-number-and-fill-remaining-index', $fill);
        $this->assertStringContainsString('/doc#contract-list-number-and-fill-remaining-index', $partners);
    }

    public function test_catalog_and_controller_title_mention_number_and_remaining(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-list-number-and-fill-remaining-index', $index);
        $this->assertStringContainsString('колонка «Номер договора»', $index);
        $this->assertStringContainsString('остаток дней', $index);

        $this->assertStringContainsString('колонка «Номер договора» (contracts.id)', $controller);
        $this->assertStringContainsString('fill_expires_at + остаток дней', $controller);
    }

    public function test_table_controller_and_views_match_documented_number_and_remaining(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractTableController.php');
        $index = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');
        $model = (string) file_get_contents($root.'/app/Models/Contract.php');

        $this->assertStringContainsString("orWhere('contracts.id', 'like', \$like)", $controller);
        $this->assertStringContainsString("orderBy('contracts.id', \$orderDir)", $controller);
        $this->assertStringContainsString("schoolListFillRemainingDaysLabel()", $controller);
        $this->assertStringContainsString('fill_expires_remaining', $controller);
        $this->assertStringContainsString('fill_expires_remaining_warn', $controller);
        $this->assertStringContainsString('case 10:', $controller);

        $this->assertStringContainsString('<th>Номер договора</th>', $index);
        $this->assertStringContainsString('data-column-key="contract_number"', $index);
        $this->assertStringContainsString('for="colContractNumber">Номер договора</label>', $index);
        $this->assertStringContainsString("key: 'contract_number'", $index);
        $this->assertStringContainsString("order: [[1, 'desc']]", $index);
        $this->assertStringContainsString('row.fill_expires_remaining', $index);
        $this->assertStringContainsString('fill_expires_remaining_warn', $index);
        $this->assertStringContainsString('Имя, телефон, email, номер', $index);

        $this->assertStringContainsString("SCHOOL_LIST_FILL_REMAINING_EXPIRED = 'Срок истек.'", $model);
        $this->assertStringContainsString('function schoolListFillRemainingDaysLabel', $model);
        $this->assertStringContainsString('function shouldHighlightSchoolListFillRemainingDays', $model);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
