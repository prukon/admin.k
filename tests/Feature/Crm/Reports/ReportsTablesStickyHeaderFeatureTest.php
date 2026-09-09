<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Закрепление thead (FixedHeader) и липкий горизонтальный скролл
 * на /admin/reports/payments, monthly, LTV и задолженностях.
 *
 * HTTP-проверка без Vite-манифеста (`withoutVite`): плагин и bind в разметке,
 * fallback без плагина, вложенные таблицы без pin, соседние вкладки без бандла.
 * Подключение `@vite` / вход в vite.config.js — BladeInlineJsSyntaxTest
 * и ReportsTablesStickyHeaderDocumentationContractTest.
 */
final class ReportsTablesStickyHeaderFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function stickyReportPages(): array
    {
        return [
            'payments' => ['payments', 'payments-table', "KidsCrmDataTable.create('#payments-table'"],
            'monthly'  => ['reports.payments.monthly', 'payments-monthly-table', "KidsCrmDataTable.create('#payments-monthly-table'"],
            'ltv'      => ['reports.ltv', 'ltv-table', "KidsCrmDataTable.create('#ltv-table'"],
            'debts'    => ['debts', 'debts-table', "KidsCrmDataTable.create('#debts-table'"],
        ];
    }

    #[DataProvider('stickyReportPages')]
    public function test_report_page_pins_thead_and_sticky_horizontal_scrollbar(
        string $route,
        string $tableId,
        string $createCall
    ): void {
        $html = $this->get(route($route))->assertOk()->getContent();

        $this->assertStickyMarkup($html, $tableId, $createCall);
    }

    #[DataProvider('stickyReportPages')]
    public function test_first_open_and_filter_query_keep_fixedheader_fallback_and_scrollleft_sync(
        string $route,
        string $tableId,
        string $createCall
    ): void {
        $first = (string) $this->get(route($route))->assertOk()->getContent();
        $this->assertStickyMarkup($first, $tableId, $createCall);
        $this->assertMatchesRegularExpression(
            '/fixedHeader:\s*\(\$\.fn\.dataTable\s*&&\s*\$\.fn\.dataTable\.FixedHeader\)\s*\?\s*\{\s*header:\s*true,\s*footer:\s*false\s*\}\s*:\s*false/',
            $first,
            'Нет плагина → false; иначе { header: true, footer: false }'
        );
        $this->assertStringNotContainsString('scrollY:', $first);
        $this->assertStringNotContainsString("margin-left', (-", $first);
        $this->assertStringContainsString('fixedHeader.bootstrap4.min.css', $first);
        $this->assertSame(1, substr_count($first, $createCall), 'Фильтры не пересоздают таблицу');

        $reopened = (string) $this->get(route($route, [
            'status' => 'inactive',
        ]))->assertOk()->getContent();
        $this->assertStickyMarkup($reopened, $tableId, $createCall);
        $this->assertMatchesRegularExpression(
            '/fixedHeader:\s*\(\$\.fn\.dataTable\s*&&\s*\$\.fn\.dataTable\.FixedHeader\)\s*\?\s*\{\s*header:\s*true,\s*footer:\s*false\s*\}\s*:\s*false/',
            $reopened
        );
        $this->assertSame(1, substr_count($reopened, $createCall));
    }

    public function test_payments_column_toggle_and_filter_reload_keep_pin_without_recreate(): void
    {
        $html = (string) $this->get(route('payments'))->assertOk()->getContent();

        $afterApply = $this->functionChunk(
            $html,
            'paymentsAfterApplyVisibleColumns',
            "KidsCrmDataTable.create('#payments-table'"
        );
        $this->assertStringContainsString("KidsCrmReportTableSticky.bind('#payments-table')", $afterApply);

        $this->assertStringContainsString("afterApplyVisibleColumns: paymentsAfterApplyVisibleColumns", $html);
        $this->assertStringContainsString('$payFiltersForm.on(\'submit\'', $html);
        $this->assertStringContainsString('dtApi.reload()', $html);
        $this->assertSame(1, substr_count($html, "KidsCrmDataTable.create('#payments-table'"));
        $this->assertGreaterThanOrEqual(2, substr_count($html, "KidsCrmReportTableSticky.bind('#payments-table')"));
    }

    public function test_nested_ltv_and_monthly_detail_tables_do_not_pin_thead(): void
    {
        $ltv = (string) $this->get(route('reports.ltv'))->assertOk()->getContent();
        $ltvNested = $this->functionChunk(
            $ltv,
            'initLtvUserPaymentsDetailTable',
            "KidsCrmDataTable.create('#ltv-table'"
        );
        $this->assertStringContainsString("dom: 'rtip'", $ltvNested);
        $this->assertStringNotContainsString('fixedHeader', $ltvNested);
        $this->assertStringNotContainsString('KidsCrmReportTableSticky', $ltvNested);
        $this->assertStringNotContainsString('dataTables.fixedHeader.min.js', $ltvNested);

        $monthly = (string) $this->get(route('reports.payments.monthly'))->assertOk()->getContent();
        $monthlyNested = $this->functionChunk(
            $monthly,
            'initMonthlyPaymentsDetailTable',
            "KidsCrmDataTable.create('#payments-monthly-table'"
        );
        $this->assertStringContainsString("dom: 'rtip'", $monthlyNested);
        $this->assertStringNotContainsString('fixedHeader', $monthlyNested);
        $this->assertStringNotContainsString('KidsCrmReportTableSticky', $monthlyNested);
    }

    public function test_intents_fiscal_tbank_emails_tabs_do_not_get_report_sticky_bundle(): void
    {
        $this->grantPermission($this->user, 'reports.payment.intents.view');
        $this->grantPermission($this->user, 'reports.fiscal.receipts.view');
        $this->grantPermission($this->user, 'reports.tbank.payments.view');
        $this->grantPermission($this->user, 'reports.emails.view');

        $other = [
            'reports.payment-intents.index' => 'payment-intents-table',
            'reports.fiscal-receipts.index' => 'fiscal-receipts-table',
            'reports.tbank-payments.index' => 'tbank-payments-table',
            'reports.emails.index' => 'emails-table',
        ];

        foreach ($other as $route => $tableId) {
            $page = $this->get(route($route));
            $page->assertOk();
            $html = (string) $page->getContent();
            $this->assertNotSame('', trim($html), $route);
            $this->assertStringContainsString('id="'.$tableId.'"', $html, $route);
            $this->assertStringNotContainsString('KidsCrmReportTableSticky', $html, $route);
            $this->assertStringNotContainsString('admin-reports-tables', $html, $route);
            $this->assertStringNotContainsString('dataTables.fixedHeader.min.js', $html, $route);
            $this->assertStringNotContainsString('fixedHeader.bootstrap4.min.css', $html, $route);
        }
    }

    public function test_table_is_not_wrapped_in_table_responsive_so_sticky_bar_can_pin(): void
    {
        foreach (self::stickyReportPages() as $row) {
            [$route, $tableId] = $row;
            $html = (string) $this->get(route($route))->assertOk()->getContent();
            $tablePos = strpos($html, 'id="'.$tableId.'"');
            $this->assertNotFalse($tablePos, $route);
            $beforeTable = substr($html, max(0, $tablePos - 120), 120);
            $this->assertStringNotContainsString('table-responsive', $beforeTable, $route);
        }
    }

    /**
     * @param  non-empty-string  $html
     */
    private function assertStickyMarkup(string $html, string $tableId, string $createCall): void
    {
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('dataTables.fixedHeader.min.js', $html);
        $this->assertStringContainsString('KidsCrmReportTableSticky.bind(\'#'.$tableId.'\')', $html);
        $this->assertStringContainsString('header: true', $html);
        $this->assertStringContainsString('footer: false', $html);

        $tablePos = strpos($html, 'id="'.$tableId.'"');
        $this->assertNotFalse($tablePos);
        $beforeTable = substr($html, max(0, $tablePos - 120), 120);
        $this->assertStringNotContainsString('table-responsive', $beforeTable);

        $pluginPos = strpos($html, 'dataTables.fixedHeader.min.js');
        $createPos = strpos($html, $createCall);
        $this->assertNotFalse($pluginPos);
        $this->assertNotFalse($createPos);
        $this->assertLessThan($createPos, $pluginPos);
    }

    private function functionChunk(string $html, string $functionName, string $untilNeedle): string
    {
        $start = strpos($html, 'function '.$functionName);
        $this->assertNotFalse($start, $functionName.' не найден');
        $end = strpos($html, $untilNeedle, $start);
        $this->assertNotFalse($end, $untilNeedle.' после '.$functionName);

        return substr($html, $start, $end - $start);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'      => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}
