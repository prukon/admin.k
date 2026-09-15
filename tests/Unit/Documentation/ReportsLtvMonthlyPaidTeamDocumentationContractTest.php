<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-ltv-monthly-paid-team-index совпадает с колонкой/фильтром
 * оплаченной группы в LTV и «Платежи по месяцам».
 */
final class ReportsLtvMonthlyPaidTeamDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ltv_monthly_paid_team_column(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-ltv-monthly-paid-team-index"', $html);
        $start = strpos($html, 'id="reports-ltv-monthly-paid-team-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="password-reset-unique-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/payments/monthly', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv', $chunk);
        $this->assertStringContainsString('payments.team_id', $chunk);
        $this->assertStringContainsString('reports.view', $chunk);
        $this->assertStringContainsString('sqlPaymentLedgerTeamTitleExpr', $chunk);
        $this->assertStringContainsString('sqlPaymentLedgerTeamTitlesAggregate', $chunk);
        $this->assertStringContainsString('applyPaymentLedgerTeamFilters', $chunk);
        $this->assertStringContainsString('сводка месяцев колонки не имеет', $chunk);
        $this->assertStringContainsString('Во вложенной HTML-таблице LTV колонки «Группа» нет', $chunk);
        $this->assertStringContainsString('ltvReportFilterParams', $chunk);
        $this->assertStringContainsString('paymentsMonthlyFilterParams', $chunk);
        $this->assertStringContainsString('users_prices.team_id', $chunk);
        $this->assertStringContainsString('Data/детализация без AJAX', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-monthly-paid-team-index', $html);
        $this->assertStringContainsString('LTV / «Платежи по месяцам»', $html);

        $this->assertStringContainsString('PaymentMonthlyReportTeamPivotFeatureTest', $chunk);
        $this->assertStringContainsString('LtvReportTeamPivotFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvMonthlyPaidTeamFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvMonthlyPaidTeamMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvMonthlyPaidTeamNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('test_monthly_and_ltv_nested_ajax_copy_paid_team_filter_and_reset_clears_select2', $chunk);
        $this->assertStringContainsString('ReportsLtvMonthlyPaidTeamDocumentationContractTest', $chunk);
        $this->assertStringContainsString('ltvReportFilterParams', $chunk);
        $this->assertStringNotContainsString('applyReportTeamFilters', $chunk);
    }

    public function test_related_docs_and_controllers_use_payment_ledger_team_filters(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $payments = $this->docFile('payments.html');
        $membership = $this->docFile('student-team-membership.html');
        $reportPayments = $this->docFile('reports-payments.html');

        $this->assertStringContainsString('applyPaymentLedgerTeamFilters', $reports);
        $this->assertStringContainsString('sqlPaymentLedgerTeamTitleExpr', $reports);
        $this->assertStringContainsString('sqlPaymentLedgerTeamTitlesAggregate', $reports);
        $this->assertStringContainsString('/doc#reports-ltv-monthly-paid-team-index', $reports);
        $this->assertStringContainsString('вложенная HTML-таблица колонки «Группа» не показывает', $reports);

        $this->assertStringContainsString('sqlPaymentLedgerTeamTitleExpr', $payments);
        $this->assertStringContainsString('applyPaymentLedgerTeamFilters', $payments);
        $this->assertStringNotContainsString('по-прежнему фильтруют по членству ученика в pivot', $payments);

        $this->assertStringContainsString('applyPaymentLedgerTeamFilters', $membership);
        $this->assertStringContainsString('PaymentMonthlyReportTeamPivotFeatureTest', $membership);

        $this->assertStringContainsString('те же правила у LTV и «Платежи по месяцам»', $reportPayments);
        $this->assertStringContainsString('/doc#reports-ltv-monthly-paid-team-index', $reportPayments);

        $this->assertStringContainsString('/doc#reports-ltv-monthly-paid-team-index', $membership);

        $monthly = $this->appFile('Http/Controllers/Admin/Report/PaymentMonthlyReportController.php');
        $ltv = $this->appFile('Http/Controllers/Admin/Report/LtvReportController.php');
        $helper = $this->appFile('Support/UserTeamQuery.php');

        $this->assertStringContainsString('sqlPaymentLedgerTeamTitleExpr', $monthly);
        $this->assertStringContainsString('applyPaymentLedgerTeamFilters', $monthly);
        $this->assertStringNotContainsString('sqlStudentTeamTitlesSubquery', $monthly);
        $this->assertStringNotContainsString('applyReportTeamFilters', $monthly);

        $this->assertStringContainsString('sqlPaymentLedgerTeamTitlesAggregate', $ltv);
        $this->assertStringContainsString('sqlPaymentLedgerTeamTitleExpr', $ltv);
        $this->assertStringContainsString('applyPaymentLedgerTeamFilters', $ltv);
        $this->assertStringNotContainsString('applyReportTeamFilters', $ltv);
        $this->assertStringNotContainsString('без фильтра по ученику/группе', $ltv);
        $this->assertStringContainsString('ltvReportFilterParams', $ltv);

        $this->assertStringContainsString('sqlPaymentLedgerTeamTitleExpr', $helper);
        $this->assertStringContainsString('sqlPaymentLedgerTeamTitlesAggregate', $helper);
        $this->assertStringContainsString('sqlPaymentPaidTeamTitleExpr', $helper);
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
}
