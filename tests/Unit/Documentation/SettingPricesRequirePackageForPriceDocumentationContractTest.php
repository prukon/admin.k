<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class SettingPricesRequirePackageForPriceDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_require_package_for_price(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="require-package-for-price"', $html);
        $this->assertStringContainsString('Нельзя установить цену без абонемента.', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPositivePrice', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceJsContractTest', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPositivePriceTest', $html);
        $this->assertStringContainsString('test_setting_prices_require_package_for_price_ux_contract', $html);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceDocumentationContractTest', $html);
        $this->assertStringContainsString('/doc#setting-prices-require-package-for-price-index', $html);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $html);
        $this->assertStringContainsString('Ручная отметка оплаты', $html);
        $this->assertStringContainsString('Выберите абонемент для группы.', $html);
        $this->assertStringContainsString('весь POST не пишется', $html);
        $this->assertStringNotContainsString('цену без абонемента по-прежнему можно сохранить', $html);
    }

    public function test_doc_index_announces_require_package_for_price(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-require-package-for-price-index"', $html);
        $start = strpos($html, 'id="setting-prices-require-package-for-price-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-contract-phone-mask-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('/admin/setting-prices/users', $chunk);
        $this->assertStringContainsString('SetPriceAllUsersRequest', $chunk);
        $this->assertStringContainsString('SaveUserYearPricesRequest', $chunk);
        $this->assertStringContainsString('SettingPricesRequirePackageForPositivePrice', $chunk);
        $this->assertStringContainsString('usersPrice.N.lesson_package_id', $chunk);
        $this->assertStringContainsString('prices.N.lesson_package_id', $chunk);
        $this->assertStringContainsString('Нельзя установить цену без абонемента.', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $chunk);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesRequirePackageForPriceJsContractTest', $chunk);
        $this->assertStringContainsString('test_setting_prices_require_package_for_price_ux_contract', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#require-package-for-price', $chunk);
        $this->assertStringContainsString('Ручную отметку оплаты это не трогает', $chunk);
        $this->assertStringContainsString('X-Requested-With', $chunk);
        $this->assertStringContainsString('setPrices.view', $chunk);
        $this->assertStringContainsString('setting-prices-paid-empty-prepaid-attach-index', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-manual-paid-card-index', $chunk);
        $this->assertStringContainsString('Выберите абонемент для группы.', $chunk);
        $this->assertStringContainsString('валит <b>весь</b> POST', $chunk);
        $this->assertStringContainsString('effective_is_paid', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);

        $this->assertStringContainsString('цену без абонемента ставить нельзя', $html);
    }

    public function test_controller_blurb_mentions_require_package(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('цену без абонемента ставить нельзя', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
