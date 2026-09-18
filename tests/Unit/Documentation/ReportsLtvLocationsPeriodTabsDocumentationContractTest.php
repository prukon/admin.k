<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-ltv-locations-period-tabs-index совпадает с табами периода
 * на «Платежи по объектам».
 */
final class ReportsLtvLocationsPeriodTabsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ltv_locations_period_tabs(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-ltv-locations-period-tabs-index"', $html);
        $start = strpos($html, 'id="reports-ltv-locations-period-tabs-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-locations-avg-attendance-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/ltv/locations', $chunk);
        $this->assertStringContainsString('reports.ltv.locations.view', $chunk);
        $this->assertStringContainsString('#ltv-locations-table', $chunk);
        $this->assertStringContainsString('#ltv-locations-period-switch', $chunk);
        $this->assertStringContainsString('#tp-view-switch', $chunk);
        $this->assertStringContainsString('#ltv-locations-period-btn-current', $chunk);
        $this->assertStringContainsString('#ltv-locations-period-btn-previous', $chunk);
        $this->assertStringContainsString('#ltv-locations-period-btn-all', $chunk);
        $this->assertStringContainsString('ltvLocationsPeriod', $chunk);
        $this->assertStringContainsString('ltvLocationsPeriodLabels', $chunk);
        $this->assertStringContainsString('admin/report/index.blade.php', $chunk);
        $this->assertStringContainsString('period=current|previous|all', $chunk);
        $this->assertStringContainsString('LtvLocationsReportPeriodRequest', $chunk);
        $this->assertStringContainsString("'period' => ['nullable', 'string', 'in:current,previous,all']", $chunk);
        $this->assertStringContainsString('data-error-for="period"', $chunk);
        $this->assertStringContainsString('$payFilterKeys', $chunk);
        $this->assertStringContainsString('payments.operation_date', $chunk);
        $this->assertStringContainsString('whereDate', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('refreshLtvLocationsReportTotal()', $chunk);
        $this->assertStringContainsString('ltvLocationsReportFilterParams()', $chunk);
        $this->assertStringContainsString('period: currentPeriod', $chunk);
        $this->assertStringContainsString('ltvLocationsSyncPeriodInUrl', $chunk);
        $this->assertStringContainsString('LtvLocationsReportPeriodTabsFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvLocationsPeriodTabsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('reports-admin#ltv-locations', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-locations-period-tabs-index', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-locations-index', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-locations-avg-attendance-index', $chunk);
    }

    public function test_related_docs_and_live_code_match_period_tabs_contract(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $membership = $this->docFile('student-team-membership.html');
        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-ltv-locations-period-tabs-index', $reports);
        $this->assertStringContainsString('#ltv-locations-period-switch', $reports);
        $this->assertStringContainsString('LtvLocationsReportPeriodRequest', $reports);
        $this->assertStringContainsString('LtvLocationsReportPeriodTabsFeatureTest', $reports);
        $this->assertStringContainsString('ReportsLtvLocationsPeriodTabsDocumentationContractTest', $reports);

        $this->assertStringContainsString('/doc#reports-ltv-locations-period-tabs-index', $membership);
        $this->assertStringContainsString('табы периода current/previous/all', $controllerTitles);

        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Admin/Report/LtvLocationsReportPeriodRequest.php');
        $this->assertStringContainsString("public const PERIODS = ['current', 'previous', 'all']", $request);
        $this->assertStringContainsString("'period' => ['nullable', 'string', 'in:'.implode(',', self::PERIODS)]", $request);
        $this->assertStringContainsString("'period' => 'Период'", $request);
        $this->assertStringContainsString("'period.in' => 'Поле «:attribute» содержит недопустимое значение.'", $request);
        $this->assertStringContainsString('function periodDateRange()', $request);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/Report/LtvLocationsReportController.php');
        $this->assertStringContainsString('LtvLocationsReportPeriodRequest', $controller);
        $this->assertStringContainsString('periodDateRange()', $controller);
        $this->assertStringContainsString("'ltvLocationsPeriod' => \$request->period()", $controller);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/ltv_locations.blade.php');
        $this->assertStringContainsString('id="ltv-locations-period-switch"', $blade);
        $this->assertStringContainsString('id="ltv-locations-period-btn-current"', $blade);
        $this->assertStringContainsString('id="ltv-locations-period-btn-previous"', $blade);
        $this->assertStringContainsString('id="ltv-locations-period-btn-all"', $blade);
        $this->assertStringContainsString('data-error-for="period"', $blade);
        $this->assertStringContainsString("\$('.js-ltv-locations-period-btn').on('click'", $blade);
        $this->assertStringContainsString('period: currentPeriod', $blade);
        $this->assertStringContainsString('function ltvLocationsSyncPeriodInUrl()', $blade);
        $this->assertStringContainsString('dtApi.reload();', $blade);
        $this->assertStringNotContainsString('KidsCrmDataTable.create', substr(
            $blade,
            (int) strpos($blade, "\$('.js-ltv-locations-period-btn').on('click'"),
            900
        ));

        $tabs = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/index.blade.php');
        $this->assertStringContainsString("'ltvLocationsPeriod' => \$ltvLocationsPeriod ?? 'current'", $tabs);
        $this->assertStringContainsString("'ltvLocationsPeriodLabels' => \$ltvLocationsPeriodLabels ?? [", $tabs);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
