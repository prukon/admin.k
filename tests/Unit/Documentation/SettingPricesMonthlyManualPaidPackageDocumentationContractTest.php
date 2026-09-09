<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#setting-prices-monthly-manual-paid-card-index и §4.0
 * совпадают с POST /admin/setting-prices/manual-paid (карточка «По месяцам»).
 */
final class SettingPricesMonthlyManualPaidPackageDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_manual_paid_applying_package(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="manual-paid-card"', $html);
        $this->assertStringContainsString('lesson_package_id', $html);
        $this->assertStringContainsString('mode=paid', $html);
        $this->assertStringContainsString('applyUnpaidUserPriceRow', $html);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidAppliesPackageFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidPackageAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidPackageAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidPackageNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidPackageMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidPackageJsContractTest', $html);
        $this->assertStringContainsString('test_setting_prices_monthly_manual_paid_sends_card_package_and_price_ux_contract', $html);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $html);
        $this->assertStringContainsString('до флажка оплаты', $html);
        $this->assertStringContainsString('JSON.stringify(payload)', $html);
        $this->assertStringContainsString('GET/PATCH/DELETE', $html);
        $this->assertStringContainsString('/doc#setting-prices-monthly-manual-paid-card-index', $html);
        $this->assertStringContainsString('.manual-paid-error', $html);
        $this->assertStringContainsString('поля карточки не применяет', $html);
    }

    public function test_doc_index_announces_manual_paid_card_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-monthly-manual-paid-card-index"', $html);
        $start = strpos($html, 'id="setting-prices-monthly-manual-paid-card-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-tables-sticky-header-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('POST /admin/setting-prices/manual-paid', $chunk);
        $this->assertStringContainsString('setPrices.manualPaid.manage', $chunk);
        $this->assertStringContainsString('setPrices.view', $chunk);
        $this->assertStringContainsString('postManualPaid', $chunk);
        $this->assertStringContainsString('resources/js/settings-prices.js', $chunk);
        $this->assertStringContainsString('lesson_package_id', $chunk);
        $this->assertStringContainsString('JSON.stringify(payload)', $chunk);
        $this->assertStringContainsString("if (mode === 'paid')", $chunk);
        $this->assertStringContainsString('mode=unpaid', $chunk);
        $this->assertStringContainsString('applyUnpaidUserPriceRow', $chunk);
        $this->assertStringContainsString('is_manual_paid', $chunk);
        $this->assertStringContainsString('effective_is_paid', $chunk);
        $this->assertStringContainsString('errors.lesson_package_id', $chunk);
        $this->assertStringContainsString('errors.price', $chunk);
        $this->assertStringContainsString('errors.comment', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-price-error', $chunk);
        $this->assertStringContainsString('.manual-paid-error', $chunk);
        $this->assertStringContainsString('/admin/setting-prices/users', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#manual-paid-card', $chunk);
        $this->assertStringContainsString('custom-payments/{id}/manual-paid', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidAppliesPackageFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyManualPaidPackageDocumentationContractTest', $chunk);
        $this->assertStringContainsString('поля карточки <b>не</b> применяются', $chunk);
        $this->assertStringContainsString('Авто <code>is_paid</code> по-прежнему <b>не</b> перезаписывается', $chunk);

        $this->assertStringNotContainsString('cron копирует абонементы', $chunk);
        $this->assertStringNotContainsString('вкладка «По ученикам» шлёт абонемент из карточки', $chunk);
        $this->assertStringNotContainsString('перезаписывает is_paid', $chunk);
        $this->assertStringNotContainsString('Apply справа после оплаты меняет пакет', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);

        $this->assertStringContainsString('абонемент и сумма из карточки при ручной оплате', $html);
    }

    public function test_related_docs_and_controller_title_link_announcement(): void
    {
        $monthly = $this->docFile('setting-prices-monthly-users.html');
        $reports = $this->docFile('reports-admin.html');
        $partners = $this->docFile('partners-permissions.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/settings-prices.js');

        $this->assertStringContainsString('/doc#setting-prices-monthly-manual-paid-card-index', $monthly);
        $this->assertStringContainsString('/doc#setting-prices-monthly-manual-paid-card-index', $reports);
        $this->assertStringContainsString('/doc#setting-prices-monthly-manual-paid-card-index', $partners);
        $this->assertStringContainsString('ручная оплата с абонементом/суммой из карточки', $controller);

        $fnStart = strpos($js, 'function postManualPaid');
        $this->assertNotFalse($fnStart);
        $fn = substr($js, $fnStart, 2500);
        $this->assertStringContainsString("if (mode === 'paid')", $fn);
        $this->assertStringContainsString('payload.lesson_package_id', $fn);
        $this->assertStringContainsString('JSON.stringify(payload)', $fn);
        $this->assertStringContainsString("showMonthlyCardFieldError(\$card, 'lesson_package_id'", $js);
        $this->assertStringContainsString('errs.comment', $js);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
