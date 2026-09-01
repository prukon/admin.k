<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Документация окна селекта «По месяцам» совпадает с контроллером и blade.
 */
final class SettingPricesMonthlySelectWindowDocumentationContractTest extends TestCase
{
    public function test_setting_prices_monthly_users_doc_describes_select_window_and_clamp(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="cabinet-academic-year"', $html);
        $this->assertStringContainsString('#single-select-date', $html);
        $this->assertStringContainsString('сентября 2025', $html);
        $this->assertStringContainsString('август 2027', $html);
        $this->assertStringContainsString('24 месяца', $html);
        $this->assertStringContainsString('MONTHLY_SELECT_MIN_DATE', $html);
        $this->assertStringContainsString('monthlySelectWindow', $html);
        $this->assertStringContainsString('prices_last_month', $html);
        $this->assertStringContainsString('update-date', $html);
        $this->assertStringContainsString('SettingPricesMonthlySelectWindowFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlySelectWindowJsContractTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlySelectWindowDocumentationContractTest', $html);
    }

    public function test_doc_index_mentions_monthly_select_window_not_any_month(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('24 месяца с сентября 2025 по август 2027', $html);
        $this->assertStringContainsString('раньше сентября 2025 сбрасывается на начало окна', $html);
        $this->assertStringNotContainsString('вкладка «По месяцам» — любой месяц в селекте', $html);
    }

    public function test_controller_and_monthly_blade_match_documented_window(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/SettingPricesController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/SettingPrices/monthly.blade.php');
        $index = (string) file_get_contents($root.'/resources/views/admin/SettingPrices/index.blade.php');

        $this->assertStringContainsString("MONTHLY_SELECT_MIN_DATE = '2025-09-01'", $controller);
        $this->assertStringContainsString('MONTHLY_SELECT_MONTH_COUNT = 24', $controller);
        $this->assertStringContainsString('function monthlySelectWindow()', $controller);
        $this->assertStringContainsString('function clampMonthStringToMonthlySelect', $controller);
        $this->assertStringContainsString("'monthlySelectStartYear'", $controller);
        $this->assertStringContainsString("'monthlySelectStartMonthIndex'", $controller);
        $this->assertStringContainsString("'monthlySelectMonthCount'", $controller);

        $this->assertStringContainsString('data-start-year="{{ (int) $monthlySelectStartYear }}"', $blade);
        $this->assertStringContainsString('dataset.startYear', $blade);
        $this->assertStringNotContainsString('const startYear = 2024', $blade);

        $this->assertStringContainsString("'monthlySelectStartYear' =>", $index);
        $this->assertStringContainsString("'monthlySelectMonthCount' =>", $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
