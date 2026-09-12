<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * JS-контракт UX: оплаченный месяц без абона — селект доступен, цена не из каталога,
 * apply/save шлёт lesson_package_id, 422 под селектом.
 * Два пути «По ученикам»: карточка и «Применить» за год.
 *
 * Падает, если селект включают только при уже назначенном абоне,
 * или если paid+пусто пропускают в payload.
 */
final class SettingPricesPaidEmptyPrepaidAttachJsContractTest extends TestCase
{
    public function test_monthly_paid_without_package_keeps_select_enabled_and_price_disabled(): void
    {
        $js = $this->settingsPricesJs();
        $render = $this->renderUsersSource($js);

        $formerPos = strpos($render, 'if (!isFormer)');
        $this->assertNotFalse($formerPos);
        $afterFormer = substr($render, $formerPos, 700);
        $this->assertStringContainsString("packageSelectDisabled = ''", $afterFormer);
        $enablePos = strpos($afterFormer, "packageSelectDisabled = ''");
        $this->assertNotFalse($enablePos);
        $beforeEnable = substr($afterFormer, 0, $enablePos);
        $this->assertStringNotContainsString('hasAbon', $beforeEnable);
        $this->assertStringNotContainsString('eff', $beforeEnable);

        $this->assertStringContainsString('} else if (!eff && hasAbon && !canManage)', $render);
        $this->assertStringContainsString("priceInputDisabled = ''", $render);
    }

    public function test_monthly_package_change_does_not_fill_catalog_price_when_paid(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "$(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select'");
        $this->assertNotFalse($start);
        $end = strpos($js, "$(document).on('change', '#left_bar .setting-prices-team-package-select'");
        $this->assertNotFalse($end);
        $handler = substr($js, $start, $end - $start);

        $guardPos = strpos($handler, 'if (pkg && !isPaid)');
        $valPos = strpos($handler, '$priceInput.val(formatPriceValue(payableRubAfterUserDiscount(pkg.price, previewPct)))');
        $this->assertNotFalse($guardPos);
        $this->assertNotFalse($valPos);
        $this->assertLessThan($valPos, $guardPos);
    }

    public function test_monthly_right_apply_sends_package_for_paid_cards_including_empty_abon(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, 'function buildRightApplyPayloadFromDom');
        $this->assertNotFalse($start);
        $end = strpos($js, 'function postManualPaid');
        $this->assertNotFalse($end);
        $fn = substr($js, $start, $end - $start);

        $this->assertStringContainsString('lesson_package_id: lessonPackageId', $fn);
        $this->assertStringContainsString("data-is-former-member') === '1'", $fn);
        $this->assertStringNotContainsString('data-effective-paid', $fn);
        $this->assertStringNotContainsString('data-abon-established', $fn);
        $this->assertStringContainsString('disabled select/input всё равно отдаёт .value', $fn);
    }

    public function test_monthly_left_snapshot_posts_package_without_skipping_paid_students(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "url: '/admin/setting-prices/set-team-price'");
        $this->assertNotFalse($start);
        $chunk = substr($js, $start, 700);
        $this->assertStringContainsString('lesson_package_id: parseInt(packageId, 10)', $chunk);
        $this->assertStringNotContainsString('effective_is_paid', $chunk);
        $this->assertStringNotContainsString('effectivePaid', $chunk);
    }

    public function test_monthly_apply_maps_package_422_under_select(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "$('#set-price-all-users').on('click'");
        $this->assertNotFalse($start);
        $end = strpos($js, '(function initMonthProlong()');
        $this->assertNotFalse($end);
        $chunk = substr($js, $start, $end - $start);

        $this->assertStringContainsString('buildRightApplyPayloadFromDom', $js);
        $this->assertStringContainsString('/^usersPrice\\.(\\d+)\\.lesson_package_id$/', $chunk);
        $this->assertStringContainsString("showMonthlyCardFieldError($(card), 'lesson_package_id'", $chunk);
        $errorPos = strpos($chunk, 'error: function');
        $this->assertNotFalse($errorPos);
        $errorChunk = substr($chunk, $errorPos);
        $this->assertStringNotContainsString('loadTeamUsersRightColumn', $errorChunk);
        $this->assertStringNotContainsString('renderUsersRightColumn', $errorChunk);
    }

    public function test_users_tab_both_save_paths_send_package_on_paid_empty_month(): void
    {
        $blade = $this->usersBlade();

        $this->assertStringContainsString("const packageDisabledAttr = isFormer ? 'disabled' : ''", $blade);
        $this->assertStringContainsString('if (!select.value)', $blade);
        $this->assertStringContainsString('} else if (!isPaid && pkgPrice != null && pkgPrice !== \'\')', $blade);

        $cardStart = strpos($blade, 'function saveUserMonthPrice');
        $this->assertNotFalse($cardStart);
        $cardEnd = strpos($blade, 'function loadUserYearPrices');
        $this->assertNotFalse($cardEnd);
        $card = substr($blade, $cardStart, $cardEnd - $cardStart);
        $this->assertStringContainsString("url: '/admin/setting-prices/user-year-prices/save'", $card);
        $this->assertStringContainsString('lesson_package_id: pkgVal !== \'\' ? parseInt(pkgVal, 10) : null', $card);
        $this->assertStringContainsString("errs['prices.0.lesson_package_id']", $card);
        $this->assertStringContainsString('.setting-prices-monthly-package-error', $card);
        $this->assertStringNotContainsString('if (effectivePaid) { return; }', $card);
        $this->assertStringNotContainsString('if (!pkgVal)', $card);

        $saveAllStart = strpos($blade, "$('#save-user-year-prices').on('click'");
        $this->assertNotFalse($saveAllStart);
        $saveAllEnd = strpos($blade, '        })();', $saveAllStart);
        $this->assertNotFalse($saveAllEnd);
        $saveAll = substr($blade, $saveAllStart, $saveAllEnd - $saveAllStart);
        $this->assertStringContainsString('lesson_package_id: pkgVal !== \'\' ? parseInt(pkgVal, 10) : null', $saveAll);
        $this->assertStringContainsString("prices.' + i + '.lesson_package_id", $saveAll);
        $this->assertStringNotContainsString('effective_is_paid', $saveAll);
        $this->assertStringNotContainsString('if (effectivePaid)', $saveAll);
    }

    public function test_former_member_still_cannot_change_package_on_either_tab(): void
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
