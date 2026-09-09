<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-tables-sticky-header-index совпадает с четырьмя вкладками отчётов.
 */
final class ReportsTablesStickyHeaderDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_reports_sticky_header_and_hscroll(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-tables-sticky-header-index"', $html);
        $start = strpos($html, 'id="reports-tables-sticky-header-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="school-lead-edit-modal-width-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/payments', $chunk);
        $this->assertStringContainsString('/admin/reports/payments/monthly', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv', $chunk);
        $this->assertStringContainsString('/admin/reports/debts', $chunk);
        $this->assertStringContainsString('KidsCrmDataTable.create(\'#payments-table\')', $chunk);
        $this->assertStringContainsString('#payments-monthly-table', $chunk);
        $this->assertStringContainsString('#ltv-table', $chunk);
        $this->assertStringContainsString('#debts-table', $chunk);
        $this->assertStringContainsString('header: true', $chunk);
        $this->assertStringContainsString('footer: false', $chunk);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $chunk);
        $this->assertStringContainsString('kids-dt-scroll-x', $chunk);
        $this->assertStringContainsString('datatables-fixedheader', $chunk);
        $this->assertStringContainsString('<code>scrollY</code>', $chunk);
        $this->assertStringContainsString('это не', $chunk);
        $this->assertStringContainsString('scrollLeft', $chunk);
        $this->assertStringContainsString('не через', $chunk);
        $this->assertStringContainsString('margin-left', $chunk);
        $this->assertStringContainsString('admin-reports-tables.css', $chunk);
        $this->assertStringContainsString('admin-reports-tables-sticky.js', $chunk);
        $this->assertStringContainsString('Vite', $chunk);
        $this->assertStringContainsString('resources/css/admin-reports-tables.css', $chunk);
        $this->assertStringContainsString('reports-admin#reports-tables-sticky-header', $chunk);
        $this->assertStringContainsString('reports-payments#reports-tables-sticky-header', $chunk);
        $this->assertStringContainsString('reports.view', $chunk);
        $this->assertStringContainsString('KidsCrmReportTableSticky.bind', $chunk);
        $this->assertStringContainsString('paymentsAfterApplyVisibleColumns', $chunk);
        $this->assertStringContainsString('dtApi.reload', $chunk);
        $this->assertStringContainsString('page_length', $chunk);
        $this->assertStringContainsString('ReportsTablesStickyHeaderFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsTablesStickyHeaderFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('test_admin_reports_sticky_header_nested_tables_column_toggle_and_filter_reload_keep_pin', $chunk);
        $this->assertStringContainsString('ReportsTablesStickyHeaderDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#reports-tables-sticky-header-index', $html);
    }

    public function test_related_docs_and_blade_match_sticky_header_contract(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $payments = $this->docFile('reports-payments.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/admin-reports-tables.css');
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/admin-reports-tables-sticky.js');
        $vite = (string) file_get_contents(dirname(__DIR__, 3).'/vite.config.js');

        $this->assertStringContainsString('id="reports-tables-sticky-header"', $reports);
        $this->assertStringContainsString('/doc#reports-tables-sticky-header-index', $reports);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $reports);
        $this->assertStringContainsString('header: true, footer: false', $reports);
        $this->assertStringContainsString('KidsCrmReportTableSticky.bind', $reports);
        $this->assertStringContainsString('paymentsAfterApplyVisibleColumns', $reports);
        $this->assertStringContainsString('reports.view', $reports);
        $this->assertStringContainsString('getBoundingClientRect', $reports);
        $this->assertStringContainsString('overflow: hidden', $reports);
        $this->assertStringContainsString('scrollLeft', $reports);
        $this->assertStringContainsString('не <code>margin-left</code>', $reports);
        $this->assertStringContainsString('box-sizing: border-box', $reports);
        $this->assertStringContainsString('resources/css/admin-reports-tables.css', $reports);
        $this->assertStringContainsString('resources/js/admin-reports-tables-sticky.js', $reports);
        $this->assertStringContainsString('vite.config.js', $reports);
        $this->assertStringContainsString('@vite', $reports);
        $this->assertStringNotContainsString('public/css/admin-reports-tables.css', $reports);

        $this->assertStringContainsString('id="reports-tables-sticky-header"', $payments);
        $this->assertStringContainsString('/doc#reports-tables-sticky-header-index', $payments);
        $this->assertStringContainsString('admin-reports-tables.css', $payments);
        $this->assertStringContainsString('header: true, footer: false', $payments);

        $this->assertStringContainsString('reports-admin#reports-tables-sticky-header', $ui);
        $this->assertStringContainsString('admin-reports-tables.css', $ui);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $ui);
        $this->assertStringNotContainsString('public/css/admin-reports-tables.css', $ui);

        $viteTag = "@vite(['resources/css/admin-list-toolbar.css', 'resources/css/admin-reports-tables.css', 'resources/js/admin-reports-tables-sticky.js'])";
        $blades = [
            'admin/report/payment.blade.php' => '#payments-table',
            'admin/report/payment_monthly.blade.php' => '#payments-monthly-table',
            'admin/report/ltv.blade.php' => '#ltv-table',
            'admin/report/debt.blade.php' => '#debts-table',
        ];
        foreach ($blades as $relative => $selector) {
            $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/'.$relative);
            $this->assertStringContainsString($viteTag, $blade, $relative);
            $this->assertStringNotContainsString("asset('css/admin-reports-tables.css')", $blade, $relative);
            $this->assertStringContainsString('dataTables.fixedHeader.min.js', $blade, $relative);
            $this->assertStringContainsString('KidsCrmReportTableSticky.bind(\''.$selector.'\')', $blade, $relative);
            $this->assertStringContainsString('header: true', $blade, $relative);
            $this->assertStringContainsString('footer: false', $blade, $relative);
            $this->assertStringNotContainsString("margin-left', (-", $blade, $relative);
            $this->assertStringNotContainsString('scrollY:', $blade, $relative);
        }

        $this->assertStringContainsString('.dtfh-floatingparenthead', $css);
        $this->assertStringContainsString('overflow: hidden', $css);
        $this->assertStringContainsString('position: sticky', $css);
        $this->assertStringContainsString('overflow-x: scroll', $css);
        $this->assertFileDoesNotExist(dirname(__DIR__, 3).'/public/css/admin-reports-tables.css');
        $this->assertStringContainsString("'resources/css/admin-reports-tables.css'", $vite);
        $this->assertStringContainsString("'resources/js/admin-reports-tables-sticky.js'", $vite);

        $this->assertStringContainsString('window.KidsCrmReportTableSticky', $js);
        $this->assertStringContainsString('getBoundingClientRect()', $js);
        $this->assertStringContainsString('parent.scrollLeft', $js);

        $this->assertStringContainsString('закрепление thead и горизонтального скролла', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
