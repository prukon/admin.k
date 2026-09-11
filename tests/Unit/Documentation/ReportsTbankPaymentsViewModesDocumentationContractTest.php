<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-tbank-payments-view-modes-index совпадает с видом
 * Платежи / По дням / По месяцам на вкладке «Платежи T‑Bank».
 */
final class ReportsTbankPaymentsViewModesDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_tbank_payments_view_modes(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-tbank-payments-view-modes-index"', $html);
        $start = strpos($html, 'id="reports-tbank-payments-view-modes-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="ops-till-missing-payout-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/tbank-payments', $chunk);
        $this->assertStringContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringContainsString('#tbank-payments-table', $chunk);
        $this->assertStringContainsString('#tp-view-switch', $chunk);
        $this->assertStringContainsString('#tp-view-btn-payments', $chunk);
        $this->assertStringContainsString('#tp-view-btn-days', $chunk);
        $this->assertStringContainsString('#tp-view-btn-months', $chunk);
        $this->assertStringContainsString('view=payments|days|months', $chunk);
        $this->assertStringContainsString('tinkoff_payments.created_at', $chunk);
        $this->assertStringContainsString('/admin/reports/payments/monthly', $chunk);
        $this->assertStringContainsString('TbankPaymentsReportFilterRequest', $chunk);
        $this->assertStringContainsString("'view' => ['nullable', 'string', 'in:payments,days,months']", $chunk);
        $this->assertStringContainsString('data-error-for="view"', $chunk);
        $this->assertStringContainsString('tpHasActiveFilters', $chunk);
        $this->assertStringContainsString('CONFIRMED', $chunk);
        $this->assertStringContainsString('status=all', $chunk);
        $this->assertStringContainsString('tpStatusDefaulted', $chunk);
        $this->assertStringContainsString('tpApplyConfirmedDefault', $chunk);
        $this->assertStringContainsString('tpClearConfirmedDefaultIfNeeded', $chunk);
        $this->assertStringContainsString('statusAutoDefaulted', $chunk);
        $this->assertStringContainsString('destroyTbankPaymentsTable', $chunk);
        $this->assertStringContainsString('mountTbankPaymentsTable', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('kids-dt-scroll-x', $chunk);
        $this->assertStringContainsString('tpView', $chunk);
        $this->assertStringContainsString('reports_tbank_payments_by_day', $chunk);
        $this->assertStringContainsString('reports_tbank_payments_by_month', $chunk);
        $this->assertStringContainsString('tbank-payments-summary-column-toggle', $chunk);
        $this->assertStringContainsString('TbankPaymentsViewModesFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsViewModesDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/reports-admin#reports-tbank-payments', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-view-modes-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-summary-confirmed-default-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $chunk);
        $this->assertStringNotContainsString('/admin/reports/payments?', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $tbank = $this->docFile('tbank.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-tbank-payments-view-modes-index', $reports);
        $this->assertStringContainsString('TbankPaymentsViewModesFeatureTest', $reports);
        $this->assertStringContainsString('reports_tbank_payments_by_day', $reports);
        $this->assertStringContainsString('reports_tbank_payments_by_month', $reports);
        $this->assertStringContainsString('#tp-view-switch', $reports);

        $this->assertStringContainsString('/doc#reports-tbank-payments-view-modes-index', $tbank);
        $this->assertStringContainsString('TbankPaymentsViewModesFeatureTest', $tbank);

        $this->assertStringContainsString('вид Платежи/По дням/По месяцам', $controller);
    }

    public function test_live_code_matches_announced_view_modes_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Report/TbankPaymentReportController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/report/tbank_payments.blade.php');
        $tabs = (string) file_get_contents($root.'/resources/views/admin/report/index.blade.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Admin/Report/TbankPaymentsReportFilterRequest.php');
        $saveRequest = (string) file_get_contents($root.'/app/Http/Requests/Admin/Report/TbankPaymentsColumnsSettingsSaveRequest.php');

        $this->assertStringContainsString("public const TABLE_KEY_BY_DAY = 'reports_tbank_payments_by_day'", $controller);
        $this->assertStringContainsString("public const TABLE_KEY_BY_MONTH = 'reports_tbank_payments_by_month'", $controller);
        $this->assertStringContainsString('function summaryData', $controller);
        $this->assertStringContainsString('DATE(tinkoff_payments.created_at)', $controller);
        $this->assertStringContainsString("DATE_FORMAT(tinkoff_payments.created_at, \\'%Y-%m\\')", $controller);
        $this->assertStringContainsString("'tpView' => \$request->view()", $controller);
        $this->assertStringContainsString("'tpStatusDefaulted' => \$request->shouldDefaultConfirmedStatus()", $controller);

        $this->assertStringContainsString('id="tp-view-switch"', $blade);
        $this->assertStringContainsString('id="tp-view-btn-payments"', $blade);
        $this->assertStringContainsString('id="tp-view-btn-days"', $blade);
        $this->assertStringContainsString('id="tp-view-btn-months"', $blade);
        $this->assertStringContainsString('data-error-for="view"', $blade);
        $this->assertStringContainsString('function mountTbankPaymentsTable()', $blade);
        $this->assertStringContainsString('function destroyTbankPaymentsTable()', $blade);
        $this->assertStringContainsString('function tpApplyConfirmedDefault()', $blade);
        $this->assertStringContainsString('function tpClearConfirmedDefaultIfNeeded()', $blade);
        $this->assertStringContainsString('var statusAutoDefaulted', $blade);
        $this->assertStringContainsString('tbank-payments-summary-column-toggle', $blade);
        $this->assertStringContainsString('view: currentView', $blade);
        $this->assertStringContainsString('dtApi.reload();', $blade);
        $unwrapPos = strpos($blade, "if (\$table.parent().hasClass('kids-dt-scroll-x'))");
        $destroyCallPos = strpos($blade, 'dtApi.table.destroy()');
        $this->assertNotFalse($unwrapPos);
        $this->assertNotFalse($destroyCallPos);
        $this->assertLessThan($destroyCallPos, $unwrapPos);
        $this->assertStringContainsString("\$table.find('tbody').remove()", $blade);

        $this->assertStringContainsString("'tpView' => \$tpView ?? 'payments'", $tabs);
        $this->assertStringContainsString("'tpStatusDefaulted' => \$tpStatusDefaulted ?? false", $tabs);
        $this->assertStringContainsString("'tbankPaymentsPageLength' => \$tbankPaymentsPageLength ?? 10", $tabs);

        $this->assertStringContainsString("public const VIEWS = ['payments', 'days', 'months']", $request);
        $this->assertStringContainsString('function shouldDefaultConfirmedStatus()', $request);
        $this->assertStringContainsString("'view' => ['nullable', 'string', 'in:'.implode(',', self::VIEWS)]", $request);
        $this->assertStringContainsString("'view' => 'Вид'", $request);
        $this->assertStringContainsString("'view.in' => 'Поле «:attribute» содержит недопустимое значение.'", $request);

        $this->assertStringContainsString('class TbankPaymentsColumnsSettingsSaveRequest', $saveRequest);
        $this->assertStringContainsString("'view' => 'Вид'", $saveRequest);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
