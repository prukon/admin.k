<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-ltv-teams-index совпадает с вкладкой «Платежи по группам».
 */
final class ReportsLtvTeamsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ltv_teams_report(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-ltv-teams-index"', $html);
        $start = strpos($html, 'id="reports-ltv-teams-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-debts-hide-deleted-users-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/ltv/teams', $chunk);
        $this->assertStringContainsString('reports.ltv.teams.view', $chunk);
        $this->assertStringContainsString('is_visible=1', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('payments.team_id', $chunk);
        $this->assertStringContainsString('Без группы', $chunk);
        $this->assertStringContainsString('#ltv-teams-table', $chunk);
        $this->assertStringContainsString('reports_ltv_teams', $chunk);
        $this->assertStringContainsString('$ltvTeamsPageLength', $chunk);
        $this->assertStringContainsString('groups.own', $chunk);
        $this->assertStringContainsString('can:reports.ltv.teams.view', $chunk);
        $this->assertStringContainsString('ltv/teams/users-search', $chunk);
        $this->assertStringContainsString('LtvTeamsReportFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvTeamsPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvTeamsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('reports-admin#ltv-teams', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-teams-index', $html);
    }

    public function test_related_docs_and_live_code_match_ltv_teams_contract(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $partners = $this->docFile('partners-permissions.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $membership = $this->docFile('student-team-membership.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="ltv-teams"', $reports);
        $this->assertStringContainsString('/doc#reports-ltv-teams-index', $reports);
        $this->assertStringContainsString('reports.ltv.teams.view', $reports);
        $this->assertStringContainsString('applyLtvTeamsDataTableSearch', $reports);
        $this->assertStringContainsString('#ltv-teams-table', $reports);
        $this->assertStringContainsString('reports_ltv_teams', $reports);
        $this->assertStringContainsString('LtvTeamsReportFeatureTest', $reports);
        $this->assertStringContainsString("type: 'list'", $reports);
        $this->assertStringContainsString('user_names_items', $reports);
        $this->assertStringContainsString('KidsCrmTooltip.renderList', $reports);
        $this->assertStringContainsString('kids-hover-list-tooltip--two-col', $reports);
        $this->assertStringContainsString('column-fill: auto', $reports);
        $this->assertStringContainsString('listOptions', $ui);
        $this->assertStringContainsString('customClass', $ui);

        $this->assertStringContainsString('reports.ltv.teams.view', $partners);
        $this->assertStringContainsString('2026_09_17_093700_add_reports_ltv_teams_view_permission.php', $partners);
        $this->assertStringContainsString('/doc#reports-ltv-teams-index', $partners);

        $this->assertStringContainsString('reports.ltv.teams.view', $groups);
        $this->assertStringContainsString('reports.ltv.teams.view', $membership);
        $this->assertStringContainsString('<code>reports_ltv_teams</code>', $ui);
        $this->assertStringContainsString('/admin/reports/ltv/teams', $ui);

        $this->assertStringContainsString('«Платежи по группам»', $controllerTitles);
        $this->assertStringContainsString('reports.ltv.teams.view', $controllerTitles);

        $migration = (string) file_get_contents(dirname(__DIR__, 3).'/database/migrations/2026_09_17_093700_add_reports_ltv_teams_view_permission.php');
        $upStart = strpos($migration, 'function up');
        $downStart = strpos($migration, 'function down');
        $this->assertNotFalse($upStart);
        $this->assertNotFalse($downStart);
        $up = substr($migration, $upStart, $downStart - $upStart);
        $this->assertStringNotContainsString('permission_role', $up);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/Report/LtvTeamsReportController.php');
        $this->assertStringContainsString("private const TABLE_KEY = 'reports_ltv_teams'", $controller);
        $this->assertStringContainsString("groupBy('payments.team_id')", $controller);
        $this->assertStringContainsString("'Без группы'", $controller);
        $this->assertStringContainsString('applyLtvTeamsDataTableSearch', $controller);
        $this->assertStringContainsString('applyOwnTeamsScope', $controller);
        $this->assertStringContainsString('user_names_items', $controller);
        $this->assertStringContainsString('splitLtvTeamsUserNames', $controller);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/ltv_teams.blade.php');
        $this->assertStringContainsString("type: 'list'", $blade);
        $this->assertStringContainsString("itemsKey: 'user_names_items'", $blade);
        $this->assertStringContainsString("kids-hover-list-tooltip--two-col", $blade);
        $this->assertStringContainsString('listOptions', $blade);

        $tooltipJs = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/kids-tooltip.js');
        $this->assertStringContainsString('sanitizeListTooltipExtraClass', $tooltipJs);
        $this->assertStringContainsString("el.getAttribute('data-bs-custom-class') || LIST_TOOLTIP_CLASS", $tooltipJs);

        $datatableJs = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/kids-datatable.js');
        $this->assertStringContainsString('col.listOptions', $datatableJs);

        $tooltipCss = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/kids-tooltip.css');
        $this->assertStringContainsString('.tooltip.kids-hover-list-tooltip--two-col', $tooltipCss);
        $this->assertStringContainsString('column-count: 2', $tooltipCss);
        $this->assertStringContainsString('column-fill: auto', $tooltipCss);
        $this->assertStringContainsString('overflow: hidden', $tooltipCss);
        $this->assertStringContainsString('10 * 1.45em', $tooltipCss);

        $routes = (string) file_get_contents(dirname(__DIR__, 3).'/routes/web.php');
        $this->assertStringContainsString("can:reports.ltv.teams.view", $routes);
        $this->assertStringContainsString("reports.ltv.teams.users.search", $routes);
        $this->assertStringContainsString("reports.ltv.teams.teams.search", $routes);
        $this->assertStringContainsString("reports.ltv.teams.trainers.search", $routes);
        $this->assertStringNotContainsString('canany:', $routes);

        $sidebar = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/sidebar.blade.php');
        $this->assertStringContainsString("can('reports.ltv.teams.view')", $sidebar);
        $this->assertStringContainsString("route('reports.ltv.teams')", $sidebar);

        $roleBase = (string) file_get_contents(dirname(__DIR__, 3).'/config/role_base_permissions.php');
        $this->assertSame(2, substr_count($roleBase, "'reports.ltv.teams.view'"));
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
