<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * UX-баг: плашка wrap-team--active рисовалась в openTeamDetail до ответа get-team-price.
 * После фикса плашка только в success вместе с правой колонкой; между кликом и ответом — loading.
 *
 * Падает на коде до фикса (rowEl.classList.add('wrap-team--active') внутри openTeamDetail).
 *
 * @see /docs/documentation/setting-prices-monthly-users.html
 */
final class SettingPricesMonthlyTeamSelectJsContractTest extends TestCase
{
    public function test_clicking_group_does_not_paint_plaque_before_ajax(): void
    {
        $open = $this->functionSource('openTeamDetail');

        $this->assertStringContainsString('loadTeamUsersRightColumn(rowEl.id)', $open);
        $this->assertStringNotContainsString("rowEl.classList.add('wrap-team--active')", $open);
        $this->assertStringNotContainsString('lastTeamId = rowEl.id', $open);
        $this->assertStringNotContainsString('clearTeamRowHighlight', $open);
    }

    public function test_loading_placeholder_starts_before_ajax_and_plaque_after_success(): void
    {
        $load = $this->functionSource('loadTeamUsersRightColumn');

        $loadingPos = strpos($load, "showRightColumnPlaceholder('loading')");
        $this->assertNotFalse($loadingPos);
        $ajaxPos = strpos($load, '$.ajax(');
        $this->assertNotFalse($ajaxPos);
        $this->assertLessThan(
            $ajaxPos,
            $loadingPos,
            'Плейсхолдер «Загрузка…» должен появиться до $.ajax'
        );

        $setLoadingPos = strpos($load, 'setTeamRowLoading(rowEl)');
        $this->assertNotFalse($setLoadingPos);
        $this->assertLessThan($ajaxPos, $setLoadingPos);
        $this->assertStringNotContainsString('setting-prices-team-loading', $load);
        $this->assertStringNotContainsString('spinner-border', $load);

        $successPos = strpos($load, 'success: function');
        $errorPos = strpos($load, 'error: function');
        $this->assertNotFalse($successPos);
        $this->assertNotFalse($errorPos);
        $success = substr($load, $successPos, $errorPos - $successPos);

        $applyPos = strpos($success, 'applyTeamRowActive(teamId)');
        $renderPos = strpos($success, 'renderUsersRightColumn');
        $this->assertNotFalse($applyPos);
        $this->assertNotFalse($renderPos);
        $this->assertLessThan(
            $renderPos,
            $applyPos,
            'Плашка должна ставиться в том же success, что и отрисовка учеников'
        );
        $this->assertStringContainsString('wrap-team--active', $this->functionSource('applyTeamRowActive'));
    }

    public function test_ajax_error_clears_right_column_and_does_not_paint_plaque(): void
    {
        $load = $this->functionSource('loadTeamUsersRightColumn');
        $errorPos = strpos($load, 'error: function');
        $this->assertNotFalse($errorPos);
        $error = substr($load, $errorPos);

        $this->assertStringContainsString("status === 'abort'", $error);
        $this->assertStringContainsString("showRightColumnPlaceholder", $error);
        $this->assertStringContainsString("'error'", $error);
        $this->assertStringContainsString('Не удалось загрузить учеников группы.', $error);
        $this->assertStringContainsString('clearTeamRowHighlight', $error);
        $this->assertStringContainsString('lastTeamId = null', $error);
        $this->assertStringNotContainsString('applyTeamRowActive', $error);
        $this->assertStringNotContainsString('renderUsersRightColumn', $error);
    }

    public function test_empty_group_success_false_still_paints_plaque(): void
    {
        $load = $this->functionSource('loadTeamUsersRightColumn');
        $successPos = strpos($load, 'success: function');
        $errorPos = strpos($load, 'error: function');
        $success = substr($load, $successPos, $errorPos - $successPos);

        $this->assertStringContainsString('if (response.success)', $success);
        $falseBranch = substr($success, (int) strpos($success, '// Пустая группа'));
        $this->assertStringContainsString("renderUsersRightColumn([], [],", $falseBranch);
        $applyBeforeIf = strpos($success, 'applyTeamRowActive(teamId)');
        $ifSuccess = strpos($success, 'if (response.success)');
        $this->assertNotFalse($applyBeforeIf);
        $this->assertNotFalse($ifSuccess);
        $this->assertLessThan(
            $ifSuccess,
            $applyBeforeIf,
            'Пустая группа (success:false) тоже получает плашку до ветки if (response.success)'
        );
    }

    public function test_apply_refresh_keeps_plaque_and_shows_loading_only_on_the_right(): void
    {
        $js = $this->settingsPricesJs();
        $this->assertStringContainsString(
            'loadTeamUsersRightColumn(lastTeamId, { keepActiveHighlight: true })',
            $js
        );

        $load = $this->functionSource('loadTeamUsersRightColumn');
        $keepPos = strpos($load, 'if (!keepActiveHighlight)');
        $this->assertNotFalse($keepPos);
        $keepChunk = substr($load, $keepPos, 450);
        $this->assertStringContainsString('setTeamRowLoading(rowEl)', $keepChunk);
        $this->assertStringContainsString('clearTeamRowLoading()', $keepChunk);
        $elsePos = strpos($keepChunk, '} else {');
        $this->assertNotFalse($elsePos);
        $elseChunk = substr($keepChunk, $elsePos);
        $this->assertStringContainsString('clearTeamRowLoading()', $elseChunk);
        $this->assertStringNotContainsString('setTeamRowLoading(rowEl)', $elseChunk);
        $this->assertStringNotContainsString('clearTeamRowHighlight()', $elseChunk);
    }

    public function test_users_tab_still_paints_plaque_on_click(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringContainsString("row.addClass('wrap-team--active')", $blade);
        $this->assertStringNotContainsString('wrap-team--loading', $blade);
        $this->assertStringNotContainsString('keepActiveHighlight', $blade);
    }

    public function test_settings_prices_js_has_valid_syntax(): void
    {
        $path = resource_path('js/settings-prices.js');
        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/settings-prices.js:\n".implode("\n", $output)
        );
    }

    private function settingsPricesJs(): string
    {
        $path = resource_path('js/settings-prices.js');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function functionSource(string $name): string
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, 'function '.$name.'(');
        $this->assertNotFalse($start, $name.' missing');
        $next = strpos($js, "\n    function ", $start + 10);
        $this->assertNotFalse($next, 'next function after '.$name.' missing');

        return substr($js, $start, $next - $start);
    }
}
