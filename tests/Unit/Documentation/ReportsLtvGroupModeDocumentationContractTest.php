<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-ltv-group-mode-index совпадает с табами группировки
 * на «Платежи по группам» и «Платежи по объектам».
 */
final class ReportsLtvGroupModeDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ltv_group_mode_tabs(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-ltv-group-mode-index"', $html);
        $start = strpos($html, 'id="reports-ltv-group-mode-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-locations-period-tabs-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/ltv/teams', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv/locations', $chunk);
        $this->assertStringContainsString('reports.ltv.teams.view', $chunk);
        $this->assertStringContainsString('reports.ltv.locations.view', $chunk);
        $this->assertStringContainsString('#ltv-teams-table', $chunk);
        $this->assertStringContainsString('#ltv-locations-table', $chunk);
        $this->assertStringContainsString('#ltv-teams-group-mode-switch', $chunk);
        $this->assertStringContainsString('#ltv-locations-group-mode-switch', $chunk);
        $this->assertStringContainsString('#ltv-teams-group-mode-btn-subscription', $chunk);
        $this->assertStringContainsString('#ltv-teams-group-mode-btn-operation', $chunk);
        $this->assertStringContainsString('#ltv-locations-group-mode-btn-subscription', $chunk);
        $this->assertStringContainsString('#ltv-locations-group-mode-btn-operation', $chunk);
        $this->assertStringContainsString('ltvTeamsMode', $chunk);
        $this->assertStringContainsString('ltvLocationsMode', $chunk);
        $this->assertStringContainsString('admin/report/index.blade.php', $chunk);
        $this->assertStringContainsString('mode=operation|subscription', $chunk);
        $this->assertStringContainsString('LtvTeamsReportPeriodRequest', $chunk);
        $this->assertStringContainsString('LtvLocationsReportPeriodRequest', $chunk);
        $this->assertStringContainsString("'mode' => ['nullable', 'string', 'in:operation,subscription']", $chunk);
        $this->assertStringContainsString('data-error-for="mode"', $chunk);
        $this->assertStringContainsString('$payFilterKeys', $chunk);
        $this->assertStringContainsString('payments.operation_date', $chunk);
        $this->assertStringContainsString('whereDate', $chunk);
        $this->assertStringContainsString('periodDateRange', $chunk);
        $this->assertStringContainsString('payments.payment_month', $chunk);
        $this->assertStringContainsString('periodSubscriptionYearMonth', $chunk);
        $this->assertStringContainsString('dtApi.reload()', $chunk);
        $this->assertStringContainsString('refreshLtvTeamsReportTotal()', $chunk);
        $this->assertStringContainsString('refreshLtvLocationsReportTotal()', $chunk);
        $this->assertStringContainsString('ltvTeamsReportFilterParams()', $chunk);
        $this->assertStringContainsString('ltvLocationsReportFilterParams()', $chunk);
        $this->assertStringContainsString('mode: currentMode', $chunk);
        $this->assertStringContainsString('LtvTeamsReportPeriodTabsFeatureTest', $chunk);
        $this->assertStringContainsString('LtvLocationsReportPeriodTabsFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvGroupModeDocumentationContractTest', $chunk);
        $this->assertStringContainsString('reports-admin#ltv-teams', $chunk);
        $this->assertStringContainsString('reports-admin#ltv-locations', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-group-mode-index', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-teams-period-tabs-index', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-locations-period-tabs-index', $chunk);
    }

    public function test_related_docs_and_live_code_match_group_mode_contract(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $membership = $this->docFile('student-team-membership.html');
        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#reports-ltv-group-mode-index', $reports);
        $this->assertStringContainsString('#ltv-teams-group-mode-switch', $reports);
        $this->assertStringContainsString('#ltv-locations-group-mode-switch', $reports);
        $this->assertStringContainsString('periodSubscriptionYearMonth', $reports);
        $this->assertStringContainsString('LtvTeamsReportPeriodTabsFeatureTest', $reports);
        $this->assertStringContainsString('LtvLocationsReportPeriodTabsFeatureTest', $reports);
        $this->assertStringContainsString('ReportsLtvGroupModeDocumentationContractTest', $reports);

        $this->assertStringContainsString('/doc#reports-ltv-group-mode-index', $membership);
        $this->assertStringContainsString('mode=operation|subscription', $controllerTitles);

        foreach ([
            'LtvTeamsReportPeriodRequest.php',
            'LtvLocationsReportPeriodRequest.php',
        ] as $requestFile) {
            $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Admin/Report/'.$requestFile);
            $this->assertStringContainsString("public const MODES = ['operation', 'subscription']", $request);
            $this->assertStringContainsString("'mode' => ['nullable', 'string', 'in:'.implode(',', self::MODES)]", $request);
            $this->assertStringContainsString("'mode' => 'Группировка'", $request);
            $this->assertStringContainsString("'mode.in' => 'Поле «:attribute» содержит недопустимое значение.'", $request);
            $this->assertStringContainsString('function mode()', $request);
            $this->assertStringContainsString('function periodSubscriptionYearMonth()', $request);
        }

        $teamsController = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/Report/LtvTeamsReportController.php');
        $this->assertStringContainsString("\$request->mode() === 'subscription'", $teamsController);
        $this->assertStringContainsString('periodSubscriptionYearMonth()', $teamsController);
        $this->assertStringContainsString("'ltvTeamsMode' => \$request->mode()", $teamsController);

        $locationsController = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/Report/LtvLocationsReportController.php');
        $this->assertStringContainsString("\$request->mode() === 'subscription'", $locationsController);
        $this->assertStringContainsString('periodSubscriptionYearMonth()', $locationsController);
        $this->assertStringContainsString("'ltvLocationsMode' => \$request->mode()", $locationsController);

        $teamsBlade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/ltv_teams.blade.php');
        $this->assertStringContainsString('id="ltv-teams-group-mode-switch"', $teamsBlade);
        $this->assertStringContainsString('id="ltv-teams-group-mode-btn-subscription"', $teamsBlade);
        $this->assertStringContainsString('id="ltv-teams-group-mode-btn-operation"', $teamsBlade);
        $this->assertStringContainsString('data-error-for="mode"', $teamsBlade);
        $this->assertStringContainsString("\$('.js-ltv-teams-group-mode-btn').on('click'", $teamsBlade);
        $this->assertStringContainsString('mode: currentMode', $teamsBlade);
        $this->assertStringNotContainsString('KidsCrmDataTable.create', substr(
            $teamsBlade,
            (int) strpos($teamsBlade, "\$('.js-ltv-teams-group-mode-btn').on('click'"),
            900
        ));

        $locationsBlade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/ltv_locations.blade.php');
        $this->assertStringContainsString('id="ltv-locations-group-mode-switch"', $locationsBlade);
        $this->assertStringContainsString('id="ltv-locations-group-mode-btn-subscription"', $locationsBlade);
        $this->assertStringContainsString('id="ltv-locations-group-mode-btn-operation"', $locationsBlade);
        $this->assertStringContainsString('data-error-for="mode"', $locationsBlade);
        $this->assertStringContainsString("\$('.js-ltv-locations-group-mode-btn').on('click'", $locationsBlade);
        $this->assertStringContainsString('mode: currentMode', $locationsBlade);
        $this->assertStringNotContainsString('KidsCrmDataTable.create', substr(
            $locationsBlade,
            (int) strpos($locationsBlade, "\$('.js-ltv-locations-group-mode-btn').on('click'"),
            900
        ));

        $tabs = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/index.blade.php');
        $this->assertStringContainsString("'ltvTeamsMode' => \$ltvTeamsMode ?? 'operation'", $tabs);
        $this->assertStringContainsString("'ltvLocationsMode' => \$ltvLocationsMode ?? 'operation'", $tabs);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
