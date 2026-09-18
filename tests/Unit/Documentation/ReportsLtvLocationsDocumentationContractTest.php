<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-ltv-locations-index совпадает с вкладкой «Платежи по объектам».
 */
final class ReportsLtvLocationsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ltv_locations_report(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-ltv-locations-index"', $html);
        $start = strpos($html, 'id="reports-ltv-locations-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="tbank-sm-patch-bankaccount-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/ltv/locations', $chunk);
        $this->assertStringContainsString('reports.ltv.locations.view', $chunk);
        $this->assertStringContainsString('is_visible=1', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('payments.location_id', $chunk);
        $this->assertStringContainsString('Без объекта', $chunk);
        $this->assertStringContainsString('#ltv-locations-table', $chunk);
        $this->assertStringContainsString('reports_ltv_locations', $chunk);
        $this->assertStringContainsString('$ltvLocationsPageLength', $chunk);
        $this->assertStringContainsString('groups.own', $chunk);
        $this->assertStringContainsString('can:reports.ltv.locations.view', $chunk);
        $this->assertStringContainsString('ltv/locations/users-search', $chunk);
        $this->assertStringContainsString('LtvLocationsReportFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvLocationsPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvLocationsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('reports-admin#ltv-locations', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-locations-index', $html);
    }

    public function test_related_docs_and_live_code_match_ltv_locations_contract(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $partners = $this->docFile('partners-permissions.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $membership = $this->docFile('student-team-membership.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $bindings = $this->docFile('location-team-bindings.html');
        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="ltv-locations"', $reports);
        $this->assertStringContainsString('/doc#reports-ltv-locations-index', $reports);
        $this->assertStringContainsString('reports.ltv.locations.view', $reports);
        $this->assertStringContainsString('applyLtvLocationsDataTableSearch', $reports);
        $this->assertStringContainsString('#ltv-locations-table', $reports);
        $this->assertStringContainsString('reports_ltv_locations', $reports);
        $this->assertStringContainsString('LtvLocationsReportFeatureTest', $reports);
        $this->assertStringContainsString("type: 'list'", $reports);
        $this->assertStringContainsString('user_names_items', $reports);

        $this->assertStringContainsString('reports.ltv.locations.view', $partners);
        $this->assertStringContainsString('2026_09_18_085500_add_reports_ltv_locations_view_permission.php', $partners);
        $this->assertStringContainsString('/doc#reports-ltv-locations-index', $partners);

        $this->assertStringContainsString('reports.ltv.locations.view', $groups);
        $this->assertStringContainsString('reports.ltv.locations.view', $membership);
        $this->assertStringContainsString('<code>reports_ltv_locations</code>', $ui);
        $this->assertStringContainsString('/admin/reports/ltv/locations', $ui);
        $this->assertStringContainsString('Платежи по объектам', $bindings);

        $this->assertStringContainsString('«Платежи по объектам»', $controllerTitles);
        $this->assertStringContainsString('reports.ltv.locations.view', $controllerTitles);

        $migration = (string) file_get_contents(dirname(__DIR__, 3).'/database/migrations/2026_09_18_085500_add_reports_ltv_locations_view_permission.php');
        $upStart = strpos($migration, 'function up');
        $downStart = strpos($migration, 'function down');
        $this->assertNotFalse($upStart);
        $this->assertNotFalse($downStart);
        $up = substr($migration, $upStart, $downStart - $upStart);
        $this->assertStringNotContainsString('permission_role', $up);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/Report/LtvLocationsReportController.php');
        $this->assertStringContainsString("private const TABLE_KEY = 'reports_ltv_locations'", $controller);
        $this->assertStringContainsString("groupBy('payments.location_id')", $controller);
        $this->assertStringContainsString("'Без объекта'", $controller);
        $this->assertStringContainsString('applyLtvLocationsDataTableSearch', $controller);
        $this->assertStringContainsString('applyOwnTeamsScope', $controller);
        $this->assertStringContainsString('user_names_items', $controller);
        $this->assertStringContainsString('splitLtvLocationsUserNames', $controller);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/ltv_locations.blade.php');
        $this->assertStringContainsString("type: 'list'", $blade);
        $this->assertStringContainsString("itemsKey: 'user_names_items'", $blade);
        $this->assertStringContainsString("kids-hover-list-tooltip--two-col", $blade);
        $this->assertStringContainsString('listOptions', $blade);
        $this->assertStringContainsString("data: 'team_title'", $blade);
        $this->assertStringContainsString('<th>Группа</th>', $blade);

        $routes = (string) file_get_contents(dirname(__DIR__, 3).'/routes/web.php');
        $this->assertStringContainsString("can:reports.ltv.locations.view", $routes);
        $this->assertStringContainsString("reports.ltv.locations.users.search", $routes);
        $this->assertStringContainsString("reports.ltv.locations.teams.search", $routes);
        $this->assertStringContainsString("reports.ltv.locations.trainers.search", $routes);
        $this->assertStringNotContainsString('canany:', $routes);

        $sidebar = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/sidebar.blade.php');
        $this->assertStringContainsString("can('reports.ltv.locations.view')", $sidebar);
        $this->assertStringContainsString("route('reports.ltv.locations')", $sidebar);

        $roleBase = (string) file_get_contents(dirname(__DIR__, 3).'/config/role_base_permissions.php');
        $this->assertSame(2, substr_count($roleBase, "'reports.ltv.locations.view'"));
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
