<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-tbank-payments-payout-status-index совпадает с колонкой
 * «Статус выплаты» на вкладке «Платежи T‑Bank».
 */
final class ReportsTbankPaymentsPayoutStatusDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_payout_status_column(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-tbank-payments-payout-status-index"', $html);
        $start = strpos($html, 'id="reports-tbank-payments-payout-status-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="groups-own-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/tbank-payments', $chunk);
        $this->assertStringContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringContainsString('#tbank-payments-table', $chunk);
        $this->assertStringContainsString('Статус выплаты', $chunk);
        $this->assertStringContainsString('payout_status', $chunk);
        $this->assertStringContainsString('payout_status_at', $chunk);
        $this->assertStringContainsString('payout_status_at_raw', $chunk);
        $this->assertStringContainsString('latestPayoutSubquery', $chunk);
        $this->assertStringContainsString('ORDER BY id DESC LIMIT 1', $chunk);
        $this->assertStringContainsString('REJECTED', $chunk);
        $this->assertStringContainsString('COMPLETED', $chunk);
        $this->assertStringContainsString('INITIATED', $chunk);
        $this->assertStringContainsString('CREDIT_CHECKING', $chunk);
        $this->assertStringContainsString('completed_at', $chunk);
        $this->assertStringContainsString('updated_at', $chunk);
        $this->assertStringContainsString('d.m.Y H:i', $chunk);
        $this->assertStringContainsString('renderPayoutStatusCell', $chunk);
        $this->assertStringContainsString('#tpColPayoutStatus', $chunk);
        $this->assertStringContainsString("type: 'custom'", $chunk);
        $this->assertStringContainsString('searchable: false', $chunk);
        $this->assertStringContainsString('view=days', $chunk);
        $this->assertStringContainsString('view=months', $chunk);
        $this->assertStringContainsString('GET …/total', $chunk);
        $this->assertStringContainsString('total_formatted', $chunk);
        $this->assertStringContainsString('total_raw', $chunk);
        $this->assertStringContainsString('tbank.payouts.manage', $chunk);
        $this->assertStringContainsString('tbankPaymentsTheadHtml', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('without_payout=1', $chunk);
        $this->assertStringContainsString('403', $chunk);
        $this->assertStringContainsString('302', $chunk);
        $this->assertStringContainsString('401', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-payout-status-index', $chunk);
        $this->assertStringContainsString('/docs/documentation/reports-admin#reports-tbank-payments', $chunk);
        $this->assertStringContainsString('tbank#payment-show-card', $chunk);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsReportTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsViewModesFeatureTest', $chunk);
        $this->assertStringContainsString('test_tbank_payments_report_inline_script_keeps_defaults_and_reloads_without_recreate', $chunk);
        $this->assertStringContainsString('test_tbank_payments_payout_status_cell_and_view_switch_thead_contract', $chunk);
        $this->assertStringContainsString('test_tbank_payments_page_uses_preset_types_without_custom', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsPayoutStatusDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('tbank_commissions_index', $chunk);
        $this->assertStringNotContainsString('reports.additional.value.view', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $tbank = $this->docFile('tbank.html');
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-tbank-payments-payout-status-index', $reports);
        $this->assertStringContainsString('payout_status', $reports);
        $this->assertStringContainsString('payout_status_at', $reports);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusFeatureTest', $reports);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusFullAccessFeatureTest', $reports);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusNonAjaxSafetyNetFeatureTest', $reports);
        $this->assertStringContainsString('ReportsTbankPaymentsPayoutStatusDocumentationContractTest', $reports);

        $this->assertStringContainsString('/doc#reports-tbank-payments-payout-status-index', $tbank);
        $this->assertStringContainsString('Статус выплаты', $tbank);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusFeatureTest', $tbank);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusFullAccessFeatureTest', $tbank);
        $this->assertStringContainsString('TbankPaymentsPayoutStatusNonAjaxSafetyNetFeatureTest', $tbank);

        $this->assertStringContainsString('колонка «Статус выплаты»', $index);
        $this->assertStringContainsString('/doc#reports-tbank-payments-payout-status-index', $index);

        $this->assertStringContainsString('колонка «Статус выплаты»', $controller);
    }

    public function test_live_code_matches_announced_payout_status_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Report/TbankPaymentReportController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/report/tbank_payments.blade.php');

        $this->assertStringContainsString('function latestPayoutSubquery', $controller);
        $this->assertStringContainsString("CASE WHEN tinkoff_payouts.status = 'COMPLETED' AND tinkoff_payouts.completed_at IS NOT NULL", $controller);
        $this->assertStringContainsString('payout_status_at_raw', $controller);
        $this->assertStringContainsString("editColumn('payout_status'", $controller);
        $this->assertStringContainsString("addColumn('payout_status_at'", $controller);
        $this->assertStringContainsString('function formatPayoutStatusAt', $controller);
        $this->assertStringContainsString("format('d.m.Y H:i')", $controller);
        $this->assertStringContainsString("removeColumn('payout_status_at_raw')", $controller);
        $this->assertStringContainsString("orderByDesc('tinkoff_payouts.id')", $controller);
        $this->assertStringNotContainsString('reports.additional.value.view', $controller);

        $this->assertStringContainsString('function tbankPaymentsTheadHtml', $blade);
        $this->assertStringContainsString('<th>Выплата</th><th>Статус выплаты</th><th>Способ</th>', $blade);
        $this->assertStringContainsString('tbankPaymentsTheadHtml(currentView)', $blade);
        $this->assertStringContainsString('function renderPayoutStatusCell', $blade);
        $this->assertStringContainsString("key: 'payout_status'", $blade);
        $this->assertStringContainsString('data-column-key="payout_status"', $blade);
        $this->assertStringContainsString('payout_status: true', $blade);
        $this->assertStringContainsString('id="tpColPayoutStatus"', $blade);
        $this->assertStringContainsString('<th>Статус выплаты</th>', $blade);
        $this->assertStringContainsString("type: 'badge'", $blade);
        $this->assertStringContainsString('searchable: false', $blade);
        $this->assertStringContainsString('row.payout_status_at', $blade);
        $this->assertStringContainsString('CREDIT_CHECKING', $blade);
        $this->assertStringContainsString('dt-cell-empty', $blade);
        $this->assertStringNotContainsString("type: 'custom'", $blade);
        $this->assertStringNotContainsString('robokassa', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
