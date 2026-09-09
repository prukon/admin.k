<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-tbank-payments-receipt-commission-index совпадает с колонками
 * «Чек» и «Комиссия платформы» на вкладке «Платежи T‑Bank».
 */
final class ReportsTbankPaymentsReceiptCommissionDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_receipt_and_platform_commission_columns(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-tbank-payments-receipt-commission-index"', $html);
        $start = strpos($html, 'id="reports-tbank-payments-receipt-commission-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-template-email-default-link-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/tbank-payments', $chunk);
        $this->assertStringContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringContainsString('#tbank-payments-table', $chunk);
        $this->assertStringContainsString('reports.additional.value.view', $chunk);
        $this->assertStringContainsString('Robokassa', $chunk);
        $this->assertStringContainsString('source=marketplace', $chunk);
        $this->assertStringContainsString('/admin/settings/tbank-commissions', $chunk);
        $this->assertStringContainsString('tinkoff_payouts.platform_fee', $chunk);
        $this->assertStringContainsString('/doc#admin-partners-list-metrics', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $chunk);

        $this->assertStringContainsString('receipt', $chunk);
        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $chunk);
        $this->assertStringContainsString('payment_number', $chunk);
        $this->assertStringContainsString('tinkoff_payment_id', $chunk);
        $this->assertStringContainsString('income_return', $chunk);
        $this->assertStringContainsString('https://receipts.ru/', $chunk);
        $this->assertStringContainsString('renderTbankReceiptCell', $chunk);
        $this->assertStringContainsString('KidsCrmDataTable.renderIcon', $chunk);
        $this->assertStringContainsString('Чек не сформирован', $chunk);
        $this->assertStringContainsString('Чек формируется (CloudKassir)', $chunk);

        $this->assertStringContainsString('platform_commission', $chunk);
        $this->assertStringContainsString('tinkoff_commission_rules', $chunk);
        $this->assertStringContainsString('tinkoff_payments.amount', $chunk);
        $this->assertStringContainsString('FORM', $chunk);
        $this->assertStringContainsString('feeCents', $chunk);
        $this->assertStringContainsString('platform_percent', $chunk);
        $this->assertStringContainsString('platform_min_fixed', $chunk);
        $this->assertStringContainsString('0.00', $chunk);
        $this->assertStringContainsString('pickForPartner', $chunk);
        $this->assertStringContainsString('GET …/total', $chunk);
        $this->assertStringContainsString('total_formatted', $chunk);
        $this->assertStringContainsString('total_raw', $chunk);

        $this->assertStringContainsString('orderable: false', $chunk);
        $this->assertStringContainsString('searchable: false', $chunk);
        $this->assertStringContainsString('Order / Deal / id', $chunk);
        $this->assertStringContainsString('/docs/documentation/reports-admin#reports-tbank-payments', $chunk);
        $this->assertStringContainsString('reports-payments#receipt-column', $chunk);
        $this->assertStringContainsString('tbank#payment-show-card', $chunk);

        $this->assertStringContainsString('TbankPaymentsReceiptAndCommissionFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsReportTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsPageFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('test_tbank_payments_report_inline_script_keeps_defaults_and_reloads_without_recreate', $chunk);
        $this->assertStringContainsString('KidsCrmDataTableColumnsMigrationIter1Iter2Test', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsReceiptCommissionDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('tbank_commissions_index', $chunk);
        $this->assertStringNotContainsString('reports.payments.commission_total.view', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $payments = $this->docFile('reports-payments.html');
        $tbank = $this->docFile('tbank.html');
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $reports);
        $this->assertStringContainsString('ReportsTbankPaymentsReceiptCommissionDocumentationContractTest', $reports);
        $this->assertStringContainsString('platform_commission', $reports);
        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $reports);
        $this->assertStringContainsString('reports.additional.value.view', $reports);
        $this->assertStringContainsString('0.00', $reports);

        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $payments);
        $this->assertStringContainsString('renderTbankReceiptCell', $payments);
        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $payments);
        $this->assertStringContainsString('reports.tbank.payments.view', $payments);
        $this->assertStringContainsString('ReportsTbankPaymentsReceiptCommissionDocumentationContractTest', $payments);

        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $tbank);
        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $tbank);
        $this->assertStringContainsString('ReportsTbankPaymentsReceiptCommissionDocumentationContractTest', $tbank);

        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $index);
        $this->assertStringContainsString('«Чек» / «Комиссия платформы»', $index);

        $this->assertStringContainsString('Чек / комиссия платформы', $controller);
    }

    public function test_live_code_matches_announced_receipt_and_commission_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Report/TbankPaymentReportController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/report/tbank_payments.blade.php');
        $resolver = (string) file_get_contents($root.'/app/Services/Tinkoff/TinkoffPaymentFiscalReceiptResolver.php');
        $url = (string) file_get_contents($root.'/app/Support/FiscalReceipts/FiscalReceiptUrl.php');
        $pick = (string) file_get_contents($root.'/app/Models/TinkoffCommissionRule.php');

        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $controller);
        $this->assertStringContainsString('function platformCommissionRub', $controller);
        $this->assertStringContainsString("'platform_percent' => 0.00", $controller);
        $this->assertStringContainsString("'platform_min_fixed' => 0.00", $controller);
        $this->assertStringContainsString("where('is_enabled', true)", $controller);
        $this->assertStringContainsString("orderByRaw('partner_id is null, method is null')", $controller);
        $this->assertStringContainsString("addColumn('platform_commission'", $controller);
        $this->assertStringContainsString("addColumn('has_receipt'", $controller);
        $this->assertStringContainsString("'total_formatted'", $controller);
        $this->assertStringContainsString("'total_raw'", $controller);
        $this->assertStringContainsString("sum('amount')", $controller);
        $this->assertStringContainsString("tinkoff_payments.order_id", $controller);
        $this->assertStringContainsString("tinkoff_payments.deal_id", $controller);
        $this->assertStringNotContainsString('reports.additional.value.view', $controller);
        $this->assertStringNotContainsString('pickForPartner', $controller);
        $this->assertStringNotContainsString('platform_fee', $controller);

        $this->assertStringContainsString('function renderTbankReceiptCell', $blade);
        $this->assertStringContainsString('KidsCrmDataTable.renderIcon', $blade);
        $this->assertStringContainsString("key: 'platform_commission'", $blade);
        $this->assertStringContainsString("key: 'receipt'", $blade);
        $this->assertStringContainsString('platform_commission: true', $blade);
        $this->assertStringContainsString('receipt: true', $blade);
        $this->assertStringContainsString('orderable: false', $blade);
        $this->assertStringContainsString('searchable: false', $blade);
        $this->assertStringContainsString('Чек не сформирован', $blade);
        $this->assertStringNotContainsString('robokassa', $blade);

        $this->assertStringContainsString("where('payment_number', (string) \$payment->tinkoff_payment_id)", $resolver);
        $this->assertStringContainsString('TYPE_INCOME_RETURN', $resolver);
        $this->assertStringContainsString('Чек формируется (CloudKassir)', $resolver);

        $this->assertStringContainsString("https://receipts.ru/", $url);

        $this->assertStringContainsString('function pickForPartner', $pick);
        $this->assertStringContainsString("'platform_percent' => 2.00", $pick);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
