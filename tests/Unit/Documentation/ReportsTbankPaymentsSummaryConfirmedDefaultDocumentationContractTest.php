<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-tbank-payments-summary-confirmed-default-index совпадает
 * с дефолтом CONFIRMED на виде По дням / По месяцам отчёта «Платежи T‑Bank».
 */
final class ReportsTbankPaymentsSummaryConfirmedDefaultDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_summary_confirmed_default(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-tbank-payments-summary-confirmed-default-index"', $html);
        $start = strpos($html, 'id="reports-tbank-payments-summary-confirmed-default-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="tbank-commissions-history-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/tbank-payments?view=days', $chunk);
        $this->assertStringContainsString('?view=months', $chunk);
        $this->assertStringContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringContainsString('#tbank-payments-table', $chunk);
        $this->assertStringContainsString('#tp-filter-status', $chunk);
        $this->assertStringContainsString('#tbankPaymentsFiltersCollapse', $chunk);
        $this->assertStringContainsString('CONFIRMED', $chunk);
        $this->assertStringContainsString('tpHasActiveFilters', $chunk);
        $this->assertStringContainsString('tpStatusDefaulted', $chunk);
        $this->assertStringContainsString('view=payments', $chunk);
        $this->assertStringContainsString('Все статусы', $chunk);
        $this->assertStringContainsString('till.overdue_payouts', $chunk);
        $this->assertStringContainsString('Не было выплаты', $chunk);
        $this->assertStringContainsString('shouldDefaultConfirmedStatus', $chunk);
        $this->assertStringContainsString("exists('status')", $chunk);
        $this->assertStringContainsString('status=all', $chunk);
        $this->assertStringContainsString('NEW / FORM / CONFIRMED / REJECTED / CANCELED', $chunk);
        $this->assertStringContainsString('tpApplyConfirmedDefault', $chunk);
        $this->assertStringContainsString('tpClearConfirmedDefaultIfNeeded', $chunk);
        $this->assertStringContainsString('statusAutoDefaulted', $chunk);
        $this->assertStringContainsString('GET …/data', $chunk);
        $this->assertStringContainsString('GET …/total', $chunk);
        $this->assertStringContainsString('TbankPaymentsReportFilterRequest', $chunk);
        $this->assertStringContainsString('TbankPaymentsViewModesFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsSummaryConfirmedDefaultDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/reports-admin#reports-tbank-payments', $chunk);
        $this->assertStringContainsString('tbank#payment-show-card', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-summary-confirmed-default-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-view-modes-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $chunk);

        $this->assertStringNotContainsString('tbank_commissions_index', $chunk);
        $this->assertStringNotContainsString('/admin/reports/payments?', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $tbank = $this->docFile('tbank.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-tbank-payments-summary-confirmed-default-index', $reports);
        $this->assertStringContainsString('shouldDefaultConfirmedStatus', $reports);
        $this->assertStringContainsString('tpApplyConfirmedDefault', $reports);
        $this->assertStringContainsString('TbankPaymentsViewModesFeatureTest', $reports);
        $this->assertStringContainsString('ReportsTbankPaymentsSummaryConfirmedDefaultDocumentationContractTest', $reports);

        $this->assertStringContainsString('/doc#reports-tbank-payments-summary-confirmed-default-index', $tbank);
        $this->assertStringContainsString('TbankPaymentsViewModesFeatureTest', $tbank);
        $this->assertStringContainsString('ReportsTbankPaymentsSummaryConfirmedDefaultDocumentationContractTest', $tbank);

        $this->assertStringContainsString('дефолт CONFIRMED на По дням/По месяцам', $controller);
    }

    public function test_live_code_matches_announced_confirmed_default_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $request = (string) file_get_contents($root.'/app/Http/Requests/Admin/Report/TbankPaymentsReportFilterRequest.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Report/TbankPaymentReportController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/report/tbank_payments.blade.php');
        $tabs = (string) file_get_contents($root.'/resources/views/admin/report/index.blade.php');

        $this->assertStringContainsString('function shouldDefaultConfirmedStatus()', $request);
        $this->assertStringContainsString('$this->statusPresentInRequest = $this->exists(\'status\')', $request);
        $this->assertStringContainsString("\$status = 'CONFIRMED'", $request);
        $this->assertStringContainsString("\$view !== 'days' && \$view !== 'months'", $request);

        $this->assertStringContainsString("'tpStatusDefaulted' => \$request->shouldDefaultConfirmedStatus()", $controller);
        $this->assertStringContainsString("'tpView' => \$request->view()", $controller);

        $this->assertStringContainsString('function tpApplyConfirmedDefault()', $blade);
        $this->assertStringContainsString('function tpClearConfirmedDefaultIfNeeded()', $blade);
        $this->assertStringContainsString('var statusAutoDefaulted', $blade);
        $this->assertStringContainsString("tpStatusSelect().val('CONFIRMED')", $blade);
        $this->assertStringContainsString('id="tp-filter-status"', $blade);
        $this->assertStringContainsString('id="tbankPaymentsFiltersCollapse"', $blade);

        $this->assertStringContainsString("'tpStatusDefaulted' => \$tpStatusDefaulted ?? false", $tabs);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
