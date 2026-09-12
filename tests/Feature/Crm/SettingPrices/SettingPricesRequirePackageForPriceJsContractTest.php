<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * UI: поле цены закрыто без абонемента; снятие абона обнуляет сумму;
 * 422 под селектом. Vite «По месяцам» (Применить + карандаш) и inline «По ученикам»
 * (карточка + «Применить» за год).
 *
 * Падает на коде до фикса: `!canManage || !hasAbon` / `!canManual || !hasAbon`
 * открывали цену без пакета; снятие абона не обнуляло сумму.
 */
final class SettingPricesRequirePackageForPriceJsContractTest extends TestCase
{
    public function test_monthly_price_stays_disabled_without_package_on_first_render(): void
    {
        $js = $this->settingsPricesJs();
        $render = $this->renderUsersSource($js);

        $this->assertStringContainsString('} else if (isEditing && !eff && hasAbon)', $render);
        $this->assertStringContainsString('} else if (!eff && hasAbon && !canManage)', $render);
        $this->assertStringNotContainsString('!canManage || !hasAbon', $render);

        $disabledPos = strpos($render, "let priceInputDisabled = 'disabled'");
        $editEnable = strpos($render, '} else if (isEditing && !eff && hasAbon)');
        $this->assertNotFalse($disabledPos);
        $this->assertNotFalse($editEnable);
        $this->assertLessThan($editEnable, $disabledPos);
    }

    public function test_monthly_package_change_zeros_unpaid_price_and_does_not_touch_paid(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($start);
        $end = strpos($js, "$(document).on('change', '#left_bar .setting-prices-team-package-select'");
        $this->assertNotFalse($end);
        $handler = substr($js, $start, $end - $start);

        $this->assertStringContainsString('} else if (!pkg && !isPaid)', $handler);
        $zeroPos = strpos($handler, '$priceInput.val(formatPriceValue(0))');
        $this->assertNotFalse($zeroPos);
        $unpaidGuard = strpos($handler, '} else if (!pkg && !isPaid)');
        $this->assertNotFalse($unpaidGuard);
        $this->assertLessThan($zeroPos, $unpaidGuard);

        $this->assertStringContainsString('} else if (!pkg)', $handler);
        $this->assertStringContainsString('$priceInput.prop(\'disabled\', true)', $handler);

        $catalogGuard = strpos($handler, 'if (pkg && !isPaid)');
        $catalogVal = strpos($handler, '$priceInput.val(formatPriceValue(payableRubAfterUserDiscount(pkg.price, previewPct)))');
        $this->assertNotFalse($catalogGuard);
        $this->assertNotFalse($catalogVal);
        $this->assertLessThan($catalogVal, $catalogGuard);
    }

    public function test_monthly_apply_all_maps_package_422_under_select_without_rebuild(): void
    {
        $js = $this->settingsPricesJs();
        $applyStart = strpos($js, "$('#set-price-all-users').on('click'");
        $this->assertNotFalse($applyStart);
        $applyEnd = strpos($js, '(function initMonthProlong()');
        $this->assertNotFalse($applyEnd);
        $apply = substr($js, $applyStart, $applyEnd - $applyStart);
        $this->assertStringContainsString('/^usersPrice\\.(\\d+)\\.lesson_package_id$/', $apply);
        $this->assertStringContainsString("showMonthlyCardFieldError($(card), 'lesson_package_id'", $apply);

        $errorPos = strpos($apply, 'error: function');
        $this->assertNotFalse($errorPos);
        $errorChunk = substr($apply, $errorPos);
        $this->assertStringNotContainsString('loadTeamUsersRightColumn', $errorChunk);
        $this->assertStringNotContainsString('renderUsersRightColumn', $errorChunk);
    }

    public function test_monthly_pencil_accept_maps_package_422_under_select_without_rebuild(): void
    {
        $js = $this->settingsPricesJs();
        $acceptStart = strpos($js, "$(document).on('click', '#right_bar .wrap-users .user-price-edit-accept'");
        $this->assertNotFalse($acceptStart);
        $acceptEnd = strpos($js, "$(document).on('change', '#right_bar .wrap-users .user-manual-paid-select'");
        $this->assertNotFalse($acceptEnd);
        $accept = substr($js, $acceptStart, $acceptEnd - $acceptStart);

        $this->assertStringContainsString("url: '/admin/setting-prices/set-price-all-users'", $accept);
        $this->assertStringContainsString('lesson_package_id: pkgVal !== \'\' ? parseInt(pkgVal, 10) : null', $accept);
        $this->assertStringContainsString("errs['usersPrice.0.lesson_package_id']", $accept);
        $this->assertStringContainsString('showMonthlyCardFieldError($card, \'lesson_package_id\'', $accept);
        $this->assertStringContainsString('clearMonthlyCardFieldErrors($card)', $accept);

        $errorPos = strpos($accept, 'error: function');
        $this->assertNotFalse($errorPos);
        $errorChunk = substr($accept, $errorPos);
        $this->assertStringNotContainsString('renderUsersRightColumn', $errorChunk);
        $this->assertStringContainsString('renderUsersRightColumn', $accept);
        $successPos = strpos($accept, 'success: function');
        $this->assertNotFalse($successPos);
        $this->assertLessThan($errorPos, $successPos);
        $this->assertLessThan(
            $errorPos,
            strpos($accept, 'renderUsersRightColumn')
        );
    }

    public function test_users_tab_locks_price_without_package_on_first_card_render(): void
    {
        $blade = $this->usersBlade();
        $this->assertStringContainsString('if (!isFormer && !effectivePaid && hasAbon && !canManual)', $blade);
        $this->assertStringContainsString('data-abon-established', $blade);
        $this->assertStringNotContainsString('!canManual || !hasAbon', $blade);
    }

    public function test_users_tab_package_change_zeros_unpaid_and_does_not_zero_paid(): void
    {
        $blade = $this->usersBlade();
        $start = strpos($blade, "$('#user-prices-table-wrapper').on('change', '.setting-prices-monthly-package-select'");
        $this->assertNotFalse($start);
        $end = strpos($blade, "$('#user-prices-table-wrapper').on('input change', '.user-price-input'");
        $this->assertNotFalse($end);
        $handler = substr($blade, $start, $end - $start);

        $emptyPos = strpos($handler, 'if (!select.value)');
        $this->assertNotFalse($emptyPos);
        $paidGuard = strpos($handler, 'if (!isPaid)', $emptyPos);
        $zeroPos = strpos($handler, '$input.val(formatPriceValue(0))', $emptyPos);
        $this->assertNotFalse($paidGuard);
        $this->assertNotFalse($zeroPos);
        $this->assertLessThan($zeroPos, $paidGuard);

        $catalogPos = strpos($handler, '} else if (!isPaid && pkgPrice != null && pkgPrice !== \'\')');
        $this->assertNotFalse($catalogPos);
        $this->assertGreaterThan($emptyPos, $catalogPos);
    }

    public function test_users_tab_card_save_sends_package_and_maps_422_under_select(): void
    {
        $blade = $this->usersBlade();
        $cardStart = strpos($blade, 'function saveUserMonthPrice');
        $this->assertNotFalse($cardStart);
        $cardEnd = strpos($blade, 'function loadUserYearPrices');
        $this->assertNotFalse($cardEnd);
        $card = substr($blade, $cardStart, $cardEnd - $cardStart);

        $this->assertStringContainsString("url: '/admin/setting-prices/user-year-prices/save'", $card);
        $this->assertStringContainsString('lesson_package_id: pkgVal !== \'\' ? parseInt(pkgVal, 10) : null', $card);
        $this->assertStringContainsString("errs['prices.0.lesson_package_id']", $card);
        $this->assertStringContainsString('.setting-prices-monthly-package-error', $card);
        $this->assertStringNotContainsString('if (!pkgVal)', $card);
    }

    public function test_users_tab_year_apply_sends_package_and_maps_422_under_select(): void
    {
        $blade = $this->usersBlade();
        $saveAllStart = strpos($blade, "$('#save-user-year-prices').on('click'");
        $this->assertNotFalse($saveAllStart);
        $saveAllEnd = strpos($blade, '        })();', $saveAllStart);
        $this->assertNotFalse($saveAllEnd);
        $saveAll = substr($blade, $saveAllStart, $saveAllEnd - $saveAllStart);

        $this->assertStringContainsString("url: '/admin/setting-prices/user-year-prices/save'", $saveAll);
        $this->assertStringContainsString('lesson_package_id: pkgVal !== \'\' ? parseInt(pkgVal, 10) : null', $saveAll);
        $this->assertStringContainsString("prices.' + i + '.lesson_package_id", $saveAll);
        $this->assertStringContainsString('.setting-prices-monthly-package-error', $saveAll);
        $this->assertStringNotContainsString('if (!pkgVal)', $saveAll);
    }

    private function settingsPricesJs(): string
    {
        $path = resource_path('js/settings-prices.js');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function usersBlade(): string
    {
        $path = resource_path('views/admin/SettingPrices/users.blade.php');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function renderUsersSource(string $js): string
    {
        $start = strpos($js, 'function renderUsersRightColumn');
        $this->assertNotFalse($start);
        $end = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($end);

        return substr($js, $start, $end - $start);
    }
}
