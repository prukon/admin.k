<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#setting-prices-monthly-former-charge-clear-index и §3.2.1
 * совпадают с POST /admin/setting-prices/former-month-charge/clear.
 */
final class SettingPricesMonthlyFormerChargeClearDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_former_charge_clear(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="former-charge-clear"', $html);
        $this->assertStringContainsString('can_clear_former_charge', $html);
        $this->assertStringContainsString('former_charge_clear_block_reason', $html);
        $this->assertStringContainsString('user-price-former-clear', $html);
        $this->assertStringContainsString('kids-tooltip-hint', $html);
        $this->assertStringContainsString('showConfirmDeleteModal', $html);
        $this->assertStringContainsString('.former-clear-error', $html);
        $this->assertStringContainsString('/admin/setting-prices/former-month-charge/clear', $html);
        $this->assertStringContainsString('ClearFormerMemberMonthChargeRequest', $html);
        $this->assertStringContainsString('Не указан ученик.', $html);
        $this->assertStringContainsString('Выберите группу.', $html);
        $this->assertStringContainsString('Укажите месяц.', $html);
        $this->assertStringContainsString('errors.charge', $html);
        $this->assertStringContainsString('GET/PATCH/PUT/DELETE', $html);
        $this->assertStringContainsString('setPrices.view', $html);
        $this->assertStringContainsString('manualPaid.manage', $html);
        $this->assertStringContainsString('pricing.former_charge_cleared', $html);
        $this->assertStringContainsString('Этот абонемент был аннулирован', $html);
        $this->assertStringContainsString('Абонемент аннулирован', $html);
        $this->assertStringContainsString('Начисление снято.', $html);
        $this->assertStringContainsString('Удаление начисления', $html);
        $this->assertStringContainsString('keepActiveHighlight', $html);
        $this->assertStringContainsString('users.blade.php', $html);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearJsContractTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearDocumentationContractTest', $html);
        $this->assertStringContainsString('test_setting_prices_monthly_former_charge_clear_ux_contract', $html);
        $this->assertStringContainsString('test_setting_prices_monthly_vite_module_former_members_ajax_handlers_have_valid_javascript_syntax', $html);
        $this->assertStringContainsString('UserPricePublicPayFeatureTest', $html);
        $this->assertStringContainsString('/doc#setting-prices-monthly-former-charge-clear-index', $html);
        $this->assertStringContainsString('Постоплата без посещений', $html);
        $this->assertStringContainsString('только у ученика, который больше не состоит', $html);
        $this->assertStringNotContainsString('не дают их редактировать', $html);
    }

    public function test_doc_index_announces_former_charge_clear_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-monthly-former-charge-clear-index"', $html);
        $start = strpos($html, 'id="setting-prices-monthly-former-charge-clear-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-tbank-payments-without-payout-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('POST <code>/admin/setting-prices/former-month-charge/clear</code>', $chunk);
        $this->assertStringContainsString('setPrices.view', $chunk);
        $this->assertStringContainsString('setPrices.manualPaid.manage', $chunk);
        $this->assertStringContainsString('user-price-former-clear', $chunk);
        $this->assertStringContainsString('can_clear_former_charge', $chunk);
        $this->assertStringContainsString('former_charge_clear_block_reason', $chunk);
        $this->assertStringContainsString('kids-tooltip-hint', $chunk);
        $this->assertStringContainsString('showConfirmDeleteModal', $chunk);
        $this->assertStringContainsString('.former-clear-error', $chunk);
        $this->assertStringContainsString('ClearFormerMemberMonthChargeRequest', $chunk);
        $this->assertStringContainsString('errors.charge', $chunk);
        $this->assertStringContainsString('Не указан ученик.', $chunk);
        $this->assertStringContainsString('Выберите группу.', $chunk);
        $this->assertStringContainsString('Укажите месяц.', $chunk);
        $this->assertStringContainsString('GET/PATCH/PUT/DELETE', $chunk);
        $this->assertStringContainsString('users.blade.php', $chunk);
        $this->assertStringContainsString('Этот абонемент был аннулирован', $chunk);
        $this->assertStringContainsString('Абонемент аннулирован', $chunk);
        $this->assertStringContainsString('Начисление снято.', $chunk);
        $this->assertStringContainsString('Удаление начисления', $chunk);
        $this->assertStringContainsString('keepActiveHighlight', $chunk);
        $this->assertStringContainsString('pricing.former_charge_cleared', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#former-charge-clear', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearJsContractTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyFormerChargeClearDocumentationContractTest', $chunk);
        $this->assertStringContainsString('test_setting_prices_monthly_former_charge_clear_ux_contract', $chunk);
        $this->assertStringContainsString('вкладка «По ученикам»', $chunk);
        $this->assertStringContainsString('resources/js/settings-prices.js', $chunk);

        $this->assertStringNotContainsString('npm run build', $chunk);
        $this->assertStringNotContainsString('вкладка «По ученикам» показывает корзину', $chunk);
        $this->assertStringNotContainsString('нужно setPrices.manualPaid.manage', $chunk);
        $this->assertStringNotContainsString('оплаченное — disabled-корзина', $chunk);

        $this->assertStringContainsString('корзина неоплаченного начисления бывшего', $html);

        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('корзина неоплаченного начисления бывшего', $controller);
    }

    public function test_related_docs_and_js_match_announcement(): void
    {
        $monthly = $this->docFile('setting-prices-monthly-users.html');
        $payments = $this->docFile('payments.html');
        $membership = $this->docFile('student-team-membership.html');
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/settings-prices.js');

        $this->assertStringContainsString('/doc#setting-prices-monthly-former-charge-clear-index', $monthly);
        $this->assertStringContainsString('Этот абонемент был аннулирован', $payments);
        $this->assertStringContainsString('Абонемент аннулирован', $payments);
        $this->assertStringContainsString('kind=annulled', $payments);
        $this->assertStringContainsString('setting-prices-monthly-users#former-charge-clear', $payments);
        $this->assertStringContainsString('/doc#setting-prices-monthly-former-charge-clear-index', $payments);
        $this->assertStringContainsString('setting-prices-monthly-users#former-charge-clear', $membership);
        $this->assertStringContainsString('/doc#setting-prices-monthly-former-charge-clear-index', $membership);

        $notifications = $this->docFile('setting-prices-payment-notifications.html');
        $this->assertStringContainsString('setting-prices-monthly-users#former-charge-clear', $notifications);
        $this->assertStringContainsString('/doc#setting-prices-monthly-former-charge-clear-index', $notifications);

        $this->assertStringContainsString('user-price-former-clear', $js);
        $this->assertStringContainsString('/admin/setting-prices/former-month-charge/clear', $js);
        $this->assertStringContainsString('can_clear_former_charge', $js);
        $this->assertStringContainsString('showConfirmDeleteModal', $js);
        $this->assertStringContainsString('errs.charge', $js);
        $this->assertStringContainsString("if (isFormer && !eff && uid)", $js);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
