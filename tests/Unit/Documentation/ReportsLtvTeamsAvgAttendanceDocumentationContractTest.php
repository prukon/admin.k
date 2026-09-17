<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#reports-ltv-teams-avg-attendance-index совпадает со столбцом «Ср. посещаемость».
 */
final class ReportsLtvTeamsAvgAttendanceDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ltv_teams_avg_attendance(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reports-ltv-teams-avg-attendance-index"', $html);
        $start = strpos($html, 'id="reports-ltv-teams-avg-attendance-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-teams-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/reports/ltv/teams', $chunk);
        $this->assertStringContainsString('#ltv-teams-table', $chunk);
        $this->assertStringContainsString('Ср. посещаемость', $chunk);
        $this->assertStringContainsString('avg_attendance', $chunk);
        $this->assertStringContainsString('team_schedule_slot_id', $chunk);
        $this->assertStringContainsString('occurrence_date', $chunk);
        $this->assertStringContainsString("code = attended", $chunk);
        $this->assertStringContainsString('ROUND(SUM(headcount) / COUNT(*), 0)', $chunk);
        $this->assertStringContainsString('Оплаченный месяц', $chunk);
        $this->assertStringContainsString('Без группы', $chunk);
        $this->assertStringContainsString("order: [[4, 'desc']]", $chunk);
        $this->assertStringContainsString('TeamAverageAttendanceAggregator', $chunk);
        $this->assertStringContainsString('LtvTeamsReportFeatureTest', $chunk);
        $this->assertStringContainsString('ReportsLtvTeamsAvgAttendanceDocumentationContractTest', $chunk);
        $this->assertStringContainsString('reports-admin#ltv-teams', $chunk);
        $this->assertStringContainsString('/doc#reports-ltv-teams-avg-attendance-index', $html);
    }

    public function test_related_docs_and_live_code_match_avg_attendance_contract(): void
    {
        $reports = $this->docFile('reports-admin.html');
        $membership = $this->docFile('student-team-membership.html');
        $controllerTitles = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('avg_attendance', $reports);
        $this->assertStringContainsString('Ср. посещаемость', $reports);
        $this->assertStringContainsString('/doc#reports-ltv-teams-avg-attendance-index', $reports);
        $this->assertStringContainsString('ReportsLtvTeamsAvgAttendanceDocumentationContractTest', $reports);
        $this->assertStringContainsString('TeamAverageAttendanceAggregator', $reports);

        $this->assertStringContainsString('Ср. посещаемость', $membership);
        $this->assertStringContainsString('/doc#reports-ltv-teams-avg-attendance-index', $membership);

        $this->assertStringContainsString('столбец «Ср. посещаемость»', $controllerTitles);

        $aggregator = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Reports/TeamAverageAttendanceAggregator.php');
        $this->assertStringContainsString('ROUND(SUM(headcount) / COUNT(*), 0)', $aggregator);
        $this->assertStringContainsString('attendedIdForPartner', $aggregator);
        $this->assertStringContainsString('COUNT(DISTINCT e.user_id)', $aggregator);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/Report/LtvTeamsReportController.php');
        $this->assertStringContainsString('TeamAverageAttendanceAggregator', $controller);
        $this->assertStringContainsString('avg_attendance', $controller);
        $this->assertStringContainsString('resolveAttendanceYearMonth', $controller);

        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/report/ltv_teams.blade.php');
        $this->assertStringContainsString('<th>Ср. посещаемость</th>', $blade);
        $this->assertStringContainsString("key: 'avg_attendance'", $blade);
        $this->assertStringContainsString("name: 'avg_attendance', searchable: false", $blade);
        $this->assertStringContainsString("order: [[4, 'desc']]", $blade);
        $this->assertTrue(
            strpos($blade, "name: 'user_names'") < strpos($blade, "key: 'avg_attendance'")
        );
        $this->assertTrue(
            strpos($blade, "key: 'avg_attendance'") < strpos($blade, "name: 'total_price', searchable: false")
        );
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
