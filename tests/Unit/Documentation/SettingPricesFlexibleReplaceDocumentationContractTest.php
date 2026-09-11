<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#setting-prices-flexible-replace-index и §ulp-sync
 * совпадают с заменой предоплаты в установке цен.
 */
final class SettingPricesFlexibleReplaceDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_flexible_replace_rules(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="ulp-sync"', $html);
        $this->assertStringContainsString('предоплата → предоплата', $html);
        $this->assertStringContainsString('consumes_lesson', $html);
        $this->assertStringContainsString('/doc#setting-prices-flexible-replace-index', $html);
        $this->assertStringContainsString('SettingPricesUsersPriceUlpSyncFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceJsContractTest', $html);
        $this->assertStringContainsString('test_setting_prices_flexible_replace_paid_select_ux_contract', $html);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceDocumentationContractTest', $html);
        $this->assertStringContainsString('/p/{code}', $html);
        $this->assertStringContainsString('/pm/{code}', $html);
        $this->assertStringContainsString('usersPrice.{i}.lesson_package_id', $html);
        $this->assertStringContainsString('остаётся доступным', $html);
        $this->assertStringNotContainsString('селект абонемента у оплаченного месяца disabled', $html);
        $this->assertStringNotContainsString('Оплаченный месяц (<code>effective_is_paid</code>) — sync не создаёт и не меняет ULP', $html);
    }

    public function test_doc_index_announces_flexible_replace_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-flexible-replace-index"', $html);
        $start = strpos($html, 'id="setting-prices-flexible-replace-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-tbank-payments-payout-status-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('/admin/setting-prices/users', $chunk);
        $this->assertStringContainsString('flexible → flexible', $chunk);
        $this->assertStringContainsString('effective_is_paid', $chunk);
        $this->assertStringContainsString('consumes_lesson', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $chunk);
        $this->assertStringContainsString('usersPrice.N.lesson_package_id', $chunk);
        $this->assertStringContainsString('/p/{code}', $chunk);
        $this->assertStringContainsString('/pm/{code}', $chunk);
        $this->assertStringContainsString('SettingPricesUsersPriceUlpSyncFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceJsContractTest', $chunk);
        $this->assertStringContainsString('test_setting_prices_flexible_replace_paid_select_ux_contract', $chunk);
        $this->assertStringContainsString('SettingPricesFlexibleReplaceDocumentationContractTest', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#ulp-sync', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
        $this->assertStringNotContainsString('селект абонемента у оплаченного месяца disabled', $chunk);
        $this->assertStringNotContainsString('сменить пакет в установке цен можно, пока нет UTSS', $html);
        $this->assertStringContainsString('X-Requested-With', $chunk);
        $this->assertStringContainsString('только просмотр', $chunk);

        $this->assertStringContainsString('замена предоплаты в разложенном/оплаченном месяце', $html);
    }

    public function test_payments_and_controller_link_announcement(): void
    {
        $payments = $this->docFile('payments.html');
        $journal = $this->docFile('schedule-journal.html');
        $packages = $this->docFile('lesson-packages.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/settings-prices.js');

        $this->assertStringContainsString('/doc#setting-prices-flexible-replace-index', $payments);
        $this->assertStringContainsString('/doc#setting-prices-flexible-replace-index', $journal);
        $this->assertStringContainsString('/doc#setting-prices-flexible-replace-index', $packages);
        $this->assertStringNotContainsString('сменить пакет в установке цен можно, пока нет UTSS', $journal);
        $this->assertStringContainsString('замена предоплаты в разложенном/оплаченном месяце', $controller);
        $this->assertStringContainsString("packageSelectDisabled = ''", $js);
        $this->assertStringContainsString('if (pkg && !isPaid)', $js);
        $this->assertStringNotContainsString("packageSelectDisabled = eff ? 'disabled' : ''", $js);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
