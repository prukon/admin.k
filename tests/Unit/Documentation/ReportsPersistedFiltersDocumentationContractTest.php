<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class ReportsPersistedFiltersDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_persisted_report_filters(): void
    {
        $html = $this->docFile('index.html');
        $this->assertStringContainsString('id="reports-persisted-filters-index"', $html);

        $start = strpos($html, 'id="reports-persisted-filters-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, '<h2', $start + 10);
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('user_table_settings.filters', $chunk);
        $this->assertStringContainsString('/admin/reports/payments', $chunk);
        $this->assertStringContainsString('/admin/reports/payments/monthly', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv/teams', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv/locations', $chunk);
        $this->assertStringContainsString('/admin/reports/debts', $chunk);
        $this->assertStringContainsString('setting_prices_monthly', $chunk);
        $this->assertStringContainsString('PersistedReportFiltersFeatureTest', $chunk);
        $this->assertStringContainsString('PersistedReportFiltersHttpContractFeatureTest', $chunk);
        $this->assertStringContainsString('reports-admin#persisted-report-filters', $chunk);
    }

    public function test_reports_pages_document_filters_column(): void
    {
        $payments = $this->docFile('reports-payments.html');
        $this->assertStringContainsString('id="persisted-filters"', $payments);
        $this->assertStringContainsString('reports.payments.filters.save', $payments);
        $this->assertStringContainsString('SavePaymentsReportFiltersRequest', $payments);
        $this->assertStringContainsString('user_table_settings.filters', $payments);

        $admin = $this->docFile('reports-admin.html');
        $this->assertStringContainsString('id="persisted-report-filters"', $admin);
        $this->assertStringContainsString('reports_payments_monthly', $admin);
        $this->assertStringContainsString('reports_ltv', $admin);
        $this->assertStringContainsString('reports_ltv_teams', $admin);
        $this->assertStringContainsString('reports_ltv_locations', $admin);
        $this->assertStringContainsString('SaveLtvTeamsReportFiltersRequest', $admin);
        $this->assertStringContainsString('SaveLtvLocationsReportFiltersRequest', $admin);
        $this->assertStringContainsString('reports_debts', $admin);
        $this->assertStringContainsString('reset=1', $admin);
        $this->assertStringContainsString('302', $admin);
        $this->assertStringContainsString('user_name[]=', $admin);
        $this->assertStringContainsString('PersistedReportFiltersHttpContractFeatureTest', $admin);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
