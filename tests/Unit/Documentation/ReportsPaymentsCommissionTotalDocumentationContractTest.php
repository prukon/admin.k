<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-payments-commission-total-index совпадает с каталогом и отчётом.
 */
final class ReportsPaymentsCommissionTotalDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_hidden_commission_total_permission(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-payments-commission-total-index"', $html);
        $start = strpos($html, 'id="reports-payments-commission-total-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-peer-card-cross-partner-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('reports.payments.commission_total.view', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('role_base_permissions.php', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('2026_09_06_224500_hide_reports_payments_commission_total_permission.php', $chunk);
        $this->assertStringContainsString('permission_role', $chunk);
        $this->assertStringContainsString('payColCommissionTotal', $chunk);
        $this->assertStringContainsString('commission_total = null', $chunk);
        $this->assertStringContainsString('reports.additional.value.view', $chunk);
        $this->assertStringContainsString('reports.payments.payout_amount.column.view', $chunk);
        $this->assertStringContainsString('ReportsPaymentsCommissionTotalPermissionCatalogFeatureTest', $chunk);
        $this->assertStringContainsString('test_new_partner_does_not_assign_reports_payments_commission_total_to_base_roles', $chunk);
        $this->assertStringContainsString('test_payments_report_commission_total_requires_reports_payments_commission_total_permission', $chunk);
        $this->assertStringContainsString('test_payments_report_commission_total_column_visible_with_permission', $chunk);
        $this->assertStringContainsString('/docs/documentation/reports-payments', $chunk);
        $this->assertStringContainsString('partners-permissions#optional-admin-permissions', $chunk);
        $this->assertStringContainsString('settings-permission-groups', $chunk);
    }

    public function test_reports_payments_page_documents_hidden_commission_total_permission(): void
    {
        $html = $this->docFile('reports-payments.html');

        $this->assertStringContainsString('reports.payments.commission_total.view', $html);
        $this->assertStringContainsString('is_visible=0', $html);
        $this->assertStringContainsString('2026_09_06_224500_hide_reports_payments_commission_total_permission.php', $html);
        $this->assertStringContainsString('/doc#reports-payments-commission-total-index', $html);
        $this->assertStringContainsString('ReportsPaymentsCommissionTotalPermissionCatalogFeatureTest', $html);
        $this->assertStringContainsString('commission_total</code> тоже <code>null</code>', $html);
    }

    public function test_partners_and_groups_pages_list_permission_as_optional_hidden(): void
    {
        $partners = $this->docFile('partners-permissions.html');
        $this->assertStringContainsString('reports.payments.commission_total.view', $partners);
        $this->assertStringContainsString('test_new_partner_does_not_assign_reports_payments_commission_total_to_base_roles', $partners);
        $this->assertStringContainsString('/doc#reports-payments-commission-total-index', $partners);

        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('reports.payments.commission_total.view', $groups);
        $this->assertStringContainsString('2026_09_06_224500_hide_reports_payments_commission_total_permission.php', $groups);
        $this->assertStringContainsString('/doc#reports-payments-commission-total-index', $groups);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
