<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#student-team-detach-unpaid-charge-index и §1.3
 * совпадают со снятием неоплаченного абонемента при уходе из группы.
 */
final class StudentTeamDetachUnpaidChargeDocumentationContractTest extends TestCase
{
    public function test_membership_doc_describes_unpaid_clear_on_team_leave(): void
    {
        $html = $this->docFile('student-team-membership.html');

        $this->assertStringContainsString('id="detach-unpaid-charge"', $html);
        $this->assertStringContainsString('syncTeamsForStudent', $html);
        $this->assertStringContainsString('clearUnpaidOnTeamLeave', $html);
        $this->assertStringContainsString('/schedule/user/{user}/sync-teams', $html);
        $this->assertStringContainsString('pricing.former_charge_cleared', $html);
        $this->assertStringContainsString('detachTeamFromAllStudents', $html);
        $this->assertStringContainsString('/doc#student-team-detach-unpaid-charge-index', $html);
        $this->assertStringContainsString('SettingPricesTeamDetachUnpaidChargeFeatureTest', $this->docFile('index.html'));
    }

    public function test_doc_index_announces_unpaid_clear_on_team_leave(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="student-team-detach-unpaid-charge-index"', $html);
        $start = strpos($html, 'id="student-team-detach-unpaid-charge-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="ops-till-rejected-intent-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('syncTeamsForStudent', $chunk);
        $this->assertStringContainsString('POST /schedule/user/{user}/sync-teams', $chunk);
        $this->assertStringContainsString('pricing.former_charge_cleared', $chunk);
        $this->assertStringContainsString('detachTeamFromAllStudents', $chunk);
        $this->assertStringContainsString('SettingPricesTeamDetachUnpaidChargeFeatureTest', $chunk);
        $this->assertStringContainsString('StudentTeamDetachUnpaidChargeDocumentationContractTest', $chunk);
        $this->assertStringContainsString('student-team-membership#detach-unpaid-charge', $chunk);
    }

    public function test_monthly_users_doc_points_at_team_leave_clear(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('student-team-membership#detach-unpaid-charge', $html);
        $this->assertStringContainsString('/doc#student-team-detach-unpaid-charge-index', $html);
        $this->assertStringNotContainsString('можно только корзиной', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
