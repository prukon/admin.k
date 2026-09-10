<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-tbank-payments-index совпадает с вкладкой отчётов «Платежи T‑Bank».
 */
final class ReportsTbankPaymentsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_tbank_payments_report(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-tbank-payments-index"', $html);
        $start = strpos($html, 'id="reports-tbank-payments-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-partners-list-totals-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/tbank-payments', $chunk);
        $this->assertStringContainsString('reports.tbank.payments.view', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('role_base_permissions', $chunk);
        $this->assertStringContainsString('2026_09_07_073200_add_reports_tbank_payments_view_permission.php', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('reports_tbank_payments', $chunk);
        $this->assertStringContainsString('#tbank-payments-table', $chunk);
        $this->assertStringContainsString('persistPageLength', $chunk);
        $this->assertStringContainsString('Выплата', $chunk);
        $this->assertStringContainsString('REJECTED', $chunk);
        $this->assertStringContainsString('status=all', $chunk);
        $this->assertStringContainsString('method=all', $chunk);
        $this->assertStringContainsString('without_payout', $chunk);
        $this->assertStringContainsString('Не было выплаты', $chunk);
        $this->assertStringContainsString('tp-filter-without-payout', $chunk);
        $this->assertStringContainsString('CREDIT_CHECKING', $chunk);
        $this->assertStringContainsString("value=\"1\"", $chunk);
        $this->assertStringContainsString('TbankPaymentsWithoutPayoutFilterFeatureTest', $chunk);
        $this->assertStringContainsString('Способ', $chunk);
        $this->assertStringContainsString('Чек', $chunk);
        $this->assertStringContainsString('Комиссия платформы', $chunk);
        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $chunk);
        $this->assertStringContainsString('tinkoff_commission_rules', $chunk);
        $this->assertStringContainsString('platform_percent', $chunk);
        $this->assertStringContainsString('TbankPaymentsReceiptAndCommissionFeatureTest', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('tp-toolbar-commissions', $chunk);
        $this->assertStringContainsString('settings.commission', $chunk);
        $this->assertStringContainsString('/admin/settings/tbank-commissions', $chunk);
        $this->assertStringContainsString('/admin/tinkoff/payments/{id}', $chunk);
        $this->assertStringContainsString('manage.payment.method.tbank', $chunk);
        $this->assertStringContainsString('whereNumber', $chunk);
        $this->assertStringContainsString('TbankPaymentsReportTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsWithoutPayoutFilterFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsPageFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TbankPaymentsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/reports-admin#reports-tbank-payments', $chunk);
        $this->assertStringContainsString('tbank#payment-show-card', $chunk);
        $this->assertStringContainsString('/doc#tbank-commissions-columns-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $chunk);
        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $chunk);
        $this->assertStringContainsString('ReportsTbankPaymentsWithoutPayoutDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('tbank_commissions_index', $chunk);
        $this->assertStringNotContainsString('редирект на /admin/reports/tbank-payments', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $tbank = $this->docFile('tbank.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $partners = $this->docFile('partners-permissions.html');
        $ui = $this->docFile('reusable-ui-partials.html');

        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $reports);
        $this->assertStringContainsString('reports.tbank.payments.view', $reports);
        $this->assertStringContainsString('ReportsTbankPaymentsDocumentationContractTest', $reports);
        $this->assertStringContainsString('TbankPaymentsReceiptAndCommissionFeatureTest', $reports);
        $this->assertStringContainsString('TbankPaymentsWithoutPayoutFilterFeatureTest', $reports);
        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $reports);
        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $reports);
        $this->assertStringContainsString('ReportsTbankPaymentsWithoutPayoutDocumentationContractTest', $reports);
        $this->assertStringContainsString('payout_amount', $reports);
        $this->assertStringContainsString('Выплата', $reports);
        $this->assertStringContainsString('without_payout', $reports);
        $this->assertStringContainsString('Не было выплаты', $reports);
        $this->assertStringContainsString('method_label', $reports);
        $this->assertStringContainsString('Способ', $reports);
        $this->assertStringContainsString('Чек', $reports);
        $this->assertStringContainsString('Комиссия платформы', $reports);
        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $reports);
        $this->assertStringContainsString('platform_commission', $reports);

        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $tbank);
        $this->assertStringContainsString('reports.tbank.payments.view', $tbank);
        $this->assertStringContainsString('tp-toolbar-commissions', $tbank);
        $this->assertStringContainsString('Чек', $tbank);
        $this->assertStringContainsString('Комиссия платформы', $tbank);
        $this->assertStringContainsString('without_payout', $tbank);
        $this->assertStringContainsString('Не было выплаты', $tbank);
        $this->assertStringContainsString('TbankPaymentsReceiptAndCommissionFeatureTest', $tbank);
        $this->assertStringContainsString('/doc#reports-tbank-payments-receipt-commission-index', $tbank);
        $this->assertStringContainsString('/doc#reports-tbank-payments-without-payout-index', $tbank);

        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $groups);
        $this->assertStringContainsString('2026_09_07_073200_add_reports_tbank_payments_view_permission.php', $groups);

        $this->assertStringContainsString('reports.tbank.payments.view', $partners);
        $this->assertStringContainsString('/doc#reports-tbank-payments-index', $partners);

        $this->assertStringContainsString('reports_tbank_payments', $ui);
        $this->assertStringContainsString('/admin/reports/tbank-payments', $ui);
    }

    public function test_live_code_matches_announced_report_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root.'/routes/web.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Report/TbankPaymentReportController.php');
        $show = (string) file_get_contents($root.'/app/Http/Controllers/TinkoffAdminPaymentController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/report/tbank_payments.blade.php');
        $tabs = (string) file_get_contents($root.'/resources/views/admin/report/index.blade.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Admin/Report/TbankPaymentsReportFilterRequest.php');
        $model = (string) file_get_contents($root.'/app/Models/TinkoffPayment.php');
        $migration = (string) file_get_contents($root.'/database/migrations/2026_09_07_073200_add_reports_tbank_payments_view_permission.php');
        $seeder = (string) file_get_contents($root.'/database/seeders/PermissionSeeder.php');
        $hints = (string) file_get_contents($root.'/config/permission_capability_hints.php');

        $this->assertStringContainsString("Route::middleware(['can:reports.tbank.payments.view'])", $routes);
        $this->assertStringContainsString("->name('reports.tbank-payments.index')", $routes);
        $this->assertStringContainsString("->name('reports.tbank-payments.total')", $routes);
        $this->assertStringContainsString("->name('reports.tbank-payments.data')", $routes);
        $this->assertStringContainsString("admin/reports/tbank-payments/columns-settings", $routes);
        $this->assertStringContainsString("Route::get('/admin/tinkoff/payments/{id}'", $routes);
        $this->assertStringContainsString("->whereNumber('id')", $routes);
        $this->assertStringContainsString("->name('admin.tinkoff.payments.show')", $routes);
        $this->assertSame(0, preg_match("/Route::get\\('\/admin\/tinkoff\/payments'\\s*,/", $routes));

        $this->assertStringContainsString("public const TABLE_KEY = 'reports_tbank_payments'", $controller);
        $this->assertStringContainsString('$request->filters()', $controller);
        $this->assertStringContainsString("tbankPaymentsPageLength", $controller);
        $this->assertStringContainsString("\$filters['without_payout']", $controller);
        $this->assertStringContainsString("where('tinkoff_payouts.status', '<>', 'REJECTED')", $controller);
        $this->assertStringContainsString("addColumn('method_label'", $controller);
        $this->assertStringContainsString("TinkoffPayment::methodLabel", $controller);
        $this->assertStringContainsString("addColumn('platform_commission'", $controller);
        $this->assertStringContainsString('platformCommissionRub', $controller);
        $this->assertStringContainsString('TinkoffPaymentFiscalReceiptResolver', $controller);
        $this->assertStringContainsString("addColumn('has_receipt'", $controller);
        $this->assertStringNotContainsString('reports.additional.value.view', $controller);

        $this->assertStringContainsString("Gate::allows('reports.tbank.payments.view')", $show);
        $this->assertStringContainsString("Gate::allows('manage.payment.method.tbank')", $show);
        $this->assertStringContainsString('$showPayoutActions = Gate::allows(\'manage.payment.method.tbank\')', $show);

        $this->assertStringContainsString('@can(\'reports.tbank.payments.view\')', $tabs);
        $this->assertStringContainsString('Платежи T‑Bank', $tabs);

        $this->assertStringContainsString("KidsCrmDataTable.create('#tbank-payments-table'", $blade);
        $this->assertStringContainsString('persistPageLength: true', $blade);
        $this->assertStringContainsString("key: 'payout_amount'", $blade);
        $this->assertStringContainsString('data-column-key="payout_amount"', $blade);
        $this->assertStringContainsString('payout_amount: true', $blade);
        $this->assertStringContainsString("key: 'method'", $blade);
        $this->assertStringContainsString('data-column-key="method"', $blade);
        $this->assertStringContainsString('method: true', $blade);
        $this->assertStringContainsString("key: 'platform_commission'", $blade);
        $this->assertStringContainsString('data-column-key="platform_commission"', $blade);
        $this->assertStringContainsString('platform_commission: true', $blade);
        $this->assertStringContainsString("key: 'receipt'", $blade);
        $this->assertStringContainsString('data-column-key="receipt"', $blade);
        $this->assertStringContainsString('receipt: true', $blade);
        $this->assertStringContainsString('renderTbankReceiptCell', $blade);
        $this->assertStringContainsString('id="tp-filter-method"', $blade);
        $this->assertStringContainsString('id="tp-filter-without-payout"', $blade);
        $this->assertStringContainsString('without_payout:', $blade);
        $this->assertStringContainsString("data: 'method_label'", $blade);
        $this->assertStringContainsString('@can(\'settings.commission\')', $blade);
        $this->assertStringContainsString('id="tp-toolbar-commissions"', $blade);
        $this->assertStringContainsString("route('admin.setting.tbankCommissions')", $blade);
        $this->assertStringContainsString('e.preventDefault()', $blade);
        $this->assertStringContainsString('dtApi.reload();', $blade);
        $this->assertStringContainsString('<option value="" {{ $tpStatus === \'\' ? \'selected\' : \'\' }}>Все статусы</option>', $blade);
        $this->assertStringContainsString('<option value="" {{ $tpMethod === \'\' ? \'selected\' : \'\' }}>Все способы</option>', $blade);

        $this->assertStringContainsString("public const STATUSES = ['NEW', 'FORM', 'CONFIRMED', 'REJECTED', 'CANCELED']", $request);
        $this->assertStringContainsString("public const METHODS = TinkoffPayment::METHODS", $request);
        $this->assertStringContainsString("\$status === 'all'", $request);
        $this->assertStringContainsString("\$method === 'all'", $request);
        $this->assertStringContainsString("'without_payout' => ['nullable', 'boolean']", $request);
        $this->assertStringContainsString("\$withoutPayout === 'all'", $request);

        $this->assertStringContainsString("public const METHODS = ['card', 'sbp', 'tpay']", $model);
        $this->assertStringContainsString("'card' => 'Карта'", $model);
        $this->assertStringContainsString("'sbp' => 'СБП'", $model);
        $this->assertStringContainsString("'tpay' => 'T‑Pay'", $model);
        $this->assertStringContainsString('public static function methodLabel', $model);

        $this->assertStringContainsString("'is_visible' => 0", $migration);
        $this->assertStringNotContainsString('permission_role', explode('function down', $migration)[0]);
        $this->assertStringContainsString("'name' => 'reports.tbank.payments.view'", $seeder);
        $this->assertStringContainsString("'is_visible' => 0", $seeder);

        $this->assertStringContainsString("'reports.tbank.payments.view'", $hints);
        $this->assertStringContainsString('Кнопка «Комиссии» на вкладке отчётов «Платежи T‑Bank»', $hints);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
