<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Сумма месяца с is_paid = 1 не меняется после ручного «Не оплачено».
 */
final class SettingPricesAcquiringAmountLockDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_acquiring_amount_lock(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-acquiring-amount-lock-index"', $html);
        $start = strpos($html, 'id="setting-prices-acquiring-amount-lock-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="payment-notifications-parent-email-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('users_prices.is_paid = 1', $chunk);
        $this->assertStringContainsString('is_manual_paid = 0', $chunk);
        $this->assertStringContainsString('price_cents', $chunk);
        $this->assertStringContainsString('UserPrice::ACQUIRING_AMOUNT_LOCKED_MESSAGE', $chunk);
        $this->assertStringContainsString('Нельзя изменить сумму: месяц уже оплачен через платёжную систему.', $chunk);
        $this->assertStringContainsString('UserPrice::amountIsFrozen()', $chunk);
        $this->assertStringContainsString('POST /admin/setting-prices/manual-paid', $chunk);
        $this->assertStringContainsString('POST /admin/setting-prices/set-price-all-users', $chunk);
        $this->assertStringContainsString('POST /admin/setting-prices/user-year-prices/save', $chunk);
        $this->assertStringContainsString('errors.usersPrice.N.price', $chunk);
        $this->assertStringContainsString('errors.prices.N.price', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $chunk);
        $this->assertStringContainsString('setPrices.manualPaid.manage', $chunk);
        $this->assertStringContainsString('Карандаш не рисуется', $chunk);
        $this->assertStringContainsString('user-price-manual-edit', $chunk);
        $this->assertStringContainsString('is_paid = 0', $chunk);
        $this->assertStringContainsString('SettingPricesAcquiringAmountLockFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesAcquiringAmountLockDocumentationContractTest', $chunk);
        $this->assertStringContainsString('test_setting_prices_acquiring_amount_stays_locked_after_manual_unpaid_ux_contract', $chunk);
        $this->assertStringContainsString('/doc#setting-prices-acquiring-amount-lock-index', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#acquiring-amount-lock', $chunk);
    }

    public function test_monthly_users_and_related_docs_describe_the_lock(): void
    {
        $monthly = $this->docFile('setting-prices-monthly-users.html');
        $payments = $this->docFile('payments.html');
        $postpay = $this->docFile('postpay.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="acquiring-amount-lock"', $monthly);
        $this->assertStringContainsString('user-price-manual-edit', $monthly);
        $this->assertStringContainsString('Карандаш', $monthly);
        $this->assertStringContainsString('Нельзя изменить сумму: месяц уже оплачен через платёжную систему.', $monthly);
        $this->assertStringContainsString('data-acquiring-paid="1"', $monthly);
        $this->assertStringContainsString('UserPrice::amountIsFrozen()', $monthly);
        $this->assertStringContainsString('/doc#setting-prices-acquiring-amount-lock-index', $monthly);
        $this->assertStringContainsString('SettingPricesAcquiringAmountLockFeatureTest', $monthly);

        $this->assertStringContainsString('/doc#setting-prices-acquiring-amount-lock-index', $payments);
        $this->assertStringContainsString('amountIsFrozen', $postpay);
        $this->assertStringContainsString('сумма месяца с is_paid=1 не меняется после ручного «Не оплачено»', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
