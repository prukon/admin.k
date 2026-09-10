<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-tbank-payments-without-payout-index совпадает с фильтром
 * «Не было выплаты» на вкладке «Платежи T‑Bank».
 */
final class ReportsTbankPaymentsWithoutPayoutDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_without_payout_filter(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-tbank-payments-without-payout-index"', $html);
        $start = strpos($html, 'id="reports-tbank-payments-without-payout-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-package-edit-fill-defaults-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/tbank-payments', $chunk);
        $this->assertStringContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringContainsString('#tbank-payments-table', $chunk);
        $this->assertStringContainsString('#tp-filter-without-payout', $chunk);
        $this->assertStringContainsString('without_payout=1', $chunk);
        $this->assertStringContainsString('Не было выплаты', $chunk);
        $this->assertStringContainsString('Выплата', $chunk);
        $this->assertStringContainsString('/admin/tinkoff/payouts', $chunk);
        $this->assertStringContainsString('tbank.payouts.manage', $chunk);
        $this->assertStringContainsString('manage.payment.method.tbank', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $chunk);

        $this->assertStringContainsString('whereNotExists', $chunk);
        $this->assertStringContainsString('applyReportFilters', $chunk);
        $this->assertStringContainsString('COALESCE(net_amount, amount)', $chunk);
        $this->assertStringContainsString('REJECTED', $chunk);
        $this->assertStringContainsString('COMPLETED', $chunk);
        $this->assertStringContainsString('INITIATED', $chunk);
        $this->assertStringContainsString('CREDIT_CHECKING', $chunk);
        $this->assertStringContainsString('NEW / FORM / CONFIRMED', $chunk);
        $this->assertStringContainsString('REJECTED платежа ≠ REJECTED выплаты', $chunk);

        $this->assertStringContainsString("value=\"1\"", $chunk);
        $this->assertStringContainsString('tpFilterParams()', $chunk);
        $this->assertStringContainsString('without_payout=all', $chunk);
        $this->assertStringContainsString('data-error-for="without_payout"', $chunk);
        $this->assertStringContainsString('TbankPaymentsReportFilterRequest', $chunk);
        $this->assertStringContainsString("'without_payout' => ['nullable', 'boolean']", $chunk);
        $this->assertStringContainsString('422', $chunk);
        $this->assertStringContainsString('302', $chunk);

        $this->assertStringContainsString('GET …/data', $chunk);
        $this->assertStringContainsString('GET …/total', $chunk);
        $this->assertStringContainsString('total_formatted', $chunk);
        $this->assertStringContainsString('total_raw', $chunk);
        $this->assertStringContainsString('tinkoff_payments.amount', $chunk);
        $this->assertStringContainsString('tpHasActiveFilters', $chunk);
        $this->assertStringContainsString('method=sbp', $chunk);
        $this->assertStringContainsString('e.preventDefault()', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);

        $this->assertStringContainsString('/docs/documentation/reports-admin#reports-tbank-payments', $chunk);
        $this->assertStringContainsString('tbank#payment-show-card', $chunk);
        $this->assertStringContainsString('TbankPaymentsWithoutPayoutFilterFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsReportTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsPageFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('test_tbank_payments_report_inline_script_keeps_defaults_and_reloads_without_recreate', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsWithoutPayoutDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('tbank_commissions_index', $chunk);
        $this->assertStringNotContainsString('reports.additional.value.view', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $tbank = $this->docFile('tbank.html');
        $payouts = $this->docFile('tbank-admin-payouts.html');
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $reports);
        $this->assertStringContainsString('ReportsTbankPaymentsWithoutPayoutDocumentationContractTest', $reports);
        $this->assertStringContainsString('without_payout', $reports);
        $this->assertStringContainsString('Не было выплаты', $reports);
        $this->assertStringContainsString('tp-filter-without-payout', $reports);
        $this->assertStringContainsString('CREDIT_CHECKING', $reports);
        $this->assertStringContainsString("value=\"1\"", $reports);

        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $tbank);
        $this->assertStringContainsString('without_payout=1', $tbank);
        $this->assertStringContainsString('Не было выплаты', $tbank);
        $this->assertStringContainsString('ReportsTbankPaymentsWithoutPayoutDocumentationContractTest', $tbank);

        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $payouts);
        $this->assertStringContainsString('tbank.payouts.manage', $payouts);

        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $index);
        $this->assertStringContainsString('фильтр «Не было выплаты»', $index);

        $this->assertStringContainsString('фильтр «Не было выплаты»', $controller);
        $this->assertStringContainsString('Чек / комиссия платформы', $controller);
    }

    public function test_live_code_matches_announced_without_payout_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Report/TbankPaymentReportController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/report/tbank_payments.blade.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Admin/Report/TbankPaymentsReportFilterRequest.php');

        $this->assertStringContainsString("\$filters['without_payout']", $controller);
        $this->assertStringContainsString('whereNotExists', $controller);
        $this->assertStringContainsString("where('tinkoff_payouts.status', '<>', 'REJECTED')", $controller);
        $this->assertStringContainsString("whereColumn('tinkoff_payouts.payment_id', 'tinkoff_payments.id')", $controller);
        $this->assertStringContainsString("if (! empty(\$filters['without_payout']))", $controller);
        $this->assertStringContainsString("'total_formatted'", $controller);
        $this->assertStringContainsString("'total_raw'", $controller);
        $this->assertStringContainsString("sum('amount')", $controller);
        $this->assertStringContainsString("COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount)", $controller);
        $this->assertStringNotContainsString('tbank.payouts.manage', $controller);

        $this->assertStringContainsString('id="tp-filter-without-payout"', $blade);
        $this->assertStringContainsString('name="without_payout"', $blade);
        $this->assertStringContainsString('value="1"', $blade);
        $this->assertStringContainsString('Не было выплаты', $blade);
        $this->assertStringContainsString('data-error-for="without_payout"', $blade);
        $this->assertStringContainsString("without_payout: \$form.find('[name=\"without_payout\"]').is(':checked') ? '1' : ''", $blade);
        $this->assertStringContainsString('e.preventDefault()', $blade);
        $this->assertStringContainsString('dtApi.reload();', $blade);
        $this->assertStringNotContainsString('tbank.payouts.manage', $blade);
        $this->assertStringNotContainsString('@can(\'tbank.payouts.manage\')', $blade);

        $this->assertStringContainsString("'without_payout' => ['nullable', 'boolean']", $request);
        $this->assertStringContainsString("\$withoutPayout === 'all'", $request);
        $this->assertStringContainsString("'without_payout' => 'Не было выплаты'", $request);
        $this->assertStringContainsString("'without_payout.boolean'", $request);
        $this->assertStringContainsString("\$this->boolean('without_payout')", $request);
        $this->assertStringContainsString("public const STATUSES = ['NEW', 'FORM', 'CONFIRMED', 'REJECTED', 'CANCELED']", $request);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
