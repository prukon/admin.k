<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Закрепление thead (FixedHeader) и липкий горизонтальный скролл
 * на вкладках отчётов: payments, monthly, LTV, debts,
 * payment-intents, fiscal-receipts, tbank-payments, emails.
 *
 * HTTP-проверка без Vite-манифеста (`withoutVite`): плагин и bind в разметке,
 * fallback без плагина, вложенные таблицы без pin.
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
        $this->grantPermission($this->user, 'reports.payment.intents.view');
        $this->grantPermission($this->user, 'reports.fiscal.receipts.view');
        $this->grantPermission($this->user, 'reports.tbank.payments.view');
        $this->grantPermission($this->user, 'reports.emails.view');
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: array<string, string>}>
     */
    public static function stickyReportPages(): array
    {
        return [
            'payments' => ['payments', 'payments-table', "KidsCrmDataTable.create('#payments-table'", ['status' => 'inactive']],
            'monthly'  => ['reports.payments.monthly', 'payments-monthly-table', "KidsCrmDataTable.create('#payments-monthly-table'", ['status' => 'inactive']],
            'ltv'      => ['reports.ltv', 'ltv-table', "KidsCrmDataTable.create('#ltv-table'", ['status' => 'inactive']],
            'debts'    => ['debts', 'debts-table', "KidsCrmDataTable.create('#debts-table'", ['status' => 'inactive']],
            'intents'  => ['reports.payment-intents.index', 'payment-intents-table', "KidsCrmDataTable.create('#payment-intents-table'", []],
            'fiscal'   => ['reports.fiscal-receipts.index', 'fiscal-receipts-table', "KidsCrmDataTable.create('#fiscal-receipts-table'", []],
            'tbank'    => ['reports.tbank-payments.index', 'tbank-payments-table', "KidsCrmDataTable.create('#tbank-payments-table'", []],
            'emails'   => ['reports.emails.index', 'emails-table', "KidsCrmDataTable.create('#emails-table'", []],
        ];
    }

    #[DataProvider('stickyReportPages')]
    public function test_report_page_pins_thead_and_sticky_horizontal_scrollbar(
        string $route,
        string $tableId,
        string $createCall,
        array $reopenQuery
    ): void {
        unset($reopenQuery);
        $html = $this->get(route($route))->assertOk()->getContent();

        $this->assertStickyMarkup($html, $tableId, $createCall);
    }

    #[DataProvider('stickyReportPages')]
    public function test_first_open_and_filter_query_keep_fixedheader_fallback_and_scrollleft_sync(
        string $route,
        string $tableId,
        string $createCall,
        array $reopenQuery
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

        $reopened = (string) $this->get(route($route, $reopenQuery))->assertOk()->getContent();
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

    public function test_extra_report_column_toggles_and_filter_reload_keep_pin_without_recreate(): void
    {
        $cases = [
            'tbank' => ['tbank-payments', 'tbankPaymentsAfterApplyVisibleColumns'],
            'intents' => ['payment-intents', 'paymentIntentsAfterApplyVisibleColumns'],
            'fiscal' => ['fiscal-receipts', 'fiscalReceiptsAfterApplyVisibleColumns'],
            'emails' => ['emails', 'emailsAfterApplyVisibleColumns'],
        ];

        foreach (self::stickyReportPages() as $key => $row) {
            if (! isset($cases[$key])) {
                continue;
            }
            [$route, $tableId, $createCall] = $row;
            [$bindNeedle, $afterApplyFn] = $cases[$key];
            unset($bindNeedle);

            $html = (string) $this->get(route($route))->assertOk()->getContent();
            $afterApply = $this->functionChunk($html, $afterApplyFn, $createCall);
            $this->assertStringContainsString("KidsCrmReportTableSticky.bind('#".$tableId."')", $afterApply);
            $this->assertStringContainsString('afterApplyVisibleColumns: '.$afterApplyFn, $html);
            $this->assertStringContainsString('e.preventDefault()', $html);
            $this->assertStringContainsString('dtApi.reload()', $html);
            $this->assertSame(1, substr_count($html, $createCall));
            $this->assertGreaterThanOrEqual(2, substr_count($html, "KidsCrmReportTableSticky.bind('#".$tableId."')"));
            $this->assertStringNotContainsString('fixedColumns:', $html, $route);
            $this->assertStringNotContainsString('KidsCrmDataTable.create(', substr($html, strpos($html, 'dtApi.reload()') ?: 0), $route);
        }
    }

    public function test_intents_and_fiscal_pages_do_not_enable_fixed_columns_so_sticky_scrollbar_can_pin(): void
    {
        foreach (['intents', 'fiscal'] as $key) {
            [$route, $tableId, $createCall] = self::stickyReportPages()[$key];
            $html = (string) $this->get(route($route))->assertOk()->getContent();

            $this->assertStickyMarkup($html, $tableId, $createCall);
            $this->assertStringNotContainsString('fixedColumns:', $html, $route);
            $this->assertStringNotContainsString('leftColumns:', $html, $route);
            $this->assertStringNotContainsString('dataTables_scrollHead', $html, $route);
            $this->assertMatchesRegularExpression(
                '/drawCallback:\s*function\s*\(\)\s*\{[\s\S]*KidsCrmReportTableSticky\.bind\(\'#'.$tableId.'\'\)/',
                $html
            );
            if ($key === 'intents') {
                $createPos = strpos($html, $createCall);
                $this->assertNotFalse($createPos, $route);
                $createChunk = substr($html, $createPos, 4000);
                $this->assertStringNotContainsString('columns.adjust', $createChunk, $route);
                $this->assertStringContainsString('paymentIntentsLockScrollHost', $html);
                $this->assertStringContainsString("overflow-y', 'hidden'", $html);
            }
        }

        $preset = (string) file_get_contents(resource_path('js/kids-datatable.js'));
        $hostFn = strpos($preset, 'function ensureTableScrollHost');
        $this->assertNotFalse($hostFn);
        $wrapPos = strpos($preset, "\$table.wrap('<div class=\"kids-dt-scroll-x\"></div>')", $hostFn);
        $this->assertNotFalse($wrapPos);
        $skipPos = strpos($preset, 'settings.oInit.fixedColumns', $hostFn);
        $this->assertNotFalse($skipPos);
        $this->assertLessThan($wrapPos, $skipPos, 'fixedColumns должен выходить до wrap, иначе полоса не появится');
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: array<string, string>}>
     */
    public static function extraStickyFirstOpenPages(): array
    {
        return [
            'intents' => [
                'reports.payment-intents.index',
                'payment-intents-table',
                'paymentIntentsFiltersCollapse',
                'payment-intents-column-toggle',
                ['status' => 'paid'],
            ],
            'fiscal' => [
                'reports.fiscal-receipts.index',
                'fiscal-receipts-table',
                'fiscalReceiptsFiltersCollapse',
                'fiscal-receipts-column-toggle',
                ['status' => 'done'],
            ],
            'tbank' => [
                'reports.tbank-payments.index',
                'tbank-payments-table',
                'tbankPaymentsFiltersCollapse',
                'tbank-payments-column-toggle',
                ['status' => 'CONFIRMED'],
            ],
            'emails' => [
                'reports.emails.index',
                'emails-table',
                'emailsReportFiltersCollapse',
                'emails-column-toggle',
                ['q' => 'sticky-pin'],
            ],
        ];
    }

    #[DataProvider('extraStickyFirstOpenPages')]
    public function test_extra_reports_first_open_keeps_columns_checked_and_filters_collapsed(
        string $route,
        string $tableId,
        string $collapseId,
        string $toggleClass,
        array $activeQuery
    ): void {
        unset($activeQuery);
        $html = (string) $this->get(route($route))->assertOk()->getContent();

        $this->assertStickyMarkup($html, $tableId, "KidsCrmDataTable.create('#".$tableId."'");
        $this->assertCollapseWithoutShow($html, $collapseId);
        $this->assertAllColumnTogglesChecked($html, $toggleClass);
        $this->assertSame(1, substr_count($html, "KidsCrmDataTable.create('#".$tableId."'"));
    }

    #[DataProvider('extraStickyFirstOpenPages')]
    public function test_extra_reports_reopen_with_filters_keeps_pin_and_does_not_uncheck_columns(
        string $route,
        string $tableId,
        string $collapseId,
        string $toggleClass,
        array $activeQuery
    ): void {
        $html = (string) $this->get(route($route, $activeQuery))->assertOk()->getContent();

        $this->assertStickyMarkup($html, $tableId, "KidsCrmDataTable.create('#".$tableId."'");
        $this->assertCollapseHasShow($html, $collapseId);
        $this->assertAllColumnTogglesChecked($html, $toggleClass);
        $this->assertSame(1, substr_count($html, "KidsCrmDataTable.create('#".$tableId."'"));
        $this->assertStringContainsString('e.preventDefault()', $html);
        $this->assertStringContainsString('dtApi.reload()', $html);
        $this->assertMatchesRegularExpression(
            '/fixedHeader:\s*\(\$\.fn\.dataTable\s*&&\s*\$\.fn\.dataTable\.FixedHeader\)\s*\?\s*\{\s*header:\s*true,\s*footer:\s*false\s*\}\s*:\s*false/',
            $html
        );
    }

    public function test_tbank_invalid_status_does_not_break_sticky_header_or_return_500(): void
    {
        $index = route('reports.tbank-payments.index');

        $invalid = $this->from($index)->get(route('reports.tbank-payments.index', ['status' => 'inactive']));
        $this->assertNotSame(500, $invalid->getStatusCode());
        $this->assertNotSame(200, $invalid->getStatusCode());
        $invalid->assertStatus(302)->assertSessionHasErrors(['status']);

        $ajax = $this->getJson(route('reports.tbank-payments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'status' => 'inactive',
        ]));
        $this->assertNotSame(500, $ajax->getStatusCode());
        $ajax->assertStatus(422)->assertJsonValidationErrors(['status']);

        $html = (string) $this->get($index)->assertOk()->getContent();
        $this->assertStickyMarkup($html, 'tbank-payments-table', "KidsCrmDataTable.create('#tbank-payments-table'");
        $this->assertCollapseWithoutShow($html, 'tbankPaymentsFiltersCollapse');
        $this->assertAllColumnTogglesChecked($html, 'tbank-payments-column-toggle');
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

    /**
     * @param  non-empty-string  $html
     */
    private function assertCollapseWithoutShow(string $html, string $collapseId): void
    {
        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="'.preg_quote($collapseId, '/').'"[^>]*>/', $html, $tag));
        $this->assertStringNotContainsString('show', $tag[0], $collapseId);
    }

    /**
     * @param  non-empty-string  $html
     */
    private function assertCollapseHasShow(string $html, string $collapseId): void
    {
        $this->assertSame(1, preg_match('/<div\b[^>]*\bid="'.preg_quote($collapseId, '/').'"[^>]*>/', $html, $tag));
        $this->assertStringContainsString('show', $tag[0], $collapseId);
    }

    /**
     * @param  non-empty-string  $html
     */
    private function assertAllColumnTogglesChecked(string $html, string $toggleClass): void
    {
        $count = preg_match_all(
            '/<input\b[^>]*class="[^"]*'.preg_quote($toggleClass, '/').'[^"]*"[^>]*>/',
            $html,
            $tags
        );
        $this->assertGreaterThanOrEqual(3, $count, $toggleClass);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('checked', $tag, $tag);
        }
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
