<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * Корзина бывшего на «По месяцам»: только unpaid former, disabled+ховер, confirm, POST JSON.
 *
 * @see /docs/documentation/setting-prices-monthly-users.html#former-charge-clear
 */
final class SettingPricesMonthlyFormerChargeClearJsContractTest extends TestCase
{
    public function test_trash_is_rendered_only_for_unpaid_former_members(): void
    {
        $render = $this->renderUsersSource();

        $this->assertStringContainsString('if (isFormer && !eff && uid)', $render);
        $this->assertStringContainsString('user-price-former-clear', $render);
        $this->assertStringContainsString('can_clear_former_charge', $render);
        $this->assertStringContainsString('former_charge_clear_block_reason', $render);
        $this->assertStringContainsString('kids-tooltip-hint', $render);
        $this->assertStringContainsString('setting-prices-monthly-clear-disabled-wrap', $render);
        $this->assertStringContainsString("canClear ? ' title=\"Удалить начисление\"' : ' disabled aria-disabled=\"true\"'", $render);
        $this->assertStringContainsString('fa-trash', $render);
        $this->assertStringContainsString('former-clear-error', $render);

        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*!isFormer\s*&&\s*!eff/',
            $render,
            'Корзина не должна рисоваться текущим членам группы'
        );
    }

    public function test_click_posts_json_after_confirm_and_shows_charge_errors(): void
    {
        $click = $this->formerClearClickSource();

        $this->assertStringContainsString("showConfirmDeleteModal", $click);
        $this->assertStringContainsString("if (\$btn.prop('disabled')", $click);
        $this->assertStringContainsString("data-is-former-member') !== '1'", $click);
        $this->assertStringContainsString('/admin/setting-prices/former-month-charge/clear', $click);
        $this->assertStringContainsString('JSON.stringify({', $click);
        $this->assertStringContainsString('user_id: parseInt(String(uid), 10)', $click);
        $this->assertStringContainsString('team_id: parseInt(String(lastTeamId), 10)', $click);
        $this->assertStringContainsString('selectedDate: selectedDate', $click);
        $this->assertStringContainsString("Accept': 'application/json'", $click);
        $this->assertStringContainsString("contentType: 'application/json'", $click);
        $this->assertStringContainsString('errs.charge', $click);
        $this->assertStringContainsString('errs.user_id', $click);
        $this->assertStringContainsString('errs.team_id', $click);
        $this->assertStringContainsString('errs.selectedDate', $click);
        $this->assertStringContainsString('.former-clear-error', $click);
        $this->assertStringContainsString("loadTeamUsersRightColumn(lastTeamId, { keepActiveHighlight: true })", $click);
        $this->assertStringContainsString("window.showToast", $click);
        $this->assertStringContainsString('Начисление снято.', $click);
    }

    public function test_users_tab_does_not_include_former_charge_clear(): void
    {
        $users = (string) file_get_contents(resource_path('views/admin/SettingPrices/users.blade.php'));
        $this->assertStringNotContainsString('user-price-former-clear', $users);
        $this->assertStringNotContainsString('former-month-charge/clear', $users);
        $this->assertStringNotContainsString('can_clear_former_charge', $users);
        $this->assertStringNotContainsString('resources/js/settings-prices.js', $users);
        $this->assertStringNotContainsString('fa-trash', $users);
    }

    public function test_former_never_gets_pencil_and_current_never_gets_trash(): void
    {
        $render = $this->renderUsersSource();

        $this->assertStringContainsString('if (!isFormer && canManage && uid && hasAbon)', $render);
        $this->assertStringContainsString('user-price-manual-edit', $render);
        $this->assertStringContainsString('pencilHtml + trashHtml', $render);

        $pencilPos = strpos($render, 'if (!isFormer && canManage && uid && hasAbon)');
        $trashPos = strpos($render, 'if (isFormer && !eff && uid)');
        $this->assertNotFalse($pencilPos);
        $this->assertNotFalse($trashPos);
        $this->assertGreaterThan($pencilPos, $trashPos);

        $this->assertStringNotContainsString('if (isFormer && uid)', $render);
        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*isFormer\s*&&\s*canManage/',
            $render,
            'У бывшего не должно быть карандаша'
        );
    }

    public function test_disabled_trash_returns_before_confirm_and_ajax(): void
    {
        $click = $this->formerClearClickSource();

        $disabledPos = strpos($click, "\$btn.prop('disabled')");
        $confirmPos = strpos($click, 'showConfirmDeleteModal');
        $ajaxPos = strpos($click, '$.ajax');
        $this->assertNotFalse($disabledPos);
        $this->assertNotFalse($confirmPos);
        $this->assertNotFalse($ajaxPos);
        $this->assertLessThan($confirmPos, $disabledPos, 'disabled должен выйти до confirm');
        $this->assertLessThan($ajaxPos, $disabledPos, 'disabled должен выйти до $.ajax');

        $this->assertStringContainsString("e.preventDefault()", $click);
        $this->assertStringContainsString('Если уже выдана ссылка на оплату, она перестанет действовать.', $click);
        $this->assertStringNotContainsString('location.reload', $click);
        $this->assertStringNotContainsString('renderUsersRightColumn', $click);
    }

    private function settingsPricesJs(): string
    {
        $path = resource_path('js/settings-prices.js');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function renderUsersSource(): string
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, 'function renderUsersRightColumn');
        $this->assertNotFalse($start, 'renderUsersRightColumn missing');
        $end = strpos($js, '$(document).on(\'click\', \'#right_bar .wrap-users .user-price-manual-edit\'', $start);
        $this->assertNotFalse($end, 'manual-edit handler missing after render');

        return substr($js, $start, $end - $start);
    }

    private function formerClearClickSource(): string
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "$(document).on('click', '#right_bar .wrap-users .user-price-former-clear'");
        $this->assertNotFalse($start, 'former-clear click handler missing');
        $end = strpos($js, 'function restoreEditingMonthlySnapshot', $start);
        $this->assertNotFalse($end, 'restoreEditingMonthlySnapshot missing after former-clear');

        return substr($js, $start, $end - $start);
    }
}
