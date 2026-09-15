<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-debts-hide-deleted-users-index совпадает с отчётом задолженностей:
 * soft-deleted ученики скрыты всегда, без чекбокса «Удаленные».
 */
final class ReportsDebtsHideDeletedUsersDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_debts_hide_deleted_users(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-debts-hide-deleted-users-index"', $html);
        $start = strpos($html, 'id="reports-debts-hide-deleted-users-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-monthly-paid-team-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/debts', $chunk);
        $this->assertStringContainsString('reports.view', $chunk);
        $this->assertStringContainsString('whereNull(users.deleted_at)', $chunk);
        $this->assertStringContainsString('applyDebtReportNotDeletedUserFilter', $chunk);
        $this->assertStringContainsString('users_prices', $chunk);
        $this->assertStringContainsString('user_custom_payment', $chunk);
        $this->assertStringContainsString('getDebts', $chunk);
        $this->assertStringContainsString('debts/total', $chunk);
        $this->assertStringContainsString('is_enabled', $chunk);
        $this->assertStringContainsString('Чекбокса «Удаленные» нет', $chunk);
        $this->assertStringContainsString('reports-admin#debts', $chunk);
        $this->assertStringContainsString('test_debts_exclude_soft_deleted_users_from_table_and_total', $chunk);
        $this->assertStringContainsString('test_debts_exclude_soft_deleted_users_when_status_all', $chunk);
        $this->assertStringContainsString('DeptReportTest', $chunk);
        $this->assertStringContainsString('ReportsDebtsHideDeletedUsersDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#reports-debts-hide-deleted-users-index', $html);
        $this->assertStringContainsString('без soft-deleted учеников', $html);
    }

    public function test_reports_admin_and_controller_hide_deleted_users(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $this->assertStringContainsString('id="debts"', $reports);
        $this->assertStringContainsString('whereNull(\'users.deleted_at\')', $reports);
        $this->assertStringContainsString('applyDebtReportNotDeletedUserFilter', $reports);
        $this->assertStringContainsString('/doc#reports-debts-hide-deleted-users-index', $reports);
        $this->assertStringContainsString('Чекбокса «Удаленные» в этом отчёте', $reports);
        $this->assertStringContainsString('test_debts_exclude_soft_deleted_users_from_table_and_total', $reports);
        $this->assertStringContainsString('ReportsDebtsHideDeletedUsersDocumentationContractTest', $reports);

        $controller = $this->appFile('Http/Controllers/Admin/Report/DeptReportController.php');
        $this->assertSame(2, substr_count($controller, '$this->applyDebtReportNotDeletedUserFilter($query)'));
        $this->assertStringContainsString("whereNull('users.deleted_at')", $controller);
        $this->assertStringContainsString('function applyDebtReportNotDeletedUserFilter', $controller);

        $custom = $this->docFile('setting-prices-custom-payments.html');
        $this->assertStringContainsString('reports-debts-hide-deleted-users-index', $custom);
        $this->assertStringContainsString('users.deleted_at', $custom);
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
