<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-list-signed-file-index совпадает со списком договоров:
 * колонка «Договор» — иконка PDF при signed_pdf_path, скачивание в новой вкладке.
 */
final class ContractListSignedFileIconDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_signed_file_icon_column(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-list-signed-file-index"', $html);
        $start = strpos($html, 'id="contract-list-signed-file-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-monthly-team-select-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/client-contracts', $chunk);
        $this->assertStringContainsString('#contracts-table', $chunk);
        $this->assertStringContainsString('колонка «Договор»', $chunk);
        $this->assertStringContainsString('/admin/users', $chunk);
        $this->assertStringContainsString('ведёт на <b>карточку</b>', $chunk);
        $this->assertStringContainsString('signed_pdf_path', $chunk);
        $this->assertStringContainsString('download_signed_url', $chunk);
        $this->assertStringContainsString("route('contracts.downloadSigned')", $chunk);
        $this->assertStringContainsString('revoked', $chunk);
        $this->assertStringContainsString('draft', $chunk);
        $this->assertStringContainsString('fa-solid fa-file-pdf', $chunk);
        $this->assertStringContainsString('#0d6efd', $chunk);
        $this->assertStringContainsString('KidsCrmDataTable.renderIcon', $chunk);
        $this->assertStringContainsString('renderSignedContractFileCell', $chunk);
        $this->assertStringContainsString('download-signed', $chunk);
        $this->assertStringContainsString('target="_blank"', $chunk);
        $this->assertStringContainsString('noopener noreferrer', $chunk);
        $this->assertStringContainsString('не <code>js-dt-nav-link</code>', $chunk);
        $this->assertStringContainsString('signed_file', $chunk);
        $this->assertStringContainsString('тип <code>icon</code>', $chunk);
        $this->assertStringContainsString('#colSignedFile', $chunk);
        $this->assertStringContainsString('индекс 8 DataTables', $chunk);
        $this->assertStringContainsString('errors.file', $chunk);
        $this->assertStringContainsString('contracts.status', $chunk);
        $this->assertStringContainsString('contracts §4.1.1', $chunk);
        $this->assertStringContainsString('contracts §4', $chunk);
        $this->assertStringContainsString('ContractsTableAndColumnsTest', $chunk);
        $this->assertStringContainsString('ContractsDocumentsSectionUiFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListSignedFileIconAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListSignedFileIconFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListSignedFileIconAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListSignedFileIconNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ContractListSignedFileIconUxFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('ContractListSignedFileIconDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-list-signed-file-index', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_describe_column(): void
    {
        $contracts = $this->docFile('contracts.html');

        $this->assertStringContainsString('/doc#contract-list-signed-file-index', $contracts);
        $this->assertStringContainsString('id="contract-list-signed-file"', $contracts);
        $this->assertStringContainsString('download_signed_url', $contracts);
        $this->assertStringContainsString('#colSignedFile', $contracts);
        $this->assertStringContainsString('renderSignedContractFileCell', $contracts);
        $this->assertStringContainsString('KidsCrmDataTable.renderIcon', $contracts);
        $this->assertStringContainsString('fa-solid fa-file-pdf', $contracts);
        $this->assertStringContainsString('signed_pdf_path', $contracts);
        $this->assertStringContainsString('download-signed', $contracts);
        $this->assertStringContainsString('target="_blank"', $contracts);
        $this->assertStringContainsString('индекс 8 DataTables', $contracts);
        $this->assertStringContainsString('id="contracts-pdf-download"', $contracts);
        $this->assertStringContainsString('Три места, критерии разные', $contracts);
        $this->assertStringContainsString('js-dt-nav-link', $contracts);
        $this->assertStringContainsString('errors.file', $contracts);
        $this->assertStringContainsString('ContractsTableAndColumnsTest.php', $contracts);
        $this->assertStringContainsString('ContractListSignedFileIconAccessFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractListSignedFileIconUxFeatureTest.php', $contracts);
        $this->assertStringContainsString('ContractListSignedFileIconDocumentationContractTest.php', $contracts);

        $users = $this->docFile('admin-users.html');
        $this->assertStringContainsString('/doc#contract-list-signed-file-index', $users);
        $this->assertStringContainsString('не скачивание файла', $users);
        $this->assertStringContainsString('contracts §4.1.1', $users);

        $partials = $this->docFile('reusable-ui-partials.html');
        $this->assertStringContainsString('/doc#contract-list-signed-file-index', $partials);
        $this->assertStringContainsString('signed_file', $partials);
        $this->assertStringContainsString('renderSignedContractFileCell', $partials);

        $cabinet = $this->docFile('account-contract-fill.html');
        $this->assertStringContainsString('/doc#contract-list-signed-file-index', $cabinet);
        $this->assertStringContainsString('signed_pdf_path', $cabinet);
        $this->assertStringContainsString('contracts §4', $cabinet);
    }

    public function test_catalog_and_controller_title_mention_signed_file_icon(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-list-signed-file-index', $index);
        $this->assertStringContainsString('колонка «Договор»', $index);
        $this->assertStringContainsString('signed_pdf_path', $index);

        $this->assertStringContainsString('колонка «Договор» = иконка PDF при signed_pdf_path (скачать в новой вкладке)', $controller);
    }

    public function test_table_controller_and_views_match_documented_signed_file_icon(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractTableController.php');
        $index = (string) file_get_contents($root.'/resources/views/contracts/index.blade.php');

        $this->assertStringContainsString('download_signed_url', $controller);
        $this->assertStringContainsString('signedDownloadUrl($contract)', $controller);
        $this->assertStringContainsString("route('contracts.downloadSigned', \$contract)", $controller);
        $this->assertStringContainsString('signed_pdf_path', $controller);
        $this->assertStringContainsString('case 8:', $controller);
        $this->assertStringContainsString('last_event_at is null, last_event_at', $controller);

        $this->assertStringContainsString('<th>Договор</th>', $index);
        $this->assertStringContainsString('data-column-key="signed_file"', $index);
        $this->assertStringContainsString('for="colSignedFile">Договор</label>', $index);
        $this->assertStringContainsString('function renderSignedContractFileCell', $index);
        $this->assertStringContainsString("key: 'signed_file'", $index);
        $this->assertStringContainsString("type: 'icon'", $index);
        $this->assertStringContainsString("data: 'download_signed_url'", $index);
        $this->assertStringContainsString('fa-solid fa-file-pdf', $index);
        $this->assertStringContainsString('#0d6efd', $index);
        $this->assertStringContainsString('KidsCrmDataTable.renderIcon', $index);
        $this->assertStringContainsString("order: [[8, 'desc']]", $index);
        $this->assertStringContainsString('Скачать подписанный договор', $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
