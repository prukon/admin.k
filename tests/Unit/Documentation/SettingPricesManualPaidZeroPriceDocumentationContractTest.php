<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * §4.0.1 и анонс /doc#setting-prices-manual-paid-zero-price-index:
 * mode=paid при 0 ₽ запрещён; журнал/долги по-прежнему требуют price_cents > 0.
 */
final class SettingPricesManualPaidZeroPriceDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_zero_price_manual_paid_rule(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="manual-paid-zero-price"', $html);
        $this->assertStringContainsString('Нельзя отметить оплату при стоимости 0 ₽.', $html);
        $this->assertStringContainsString('mode=paid</code> при итоговых', $html);
        $this->assertStringContainsString('mode=unpaid</code> при 0 ₽ можно', $html);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceJsContractTest', $html);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceDocumentationContractTest', $html);
        $this->assertStringContainsString('test_setting_prices_monthly_manual_paid_zero_price_error_under_price_field_ux_contract', $html);
        $this->assertStringContainsString('test_setting_prices_users_tab_manual_paid_zero_price_error_under_price_field_ux_contract', $html);
        $this->assertStringContainsString('/doc#setting-prices-manual-paid-zero-price-index', $html);
        $this->assertStringContainsString('/doc#setting-prices-monthly-manual-paid-card-index', $html);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $html);
        $this->assertStringContainsString('под строкой карточки', $html);
        $this->assertStringContainsString('селект оплаты не уезжает вправо', $html);
        $this->assertStringContainsString('errors.price', $html);
        $this->assertStringContainsString('SetManualUserPricePaidRequest', $html);
        $this->assertStringContainsString('price_cents &gt; 0', $html);
        $this->assertStringNotContainsString('журнал рисует галочку при 0 ₽ и ручной оплате', $html);
    }

    public function test_doc_index_has_dedicated_zero_price_announcement(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-manual-paid-zero-price-index"', $html);
        $start = strpos($html, 'id="setting-prices-manual-paid-zero-price-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="journal-team-filter-select2-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('/admin/setting-prices/users', $chunk);
        $this->assertStringContainsString('POST /admin/setting-prices/manual-paid', $chunk);
        $this->assertStringContainsString('setPrices.manualPaid.manage', $chunk);
        $this->assertStringContainsString('setPrices.view', $chunk);
        $this->assertStringContainsString('Нельзя отметить оплату при стоимости 0 ₽.', $chunk);
        $this->assertStringContainsString('mode=unpaid</code> при 0 ₽ можно', $chunk);
        $this->assertStringContainsString('SetManualUserPricePaidRequest', $chunk);
        $this->assertStringContainsString('ZERO_PRICE_MESSAGE', $chunk);
        $this->assertStringContainsString('postManualPaid', $chunk);
        $this->assertStringContainsString('postManualPaidForUser', $chunk);
        $this->assertStringContainsString('payload.price', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $chunk);
        $this->assertStringContainsString('под всей строкой', $chunk);
        $this->assertStringContainsString('селект оплаты не сдвигается', $chunk);
        $this->assertStringContainsString('errors.price', $chunk);
        $this->assertStringContainsString('price_cents &gt; 0', $chunk);
        $this->assertStringContainsString('hasAbon', $chunk);
        $this->assertStringContainsString('custom-payments/{id}/manual-paid', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-manual-paid-card-index', $chunk);
        $this->assertStringContainsString('setting-prices-require-package-for-price-index', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#manual-paid-zero-price', $chunk);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceJsContractTest', $chunk);
        $this->assertStringContainsString('SettingPricesManualPaidZeroPriceDocumentationContractTest', $chunk);
        $this->assertStringContainsString('test_setting_prices_monthly_manual_paid_zero_price_error_under_price_field_ux_contract', $chunk);
        $this->assertStringContainsString('test_setting_prices_users_tab_manual_paid_zero_price_error_under_price_field_ux_contract', $chunk);
        $this->assertStringContainsString('X-Requested-With', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
        $this->assertStringNotContainsString('журнал рисует галочку при 0 ₽', $chunk);
        $this->assertStringNotContainsString('вкладка «По ученикам» это правило не касается', $chunk);
        $this->assertStringNotContainsString('доп. платежи тоже 422 при нуле', $chunk);

        $this->assertStringContainsString('ручную оплату при 0 ₽ ставить нельзя', $html);
        $this->assertStringContainsString('/doc#setting-prices-manual-paid-zero-price-index', $html);
    }

    public function test_card_announcement_still_points_to_zero_price_rule(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="setting-prices-monthly-manual-paid-card-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-tables-sticky-header-index"');
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Нельзя отметить оплату при стоимости 0 ₽.', $chunk);
        $this->assertStringContainsString('mode=unpaid</code> при 0 ₽ можно', $chunk);
        $this->assertStringContainsString('/doc#setting-prices-manual-paid-zero-price-index', $chunk);
        $this->assertStringContainsString('test_setting_prices_users_tab_manual_paid_zero_price_error_under_price_field_ux_contract', $chunk);
    }

    public function test_related_docs_and_controller_link_announcement(): void
    {
        $journal = $this->docFile('schedule-journal.html');
        $payments = $this->docFile('payments.html');
        $reports = $this->docFile('reports-admin.html');
        $partners = $this->docFile('partners-permissions.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#setting-prices-manual-paid-zero-price-index', $journal);
        $this->assertStringContainsString('/doc#setting-prices-manual-paid-zero-price-index', $payments);
        $this->assertStringContainsString('/doc#setting-prices-manual-paid-zero-price-index', $reports);
        $this->assertStringContainsString('/doc#setting-prices-manual-paid-zero-price-index', $partners);
        $this->assertStringContainsString('ручную оплату при 0 ₽ ставить нельзя', $controller);
        $this->assertStringContainsString('price_cents &gt; 0', $journal);
        $this->assertStringNotContainsString('журнал рисует галочку при 0 ₽ и ручной оплате', $journal);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
