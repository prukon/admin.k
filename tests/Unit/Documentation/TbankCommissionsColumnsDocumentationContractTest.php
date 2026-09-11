<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#tbank-commissions-columns-index совпадает с кнопкой «Колонки»
 * на /admin/settings/tbank-commissions (не отчёт «Платежи T‑Bank»).
 */
final class TbankCommissionsColumnsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_commissions_columns_toolbar(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="tbank-commissions-columns-index"', $html);
        $start = strpos($html, 'id="tbank-commissions-columns-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-tbank-payments-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/settings/tbank-commissions', $chunk);
        $this->assertStringContainsString('settings.commission', $chunk);
        $this->assertStringContainsString('tbank_commissions_index', $chunk);
        $this->assertStringContainsString('data-column-key', $chunk);
        $this->assertStringContainsString('persistPageLength', $chunk);
        $this->assertStringContainsString('$tbankCommissionsPageLength', $chunk);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $chunk);
        $this->assertStringContainsString('errors.columns', $chunk);
        $this->assertStringContainsString('errors.page_length', $chunk);
        $this->assertStringContainsString('whereNumber', $chunk);
        $this->assertStringContainsString('tbank#commissions-ui', $chunk);
        $this->assertStringContainsString('TbankCommissionsColumnsSettingsFeatureTest', $chunk);
        $this->assertStringContainsString('TbankCommissionsColumnsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#tbank-commissions-columns-index', $chunk);

        $this->assertStringNotContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringNotContainsString('reports_tbank_payments', $chunk);
        $this->assertStringNotContainsString('сумма текущей страницы пагинации', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_do_not_mix_report(): void
    {
        $tbank = $this->docFile('tbank.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $reports = $this->docFile('reports-admin.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('id="commissions-ui"', $tbank);
        $this->assertStringContainsString('/doc#tbank-commissions-columns-index', $tbank);
        $this->assertStringContainsString('TbankCommissionsColumnsDocumentationContractTest', $tbank);
        $this->assertStringContainsString('tbank_commissions_index', $tbank);
        $this->assertStringContainsString('settings.commission', $tbank);

        $this->assertStringContainsString('/doc#tbank-commissions-columns-index', $ui);
        $this->assertStringContainsString('tbank_commissions_index', $ui);

        $this->assertStringContainsString('/doc#tbank-commissions-columns-index', $reports);
        $this->assertStringContainsString('/admin/settings/tbank-commissions', $reports);
        $this->assertStringContainsString('reports_tbank_payments', $reports);

        $this->assertStringContainsString('/doc#tbank-commissions-columns-index', $index);
        $this->assertStringContainsString('id="datatable-page-length-catalog"', $index);
        $this->assertStringContainsString('<code>tbank_commissions_index</code>', $index);
    }

    public function test_live_code_matches_announced_columns_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root.'/routes/web.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Setting/TbankCommissionsController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/setting/tbankCommissions.blade.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Admin/ColumnsSettingsWithPageLengthSaveRequest.php');

        $this->assertStringContainsString("Route::middleware('can:settings.commission')", $routes);
        $this->assertStringContainsString("admin/settings/tbank-commissions/columns-settings", $routes);
        $this->assertStringContainsString("->name('admin.setting.tbankCommissions.columns-settings.get')", $routes);
        $this->assertStringContainsString("->name('admin.setting.tbankCommissions.columns-settings.save')", $routes);
        $this->assertStringContainsString("->name('logs.data.tbank-commission')", $routes);
        $this->assertStringContainsString("->whereNumber('id')", $routes);

        $this->assertStringContainsString("private const TABLE_KEY = 'tbank_commissions_index'", $controller);
        $this->assertStringContainsString('tbankCommissionsPageLength', $controller);
        $this->assertStringContainsString('ColumnsSettingsWithPageLengthSaveRequest', $controller);
        $this->assertStringContainsString('pageLengthForUser', $controller);
        $this->assertStringContainsString("redirect()->route('admin.setting.tbankCommissions', ['edit' => \$id])", $controller);

        $this->assertStringNotContainsString("@if((\$mode ?? 'list') === 'edit')", $blade);
        $this->assertStringNotContainsString('Правка правила', $blade);
        $listStart = strpos($blade, "@vite(['resources/css/admin-list-toolbar.css'])");
        $this->assertNotFalse($listStart);

        $listChunk = substr($blade, $listStart);
        $this->assertStringContainsString('id="tbankCommissionsColumnsDropdown"', $listChunk);
        $this->assertStringContainsString('id="tbankCommissionEditModal"', $listChunk);
        $this->assertStringContainsString("linkClass: 'js-tbank-commission-edit'", $listChunk);
        $this->assertStringContainsString("'.js-tbank-commission-edit'", $listChunk);
        $this->assertStringContainsString('persistPageLength: true', $listChunk);
        $this->assertStringContainsString('pageLength: @json((int) ($tbankCommissionsPageLength ?? 10))', $listChunk);
        $this->assertStringContainsString("KidsCrmDataTable.create('#tbank-commissions-table'", $listChunk);
        $this->assertStringContainsString('$form.on(\'submit\'', $listChunk);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $listChunk);
        $this->assertStringContainsString('$(\'#tbank-commissions-filters-reset\').on(\'click\'', $listChunk);
        $this->assertStringNotContainsString('data-column-key="rownum"', $listChunk);
        foreach ([
            'partner_title',
            'method',
            'acquiring_percent',
            'payout_percent',
            'platform_percent',
            'auto_payout',
            'payouts_30d',
            'is_enabled',
            'actions',
        ] as $key) {
            $this->assertStringContainsString('data-column-key="'.$key.'"', $listChunk);
            $this->assertStringContainsString($key.': true', $listChunk);
        }

        $this->assertStringContainsString("'required_without:page_length'", $request);
        $this->assertStringContainsString('Передайте настройки колонок.', $request);
        $this->assertStringContainsString('Можно показать 10, 20, 50 или 100 записей.', $request);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
