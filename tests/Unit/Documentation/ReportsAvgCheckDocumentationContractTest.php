<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-avg-check-index совпадает со столбцом «Ср. чек».
 */
final class ReportsAvgCheckDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_avg_check_column(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-avg-check-index"', $html);
        $start = strpos($html, 'id="reports-avg-check-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-group-mode-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/ltv', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv/teams', $chunk);
        $this->assertStringContainsString('/admin/reports/ltv/locations', $chunk);
        $this->assertStringContainsString('/admin/reports/payments/monthly', $chunk);
        $this->assertStringContainsString('#ltv-table', $chunk);
        $this->assertStringContainsString('#ltv-teams-table', $chunk);
        $this->assertStringContainsString('#ltv-locations-table', $chunk);
        $this->assertStringContainsString('#payments-monthly-table', $chunk);
        $this->assertStringContainsString('Ср. чек', $chunk);
        $this->assertStringContainsString('avg_check', $chunk);
        $this->assertStringContainsString('round(sum_cents / count / 100)', $chunk);
        $this->assertStringContainsString('без копеек', $chunk);
        $this->assertStringContainsString('searchable: false', $chunk);
        $this->assertStringContainsString('LtvReportTest', $chunk);
        $this->assertStringContainsString('ReportsAvgCheckDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#reports-avg-check-index', $html);
    }

    public function test_related_docs_and_live_code_match_avg_check_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $reports = $this->docFile('reports-admin.html');
        $membership = $this->docFile('student-team-membership.html');
        $controllerTitles = (string) file_get_contents($root.'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('avg_check', $reports);
        $this->assertStringContainsString('Ср. чек', $reports);
        $this->assertStringContainsString('/doc#reports-avg-check-index', $reports);
        $this->assertStringContainsString('столбец «Ср. чек»', $controllerTitles);

        $this->assertStringContainsString('Ср. чек', $membership);
        $this->assertStringContainsString('/doc#reports-avg-check-index', $membership);

        foreach ([
            'LtvReportController.php' => 'NULLIF(COUNT(payments.id), 0)',
            'LtvTeamsReportController.php' => 'NULLIF(COUNT(payments.id), 0)',
            'LtvLocationsReportController.php' => 'NULLIF(COUNT(payments.id), 0)',
            'PaymentMonthlyReportController.php' => 'NULLIF(COUNT(*), 0)',
        ] as $file => $orderExpr) {
            $source = (string) file_get_contents($root.'/app/Http/Controllers/Admin/Report/'.$file);
            $this->assertStringContainsString("addColumn('avg_check'", $source);
            $this->assertStringContainsString('averageCheckRubles', $source);
            $this->assertStringContainsString('SUM(payments.summ_cents) / '.$orderExpr, $source);
        }

        $blades = [
            'ltv.blade.php' => "name: 'payment_count', searchable: false",
            'ltv_teams.blade.php' => "name: 'payment_count', searchable: false",
            'ltv_locations.blade.php' => "name: 'payment_count', searchable: false",
            'payment_monthly.blade.php' => "name: 'total_sum', searchable: false",
        ];
        foreach ($blades as $blade => $before) {
            $html = (string) file_get_contents($root.'/resources/views/admin/report/'.$blade);
            $this->assertStringContainsString('<th>Ср. чек</th>', $html);
            $this->assertStringContainsString("key: 'avg_check', type: 'money'", $html);
            $this->assertStringContainsString("name: 'avg_check', searchable: false", $html);
            $this->assertTrue(strpos($html, $before) < strpos($html, "key: 'avg_check'"));
        }
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
