<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-debts-context-columns-index совпадает с колонками
 * админа, абонемента, группы и объекта в отчёте задолженностей.
 */
final class ReportsDebtsContextColumnsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_debts_context_columns(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-debts-context-columns-index"', $html);
        $start = strpos($html, 'id="reports-debts-context-columns-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-debts-hide-deleted-users-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/debts', $chunk);
        $this->assertStringContainsString('locations.view', $chunk);
        $this->assertStringContainsString('users_prices.team_id', $chunk);
        $this->assertStringContainsString('user_custom_payment.team_id', $chunk);
        $this->assertStringContainsString('teams.location_id', $chunk);
        $this->assertStringContainsString('location_admin_user', $chunk);
        $this->assertStringContainsString('lesson_packages.name', $chunk);
        $this->assertStringContainsString('Без объекта', $chunk);
        $this->assertStringContainsString('еще N шт.', $chunk);
        $this->assertStringContainsString('reports-admin#debts-context-columns', $chunk);
        $this->assertStringContainsString('test_getDebts_context_columns_from_team_location_and_package', $chunk);
        $this->assertStringContainsString('/doc#reports-debts-context-columns-index', $chunk);
    }

    public function test_reports_admin_describes_debts_context_columns(): void
    {
        $reports = $this->docFile('reports-admin.html');

        $this->assertStringContainsString('id="debts-context-columns"', $reports);
        $this->assertStringContainsString('/doc#reports-debts-context-columns-index', $reports);
        $this->assertStringContainsString('location_admin', $reports);
        $this->assertStringContainsString('lesson_package_name', $reports);
        $this->assertStringContainsString('users_prices.lesson_package_id', $reports);
        $this->assertStringContainsString('locations.view', $reports);

        $controller = $this->appFile('Http/Controllers/Admin/Report/DeptReportController.php');
        $this->assertStringContainsString('function debtReportAdminNamesSql', $controller);
        $this->assertStringContainsString('lesson_package_name', $controller);
        $this->assertStringContainsString('location_admin_names_raw', $controller);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/debt.blade.php');
        $this->assertStringContainsString('data-column-key="location_admin"', $blade);
        $this->assertStringContainsString('data-column-key="lesson_package"', $blade);
        $this->assertStringContainsString('data-column-key="team_title"', $blade);
        $this->assertStringContainsString('data-column-key="location"', $blade);
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
