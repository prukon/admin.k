<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use Tests\TestCase;

/**
 * JS-контракт UX-бага: смена абонемента в селекте, затем статус «оплачено»
 * должна уносить lesson_package_id и price из DOM карточки, а не только mode.
 *
 * Падает на коде до фикса (JSON.stringify только user_id/team_id/date/mode/comment).
 *
 * @see /docs/documentation/setting-prices-monthly-users.html#manual-paid-card
 */
final class SettingPricesMonthlyManualPaidPackageJsContractTest extends TestCase
{
    public function test_post_manual_paid_reads_package_and_price_from_card_dom_when_marking_paid(): void
    {
        $js = $this->settingsPricesJs();
        $fn = $this->postManualPaidSource($js);

        $this->assertStringContainsString("\$card.find('.setting-prices-monthly-package-select')", $fn);
        $this->assertStringContainsString("\$card.find('.setting-prices-monthly-price-input')", $fn);
        $this->assertStringContainsString("if (mode === 'paid')", $fn);
        $this->assertStringContainsString('payload.lesson_package_id = lessonPackageId', $fn);
        $this->assertStringContainsString('payload.price = price', $fn);
        $this->assertStringContainsString('packageSelect.val()', $fn);
        $this->assertStringContainsString('priceInput.val()', $fn);
        $this->assertStringContainsString('JSON.stringify(payload)', $fn);

        $paidIfPos = strpos($fn, "if (mode === 'paid')");
        $this->assertNotFalse($paidIfPos);
        $stringifyPos = strpos($fn, 'JSON.stringify(payload)');
        $this->assertNotFalse($stringifyPos);
        $this->assertLessThan(
            $stringifyPos,
            $paidIfPos,
            'Абонемент/цена должны попасть в payload до JSON.stringify'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/JSON\.stringify\(\s*\{\s*user_id:\s*userId[\s\S]*comment:\s*comment\s*\}\s*\)/',
            $fn,
            'Старый UX-баг: в AJAX уходил только статус без селекта абонемента'
        );
        $this->assertStringNotContainsString('usersPrice[idx].lesson_package_id', $fn);
    }

    public function test_unpaid_mode_does_not_send_card_package(): void
    {
        $fn = $this->postManualPaidSource($this->settingsPricesJs());

        $paidChunkStart = strpos($fn, "if (mode === 'paid')");
        $this->assertNotFalse($paidChunkStart);
        $beforePaid = substr($fn, 0, $paidChunkStart);
        $this->assertStringNotContainsString('payload.lesson_package_id', $beforePaid);
        $this->assertStringNotContainsString('payload.price = price', $beforePaid);
    }

    public function test_validation_errors_show_under_package_and_price_and_do_not_rerender_row(): void
    {
        $fn = $this->postManualPaidSource($this->settingsPricesJs());

        $failPos = strpos($fn, '.fail(function');
        $this->assertNotFalse($failPos);
        $failChunk = substr($fn, $failPos);

        $this->assertStringContainsString("errs.lesson_package_id", $failChunk);
        $this->assertStringContainsString("showMonthlyCardFieldError(\$card, 'lesson_package_id'", $failChunk);
        $this->assertStringContainsString("showMonthlyCardFieldError(\$card, 'price'", $failChunk);
        $this->assertStringNotContainsString('renderUsersRightColumn', $failChunk);

        $js = $this->settingsPricesJs();
        $this->assertStringContainsString('setting-prices-monthly-package-error', $js);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $js);
        $this->assertStringContainsString("packageSelectDisabled = eff ? 'disabled' : ''", $js);
    }

    public function test_success_replaces_row_from_server_user_price_after_paid(): void
    {
        $fn = $this->postManualPaidSource($this->settingsPricesJs());
        $donePos = strpos($fn, '.done(function');
        $this->assertNotFalse($donePos);
        $failPos = strpos($fn, '.fail(function');
        $this->assertNotFalse($failPos);
        $doneChunk = substr($fn, $donePos, $failPos - $donePos);

        $this->assertStringContainsString('res.user_price', $doneChunk);
        $this->assertStringContainsString('usersPrice[idx] = updated', $doneChunk);
        $this->assertStringContainsString('renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid)', $doneChunk);
    }

    private function settingsPricesJs(): string
    {
        $path = resource_path('js/settings-prices.js');
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
}
