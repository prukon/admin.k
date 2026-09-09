<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class SettingPricesMonthlyTeamSelectDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_team_select_loading_and_tests(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('wrap-team--active', $html);
        $this->assertStringContainsString('wrap-team--loading', $html);
        $this->assertStringContainsString('get-team-price', $html);
        $this->assertStringContainsString('плашку не ставить, правую колонку очистить', $html);
        $this->assertStringContainsString('200</code> + <code>success: false', $html);
        $this->assertStringContainsString('SettingPricesMonthlyTeamSelectAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyTeamSelectAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyTeamSelectNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyTeamSelectMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyTeamSelectJsContractTest', $html);
        $this->assertStringContainsString('GetTeamPriceRequest', $html);
        $this->assertStringContainsString('errors.selectedDate', $html);
        $this->assertStringContainsString('Укажите месяц.', $html);
        $this->assertStringContainsString('Team not found', $html);
        $this->assertStringContainsString('keepActiveHighlight', $html);
        $this->assertStringContainsString('/doc#setting-prices-monthly-team-select-index', $html);
        $this->assertStringContainsString('id="team-select"', $html);
        $this->assertStringContainsString('без спиннера у селекта', $html);
        $this->assertStringContainsString('Нет текущих и нет бывших', $html);
        $this->assertStringContainsString('Только бывшие с <code>price_cents', $html);
    }

    public function test_doc_index_announces_team_select_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-monthly-team-select-index"', $html);
        $start = strpos($html, 'id="setting-prices-monthly-team-select-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="setting-prices-monthly-manual-paid-card-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('POST /admin/setting-prices/get-team-price', $chunk);
        $this->assertStringContainsString('setPrices.view', $chunk);
        $this->assertStringContainsString('openTeamDetail', $chunk);
        $this->assertStringContainsString('loadTeamUsersRightColumn', $chunk);
        $this->assertStringContainsString('wrap-team--active', $chunk);
        $this->assertStringContainsString('wrap-team--loading', $chunk);
        $this->assertStringContainsString('без</b> спиннера у селекта абонемента', $chunk);
        $this->assertStringContainsString('setting-prices-users-placeholder', $chunk);
        $this->assertStringContainsString('keepActiveHighlight', $chunk);
        $this->assertStringContainsString('GetTeamPriceRequest', $chunk);
        $this->assertStringContainsString('errors.teamId', $chunk);
        $this->assertStringContainsString('errors.selectedDate', $chunk);
        $this->assertStringContainsString('Укажите группу.', $chunk);
        $this->assertStringContainsString('Укажите месяц.', $chunk);
        $this->assertStringContainsString('Team not found', $chunk);
        $this->assertStringContainsString('success: false', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#team-select', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyTeamSelectJsContractTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyTeamSelectDocumentationContractTest', $chunk);
        $this->assertStringContainsString('вкладка «По ученикам»', $chunk);
        $this->assertStringContainsString('нет текущих и нет бывших', $chunk);
        $this->assertStringContainsString('при ошибке перезагрузки', $chunk);

        $this->assertStringContainsString('плашка группы только вместе со списком учеников', $html);

        $this->assertStringNotContainsString('спиннер у селекта абонемента слева', $chunk);
        $this->assertStringNotContainsString('плашка сразу при клике', $chunk);
        $this->assertStringNotContainsString('вкладка «По ученикам» этот loading использует', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
        $this->assertStringNotContainsString('setting-prices-team-loading', $chunk);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('плашка группы только вместе со списком учеников', $controller);

        $js = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/settings-prices.js');
        $openStart = strpos($js, 'function openTeamDetail');
        $this->assertNotFalse($openStart);
        $openEnd = strpos($js, 'function effectivePaidFromUserPrice', $openStart);
        $open = substr($js, $openStart, $openEnd - $openStart);
        $this->assertStringNotContainsString("rowEl.classList.add('wrap-team--active')", $open);
        $this->assertStringNotContainsString('setting-prices-team-loading', $js);
        $this->assertStringContainsString("showRightColumnPlaceholder('loading')", $js);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
