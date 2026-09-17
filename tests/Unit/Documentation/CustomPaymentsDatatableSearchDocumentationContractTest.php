<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#custom-payments-datatable-search-index совпадает с поиском DataTables
 * на вкладке доп. платежей: ФИО / группа / примечание, без autoFilter 42S22.
 */
final class CustomPaymentsDatatableSearchDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_custom_payments_datatable_search(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="custom-payments-datatable-search-index"', $html);
        $start = strpos($html, 'id="custom-payments-datatable-search-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="users-contract-create-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/custom-payments', $chunk);
        $this->assertStringContainsString('search.value', $chunk);
        $this->assertStringContainsString('filter($callback)', $chunk);
        $this->assertStringContainsString('user_custom_payment.user_name', $chunk);
        $this->assertStringContainsString('42S22', $chunk);
        $this->assertStringContainsString('users.lastname', $chunk);
        $this->assertStringContainsString('teams.title', $chunk);
        $this->assertStringContainsString('user_custom_payment.note', $chunk);
        $this->assertStringContainsString('amount_cents', $chunk);
        $this->assertStringContainsString('searchable: false', $chunk);
        $this->assertStringContainsString('CustomPaymentsDatatableSearchFeatureTest', $chunk);
        $this->assertStringContainsString('CustomPaymentsDatatableSearchFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('setting-prices-custom-payments#datatable-search', $chunk);
    }

    public function test_custom_payments_doc_describes_datatable_search(): void
    {
        $html = $this->docFile('setting-prices-custom-payments.html');

        $this->assertStringContainsString('id="datatable-search"', $html);
        $this->assertStringContainsString('/doc#custom-payments-datatable-search-index', $html);
        $this->assertStringContainsString('applyCustomPaymentsDataTableSearch', $html);
        $this->assertStringContainsString('filter($callback)', $html);
        $this->assertStringContainsString('<b>без</b> второго аргумента <code>true</code>', $html);
        $this->assertStringContainsString('user_custom_payment.note', $html);
        $this->assertStringContainsString('searchable: false', $html);
        $this->assertStringContainsString('CustomPaymentsDatatableSearchFeatureTest', $html);
        $this->assertStringContainsString('CustomPaymentsDatatableSearchFullAccessFeatureTest', $html);
    }

    public function test_catalog_mentions_datatable_search_announcement(): void
    {
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('/doc#custom-payments-datatable-search-index', $index);
        $this->assertStringContainsString('поиск DataTables ФИО/группа/примечание', $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
