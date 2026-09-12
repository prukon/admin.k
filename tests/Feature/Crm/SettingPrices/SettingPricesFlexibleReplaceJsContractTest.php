<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * JS-контракт UX-бага: у оплаченного месяца селект абонемента доступен,
 * смена select не подставляет каталожную цену, 422 рисуется под селектом.
 * Два пути: Vite «По месяцам» и inline «По ученикам» (карточка + «Применить» за год).
 *
 * Падает на коде до фикса (packageSelectDisabled = eff ? 'disabled' : ''
 * и $priceInput.val(каталог) без проверки isPaid).
 */
final class SettingPricesFlexibleReplaceJsContractTest extends TestCase
{
    public function test_monthly_paid_current_member_keeps_package_select_enabled_and_price_disabled(): void
    {
        $js = $this->settingsPricesJs();
        $render = $this->renderUsersSource($js);

        $this->assertStringContainsString("packageSelectDisabled = ''", $render);
        $this->assertStringNotContainsString("packageSelectDisabled = eff ? 'disabled' : ''", $render);
        $this->assertStringContainsString("if (!isFormer)", $render);
        $this->assertStringContainsString("} else if (\$card.attr('data-effective-paid') === '1')", $js);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $render);
        $this->assertStringContainsString('data-effective-paid', $render);

        $formerPos = strpos($render, "if (!isFormer)");
        $this->assertNotFalse($formerPos);
        $afterFormer = substr($render, $formerPos, 900);
        $this->assertStringContainsString("packageSelectDisabled = ''", $afterFormer);
        $this->assertStringContainsString('priceInputDisabled', $afterFormer);
    }

    public function test_monthly_package_change_does_not_overwrite_paid_price_but_does_for_unpaid(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($start, 'change handler for monthly package select missing');
        $end = strpos($js, "$(document).on('change', '#left_bar .setting-prices-team-package-select'");
        $this->assertNotFalse($end);
        $handler = substr($js, $start, $end - $start);

        $this->assertStringContainsString('if (pkg && !isPaid)', $handler);
        $this->assertStringContainsString('$priceInput.val(formatPriceValue(payableRubAfterUserDiscount(pkg.price, previewPct)))', $handler);
        $paidGuardPos = strpos($handler, 'if (pkg && !isPaid)');
        $valPos = strpos($handler, '$priceInput.val(formatPriceValue(payableRubAfterUserDiscount(pkg.price, previewPct)))');
        $this->assertNotFalse($paidGuardPos);
        $this->assertNotFalse($valPos);
        $this->assertLessThan($valPos, $paidGuardPos, 'Каталожная цена должна писаться только внутри !isPaid');

        $this->assertStringContainsString("} else if (\$card.attr('data-effective-paid') === '1')", $handler);
        $this->assertStringContainsString("\$priceInput.prop('disabled', true)", $handler);
        $this->assertStringContainsString("data-is-former-member') === '1'", $handler);
    }

    public function test_monthly_apply_maps_package_422_under_select_and_does_not_rerender_column(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "$('#set-price-all-users').on('click'");
        $this->assertNotFalse($start);
        $end = strpos($js, '(function initMonthProlong()');
        $this->assertNotFalse($end);
        $chunk = substr($js, $start, $end - $start);

        $this->assertStringContainsString("url: '/admin/setting-prices/set-price-all-users'", $chunk);
        $this->assertStringContainsString('error: function', $chunk);
        $this->assertStringContainsString('/^usersPrice\\.(\\d+)\\.lesson_package_id$/', $chunk);
        $this->assertStringContainsString("showMonthlyCardFieldError($(card), 'lesson_package_id'", $chunk);
        $this->assertStringContainsString('buildRightApplyPayloadFromDom', $js);
        $this->assertStringContainsString('disabled select/input всё равно отдаёт .value', $js);

        $errorPos = strpos($chunk, 'error: function');
        $this->assertNotFalse($errorPos);
        $errorChunk = substr($chunk, $errorPos);
        $this->assertStringNotContainsString('loadTeamUsersRightColumn', $errorChunk);
        $this->assertStringNotContainsString('renderUsersRightColumn', $errorChunk);
    }

    public function test_users_tab_paid_month_keeps_select_enabled_and_does_not_fill_catalog_price(): void
    {
        $blade = $this->usersBlade();

        $this->assertStringContainsString("const packageDisabledAttr = isFormer ? 'disabled' : ''", $blade);
        $this->assertStringNotContainsString("effectivePaid ? 'disabled'", $blade);
        $this->assertStringContainsString('if (!select.value)', $blade);
        $this->assertStringContainsString('} else if (!isPaid && pkgPrice != null && pkgPrice !== \'\')', $blade);

        $changeStart = strpos($blade, "$('#user-prices-table-wrapper').on('change', '.setting-prices-monthly-package-select'");
        $this->assertNotFalse($changeStart);
        $changeEnd = strpos($blade, "$('#user-prices-table-wrapper').on('input change', '.user-price-input'");
        $this->assertNotFalse($changeEnd);
        $change = substr($blade, $changeStart, $changeEnd - $changeStart);
        $emptyPos = strpos($change, 'if (!select.value)');
        $fillPos = strpos($change, '} else if (!isPaid && pkgPrice != null && pkgPrice !== \'\')');
        $valPos = strpos($change, '$input.val(formatPriceValue(payableRubAfterUserDiscount(pkgPrice, pct)))');
        $this->assertNotFalse($emptyPos);
        $this->assertNotFalse($fillPos);
        $this->assertNotFalse($valPos);
        $this->assertLessThan($fillPos, $emptyPos);
        $this->assertLessThan($valPos, $fillPos);
    }

    public function test_users_tab_card_save_and_save_all_both_send_package_including_paid_months(): void
    {
        $blade = $this->usersBlade();

        $cardStart = strpos($blade, 'function saveUserMonthPrice');
        $this->assertNotFalse($cardStart);
        $cardEnd = strpos($blade, 'function loadUserYearPrices');
        $this->assertNotFalse($cardEnd);
        $card = substr($blade, $cardStart, $cardEnd - $cardStart);
        $this->assertStringContainsString("url: '/admin/setting-prices/user-year-prices/save'", $card);
        $this->assertStringContainsString('lesson_package_id: pkgVal !== \'\' ? parseInt(pkgVal, 10) : null', $card);
        $this->assertStringContainsString("Для оплаченного месяца сумму не меняем, но абонемент предоплаты можно заменить.", $card);
        $this->assertStringContainsString('.setting-prices-monthly-package-error', $card);
        $this->assertStringContainsString("errs['prices.0.lesson_package_id']", $card);
        $this->assertStringNotContainsString('if (effectivePaid) { return; }', $card);

        $saveAllStart = strpos($blade, "$('#save-user-year-prices').on('click'");
        $this->assertNotFalse($saveAllStart, 'save-all path missing — второй JS-вход на ту же форму');
        $saveAllEnd = strpos($blade, '        })();', $saveAllStart);
        $this->assertNotFalse($saveAllEnd);
        $saveAll = substr($blade, $saveAllStart, $saveAllEnd - $saveAllStart);
        $this->assertStringContainsString("url: '/admin/setting-prices/user-year-prices/save'", $saveAll);
        $this->assertStringContainsString("\$('#user-prices-table-wrapper .user-price-input').each", $saveAll);
        $this->assertStringContainsString('lesson_package_id: pkgVal !== \'\' ? parseInt(pkgVal, 10) : null', $saveAll);
        $this->assertStringContainsString("prices.' + i + '.lesson_package_id", $saveAll);
        $this->assertStringContainsString('.setting-prices-monthly-package-error', $saveAll);
        $this->assertStringNotContainsString('effective_is_paid', $saveAll);
        $this->assertStringNotContainsString('if (effectivePaid)', $saveAll);
    }

    public function test_former_member_package_select_stays_disabled_on_both_tabs(): void
    {
        $js = $this->settingsPricesJs();
        $render = $this->renderUsersSource($js);
        $this->assertStringContainsString("let packageSelectDisabled = 'disabled'", $render);
        $this->assertStringContainsString('if (!isFormer)', $render);

        $blade = $this->usersBlade();
        $this->assertStringContainsString("const packageDisabledAttr = isFormer ? 'disabled' : ''", $blade);
        $this->assertStringContainsString("if (lastPricesPayload && lastPricesPayload.is_former_member)", $blade);
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
