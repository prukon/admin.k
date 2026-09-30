<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Документация шапки и персонального вида вкладки «По месяцам».
 */
final class SettingPricesMonthlyViewStateDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_per_user_view_state(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="monthly-view-state"', $html);
        $this->assertStringContainsString('setting_prices_monthly', $html);
        $this->assertStringContainsString('user_table_settings', $html);
        $this->assertStringContainsString('settingPricesMonthlyFiltersCollapse', $html);
        $this->assertStringContainsString('setting-prices.monthly-filters', $html);
        $this->assertStringContainsString('effective_is_paid', $html);
        $this->assertStringContainsString('errors.team_package', $html);
        $this->assertStringContainsString('errors.user_package', $html);
        $this->assertStringContainsString('SettingPricesMonthlyViewStateFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyViewStateDocumentationContractTest', $html);
        $this->assertStringContainsString('Пролонгация по-прежнему по всем группам партнёра', $html);
        $this->assertStringContainsString('администратор объекта', $html);
        $this->assertStringContainsString('без привязки к объектам', $html);
        $this->assertStringContainsString('locations.view', $html);
    }

    public function test_doc_index_announces_monthly_view_state(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-monthly-view-state-index"', $html);
        $this->assertStringContainsString('table_key=setting_prices_monthly', $html);
        $this->assertStringContainsString('setting-prices-monthly-users#monthly-view-state', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
