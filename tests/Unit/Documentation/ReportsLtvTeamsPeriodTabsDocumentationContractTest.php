<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-ltv-teams-period-tabs-index совпадает с табами периода
 * на «Платежи по группам».
 */
final class ReportsLtvTeamsPeriodTabsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ltv_teams_period_tabs(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-ltv-teams-period-tabs-index"', $html);
        $start = strpos($html, 'id="reports-ltv-teams-period-tabs-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-teams-avg-attendance-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/ltv/teams', $chunk);
        $this->assertStringContainsString('reports.ltv.teams.view', $chunk);
        $this->assertStringContainsString('#ltv-teams-table', $chunk);
        $this->assertStringContainsString('#ltv-teams-period-switch', $chunk);
        $this->assertStringContainsString('#tp-view-switch', $chunk);
        $this->assertStringContainsString('#ltv-teams-period-btn-current', $chunk);
        $this->assertStringContainsString('#ltv-teams-period-btn-previous', $chunk);
        $this->assertStringContainsString('#ltv-teams-period-btn-all', $chunk);
        $this->assertStringContainsString('ltvTeamsPeriod', $chunk);
        $this->assertStringContainsString('ltvTeamsPeriodLabels', $chunk);
        $this->assertStringContainsString('admin/report/index.blade.php', $chunk);
        $this->assertStringContainsString('period=current|previous|all', $chunk);
        $this->assertStringContainsString('LtvTeamsReportPeriodRequest', $chunk);
        $this->assertStringContainsString("'period' => ['nullable', 'string', 'in:current,previous,all']", $chunk);
        $this->assertStringContainsString('data-error-for="period"', $chunk);
        $this->assertStringContainsString('$payFilterKeys', $chunk);
        $this->assertStringContainsString('payments.operation_date', $chunk);
        $this->assertStringContainsString('whereDate', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('refreshLtvTeamsReportTotal()', $chunk);
        $this->assertStringContainsString('ltvTeamsReportFilterParams()', $chunk);
        $this->assertStringContainsString('period: currentPeriod', $chunk);
        $this->assertStringContainsString('ltvTeamsSyncPeriodInUrl', $chunk);
        $this->assertStringContainsString('LtvTeamsReportPeriodTabsFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvTeamsPeriodTabsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('reports-admin#ltv-teams', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-teams-period-tabs-index', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-teams-index', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-teams-avg-attendance-index', $chunk);
    }

    public function test_related_docs_and_live_code_match_period_tabs_contract(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $membership = $this->docFile('student-team-membership.html');
        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-ltv-teams-period-tabs-index', $reports);
        $this->assertStringContainsString('#ltv-teams-period-switch', $reports);
        $this->assertStringContainsString('LtvTeamsReportPeriodRequest', $reports);
        $this->assertStringContainsString('LtvTeamsReportPeriodTabsFeatureTest', $reports);
        $this->assertStringContainsString('ReportsLtvTeamsPeriodTabsDocumentationContractTest', $reports);

        $this->assertStringContainsString('/doc#reports-ltv-teams-period-tabs-index', $membership);
        $this->assertStringContainsString('табы периода current/previous/all', $controllerTitles);

        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Admin/Report/LtvTeamsReportPeriodRequest.php');
        $this->assertStringContainsString("public const PERIODS = ['current', 'previous', 'all']", $request);
        $this->assertStringContainsString("'period' => ['nullable', 'string', 'in:'.implode(',', self::PERIODS)]", $request);
        $this->assertStringContainsString("'period' => 'Период'", $request);
        $this->assertStringContainsString("'period.in' => 'Поле «:attribute» содержит недопустимое значение.'", $request);
        $this->assertStringContainsString('function periodDateRange()', $request);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/Report/LtvTeamsReportController.php');
        $this->assertStringContainsString('LtvTeamsReportPeriodRequest', $controller);
        $this->assertStringContainsString('periodDateRange()', $controller);
        $this->assertStringContainsString("'ltvTeamsPeriod' => \$request->period()", $controller);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/ltv_teams.blade.php');
        $this->assertStringContainsString('id="ltv-teams-period-switch"', $blade);
        $this->assertStringContainsString('id="ltv-teams-period-btn-current"', $blade);
        $this->assertStringContainsString('id="ltv-teams-period-btn-previous"', $blade);
        $this->assertStringContainsString('id="ltv-teams-period-btn-all"', $blade);
        $this->assertStringContainsString('data-error-for="period"', $blade);
        $this->assertStringContainsString("\$('.js-ltv-teams-period-btn').on('click'", $blade);
        $this->assertStringContainsString('period: currentPeriod', $blade);
        $this->assertStringContainsString('function ltvTeamsSyncPeriodInUrl()', $blade);
        $this->assertStringContainsString('dtApi.reload();', $blade);
        $this->assertStringNotContainsString('KidsCrmDataTable.create', substr(
            $blade,
            (int) strpos($blade, "\$('.js-ltv-teams-period-btn').on('click'"),
            900
        ));

        $tabs = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/index.blade.php');
        $this->assertStringContainsString("'ltvTeamsPeriod' => \$ltvTeamsPeriod ?? 'current'", $tabs);
        $this->assertStringContainsString("'ltvTeamsPeriodLabels' => \$ltvTeamsPeriodLabels ?? [", $tabs);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
