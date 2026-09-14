<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * Два JS-пути ручной оплаты при 0 ₽.
 *
 * «По месяцам» (Vite postManualPaid): при mode=paid всегда шлёт payload.price
 * (пустое поле → 0) и рисует 422 под строкой карточки, без пересборки колонки.
 * «По ученикам» (inline postManualPaidForUser): цену не шлёт; 422 — под строкой
 * месяца, toast не показывается.
 *
 * Падает на коде до фикса, где errors.price уходил в общий toast / не под строкой.
 */
final class SettingPricesManualPaidZeroPriceJsContractTest extends TestCase
{
    public function test_monthly_paid_sends_card_price_defaulting_to_zero(): void
    {
        $fn = $this->postManualPaidSource($this->settingsPricesJs());

        $this->assertStringContainsString("if (mode === 'paid')", $fn);
        $this->assertStringContainsString('let price = 0', $fn);
        $this->assertStringContainsString('payload.price = price', $fn);
        $this->assertStringContainsString("\$card.find('.setting-prices-monthly-price-input')", $fn);

        $defaultPos = strpos($fn, 'let price = 0');
        $assignPos = strpos($fn, 'payload.price = price');
        $this->assertNotFalse($defaultPos);
        $this->assertNotFalse($assignPos);
        $this->assertLessThan($assignPos, $defaultPos);

        $paidIfPos = strpos($fn, "if (mode === 'paid')");
        $stringifyPos = strpos($fn, 'JSON.stringify(payload)');
        $this->assertNotFalse($paidIfPos);
        $this->assertNotFalse($stringifyPos);
        $this->assertLessThan($stringifyPos, $assignPos);
        $this->assertLessThan($assignPos, $paidIfPos);
    }

    public function test_monthly_unpaid_does_not_send_price(): void
    {
        $fn = $this->postManualPaidSource($this->settingsPricesJs());

        $paidChunkStart = strpos($fn, "if (mode === 'paid')");
        $this->assertNotFalse($paidChunkStart);
        $beforePaid = substr($fn, 0, $paidChunkStart);
        $this->assertStringNotContainsString('payload.price = price', $beforePaid);
        $this->assertStringNotContainsString('let price = 0', $beforePaid);
    }

    public function test_monthly_zero_price_422_shows_under_price_without_rebuild(): void
    {
        $fn = $this->postManualPaidSource($this->settingsPricesJs());
        $failPos = strpos($fn, '.fail(function');
        $this->assertNotFalse($failPos);
        $failChunk = substr($fn, $failPos);

        $this->assertStringContainsString('errs.price', $failChunk);
        $this->assertStringContainsString("showMonthlyCardFieldError(\$card, 'price'", $failChunk);
        $this->assertStringNotContainsString('renderUsersRightColumn', $failChunk);
        $this->assertStringNotContainsString('loadTeamUsersRightColumn', $failChunk);

        $js = $this->settingsPricesJs();
        $this->assertStringContainsString('setting-prices-monthly-price-error', $js);
        $cardPos = strpos($js, 'const userBlock = `');
        $this->assertNotFalse($cardPos);
        $cardEnd = strpos($js, 'rightBar.append(userBlock);', $cardPos);
        $this->assertNotFalse($cardEnd);
        $card = substr($js, $cardPos, $cardEnd - $cardPos);
        $statusPos = strpos($card, 'setting-prices-monthly-status');
        $priceErrPos = strpos($card, 'setting-prices-monthly-price-error');
        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($priceErrPos);
        $this->assertLessThan($priceErrPos, $statusPos);
        $showFn = strpos($js, 'function showMonthlyCardFieldError');
        $this->assertNotFalse($showFn);
        $showChunk = substr($js, $showFn, 700);
        $this->assertStringContainsString("field === 'price'", $showChunk);
        $this->assertStringContainsString("\$card.find('.setting-prices-monthly-price-error')", $showChunk);
    }

    public function test_monthly_select_change_opens_comment_modal_then_post_manual_paid(): void
    {
        $js = $this->settingsPricesJs();
        $start = strpos($js, "$(document).on('change', '#right_bar .wrap-users .user-manual-paid-select'");
        $this->assertNotFalse($start);
        $end = strpos($js, "$(document).on('change', '#left_bar .setting-prices-team-package-select'");
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $handler = substr($js, $start, $end - $start);

        $this->assertStringContainsString('showManualPaidCommentModal', $handler);
        $this->assertStringContainsString("const mode = val === '1' ? 'paid' : 'unpaid'", $handler);
        $this->assertStringContainsString('postManualPaid(userId, lastTeamId, selectedDate, mode, comment, $card)', $handler);
    }

    public function test_users_tab_does_not_send_card_price_and_maps_422_under_price(): void
    {
        $blade = $this->usersBlade();
        $fn = $this->postManualPaidForUserSource($blade);

        $this->assertStringContainsString('/admin/setting-prices/manual-paid', $fn);
        $this->assertStringContainsString('JSON.stringify({', $fn);
        $this->assertStringContainsString('user_id: userId', $fn);
        $this->assertStringContainsString('mode: mode', $fn);
        $this->assertStringContainsString('comment: comment', $fn);
        $this->assertStringNotContainsString('payload.price', $fn);
        $this->assertStringNotContainsString('lesson_package_id', $fn);

        $failPos = strpos($fn, '.fail(function');
        $this->assertNotFalse($failPos);
        $failChunk = substr($fn, $failPos);
        $this->assertStringContainsString('errs.price', $failChunk);
        $this->assertStringContainsString('$priceErr.text(msg).show()', $failChunk);
        $this->assertStringContainsString('if (fieldShown)', $failChunk);
        $this->assertLessThan(
            strpos($failChunk, 'if (typeof onError === \'function\')'),
            strpos($failChunk, 'if (fieldShown)')
        );
        $this->assertStringNotContainsString('showToast(msg, true)', explode('if (fieldShown)', $failChunk, 2)[0]);
    }

    public function test_users_tab_select_change_opens_comment_modal_then_post_manual_paid_for_user(): void
    {
        $blade = $this->usersBlade();
        $start = strpos($blade, "$('#user-prices-table-wrapper').on('change', '.user-manual-paid-select'");
        $this->assertNotFalse($start);
        $end = strpos($blade, "$('#left_bar').on('click', '.user-row'");
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $handler = substr($blade, $start, $end - $start);

        $this->assertStringContainsString('showManualPaidCommentModal', $handler);
        $this->assertStringContainsString('postManualPaidForUser(currentUserId, currentTeamId, selectedDate, mode, comment, $tr', $handler);
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

    private function postManualPaidSource(string $js): string
    {
        $start = strpos($js, 'function postManualPaid(');
        $this->assertNotFalse($start, 'postManualPaid missing');
        $end = strpos($js, 'function clearMonthlyCardFieldErrors(', $start);
        $this->assertNotFalse($end, 'clearMonthlyCardFieldErrors missing after postManualPaid');

        return substr($js, $start, $end - $start);
    }

    private function postManualPaidForUserSource(string $blade): string
    {
        $start = strpos($blade, 'function postManualPaidForUser(');
        $this->assertNotFalse($start, 'postManualPaidForUser missing');
        $end = strpos($blade, 'function renderUserPricesTable(', $start);
        $this->assertNotFalse($end, 'renderUserPricesTable missing after postManualPaidForUser');

        return substr($blade, $start, $end - $start);
    }
}
