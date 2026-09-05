<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-datatable-search-index совпадает с поиском DataTables
 * на payments / LTV / monthly / payment-intents (не autoFilter, не фильтры формы).
 */
final class ReportsDatatableSearchDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_report_datatable_search(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-datatable-search-index"', $html);
        $start = strpos($html, 'id="reports-datatable-search-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="permission-capability-hints-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('filter($callback)', $chunk);
        $this->assertStringContainsString('без</b> второго аргумента', $chunk);
        $this->assertStringContainsString('autoFilter', $chunk);
        $this->assertStringContainsString('не</b> фильтры формы', $chunk);
        $this->assertStringContainsString('/admin/reports/payments', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv', $chunk);
        $this->assertStringContainsString('/admin/reports/payments/monthly', $chunk);
        $this->assertStringContainsString('/admin/reports/payment-intents', $chunk);
        $this->assertStringContainsString('reports.view', $chunk);
        $this->assertStringContainsString('reports.payment.intents.view', $chunk);
        $this->assertStringContainsString('payments.user_name', $chunk);
        $this->assertStringContainsString('Январь 2026', $chunk);
        $this->assertStringContainsString("dom: 'rtip'", $chunk);
        $this->assertStringContainsString('provider_inv_id', $chunk);
        $this->assertStringContainsString('CAST', $chunk);
        $this->assertStringContainsString('addcslashes', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('searching: false', $chunk);
        $this->assertStringContainsString('Задолженности, чеки и исходящие письма', $chunk);
        $this->assertStringContainsString('ReportsDatatableSearchFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsMonthlyIntentsDatatableSearchFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsDatatableSearchFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsDatatableSearchDocumentationContractTest', $chunk);
        $this->assertStringContainsString('reports-payments#datatable-search', $chunk);
        $this->assertStringContainsString('reports-admin#reports-datatable-search', $chunk);

        $this->assertStringNotContainsString('searching: false</code> на основных таблицах четырёх отчётов', $chunk);
        $this->assertStringNotContainsString('вложенная LTV ищет ФИО', $chunk);
        $this->assertStringNotContainsString('задолженности ищут ФИО', $chunk);
    }

    public function test_related_doc_pages_link_search_announcement(): void
    {
        $payments = $this->docFile('reports-payments.html');
        $reports = $this->docFile('reports-admin.html');
        $ui = $this->docFile('reusable-ui-partials.html');

        $this->assertStringContainsString('id="datatable-search"', $payments);
        $this->assertStringContainsString('/doc#reports-datatable-search-index', $payments);
        $this->assertStringContainsString('applyPaymentsDataTableSearch', $payments);
        $this->assertStringContainsString('filter($callback)</code> <b>без</b> второго аргумента', $payments);

        $this->assertStringContainsString('id="reports-datatable-search"', $reports);
        $this->assertStringContainsString('/doc#reports-datatable-search-index', $reports);
        $this->assertStringContainsString('applyLtvDataTableSearch', $reports);
        $this->assertStringContainsString('applyMonthlyDataTableSearch', $reports);
        $this->assertStringContainsString('applyPaymentIntentsDataTableSearch', $reports);
        $this->assertStringContainsString('своего <code>filter()</code> не ставит', $reports);
        $this->assertStringContainsString("dom: 'rtip'", $reports);

        $this->assertStringContainsString("dom: 'rtip'", $ui);
        $this->assertStringContainsString('/doc#reports-datatable-search-index', $ui);
        $this->assertStringContainsString('reports-admin#reports-datatable-search', $ui);
    }

    public function test_documentation_controller_titles_mention_search(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('поиск DataTables только ФИО и группа', $controller);
        $this->assertStringContainsString('поиск DataTables payments/LTV/monthly/intents', $controller);
    }

    public function test_announcement_matches_controllers_and_blades(): void
    {
        $payments = $this->appFile('Http/Controllers/Admin/Report/PaymentReportController.php');
        $ltv = $this->appFile('Http/Controllers/Admin/Report/LtvReportController.php');
        $monthly = $this->appFile('Http/Controllers/Admin/Report/PaymentMonthlyReportController.php');
        $intents = $this->appFile('Http/Controllers/Admin/Report/PaymentIntentReportController.php');

        $this->assertStringContainsString('applyPaymentsDataTableSearch', $payments);
        $this->assertStringContainsString('addcslashes($keyword', $payments);
        $this->assertStringContainsString('if (! $request->ajax())', $payments);

        $this->assertStringContainsString('applyLtvDataTableSearch', $ltv);
        $this->assertStringContainsString('filter(function ($query) use ($request, $partnerId): void {', $ltv);
        $this->assertStringNotContainsString(
            'applyLtvDataTableSearch',
            $this->extractGetUserPayments($ltv)
        );

        $this->assertStringContainsString('applyMonthlyDataTableSearch', $monthly);
        $this->assertStringContainsString('monthNumbersFromSearchKeyword', $monthly);
        $this->assertStringContainsString("'январ' => '01'", $monthly);

        $this->assertStringContainsString('applyPaymentIntentsDataTableSearch', $intents);
        $this->assertStringContainsString('CAST(payment_intents.id AS CHAR)', $intents);
        $this->assertStringNotContainsString('if (! $request->ajax())', $intents);

        $paymentBlade = $this->viewFile('admin/report/payment.blade.php');
        $ltvBlade = $this->viewFile('admin/report/ltv.blade.php');
        $monthlyBlade = $this->viewFile('admin/report/payment_monthly.blade.php');
        $intentsBlade = $this->viewFile('admin/report/payment_intents.blade.php');

        foreach ([$paymentBlade, $ltvBlade, $monthlyBlade, $intentsBlade] as $blade) {
            $createPos = strpos($blade, 'KidsCrmDataTable.create');
            $this->assertNotFalse($createPos);
            $createChunk = substr($blade, $createPos, 3500);
            $this->assertStringNotContainsString('searching: false', $createChunk);
        }

        $this->assertStringContainsString("dom: 'rtip'", $ltvBlade);
        $this->assertStringContainsString("dom: 'rtip'", $monthlyBlade);
        $this->assertStringContainsString('dtApi.reload();', $paymentBlade);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true });', $ltvBlade);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true });', $monthlyBlade);
    }

    private function extractGetUserPayments(string $ltvController): string
    {
        $start = strpos($ltvController, 'function getUserPayments');
        $this->assertNotFalse($start);
        $end = strpos($ltvController, 'function getColumnsSettings');
        $this->assertNotFalse($end);

        return substr($ltvController, $start, $end - $start);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function appFile(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/app/'.$relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function viewFile(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/resources/views/'.$relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
