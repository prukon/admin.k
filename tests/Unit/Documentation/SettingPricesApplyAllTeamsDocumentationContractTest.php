<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#setting-prices-apply-all-teams-index:
 * скрытое setPrices.applyAllTeams.manage прячет массовое «Применить» слева.
 */
final class SettingPricesApplyAllTeamsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_apply_all_teams_permission(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-apply-all-teams-index"', $html);
        $start = strpos($html, 'id="setting-prices-apply-all-teams-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="student-team-detach-unpaid-charge-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('setPrices.applyAllTeams.manage', $chunk);
        $this->assertStringContainsString('set-price-all-teams', $chunk);
        $this->assertStringContainsString('is_visible = 0', $chunk);
        $this->assertStringContainsString('<b>POST</b> <code>/admin/setting-prices/set-price-all-teams</code>', $chunk);
        $this->assertStringContainsString('SettingPricesApplyAllTeamsMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesApplyAllTeamsAccessFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/setting-prices-monthly-users#apply-all-teams', $chunk);
    }

    public function test_monthly_users_doc_describes_apply_all_teams_permission(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="apply-all-teams"', $html);
        $this->assertStringContainsString('setPrices.applyAllTeams.manage', $html);
        $this->assertStringContainsString('id="set-price-all-teams"', $html);
        $this->assertStringContainsString('/admin/setting-prices/set-price-all-teams', $html);
        $this->assertStringContainsString('SettingPricesApplyAllTeamsMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesApplyAllTeamsAccessFeatureTest', $html);
        $this->assertStringContainsString('/doc#setting-prices-apply-all-teams-index', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
