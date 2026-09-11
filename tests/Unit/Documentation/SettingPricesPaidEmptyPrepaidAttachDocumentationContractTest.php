<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#setting-prices-paid-empty-prepaid-attach-index и §ulp-sync
 * совпадают с постановкой предоплаты на оплаченный месяц без абонемента.
 */
final class SettingPricesPaidEmptyPrepaidAttachDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_paid_empty_prepaid_attach(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="ulp-sync"', $html);
        $this->assertStringContainsString('пусто → предоплата', $html);
        $this->assertStringContainsString('/doc#setting-prices-paid-empty-prepaid-attach-index', $html);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachAccessFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachMarkupFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachJsContractTest', $html);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachDocumentationContractTest', $html);
        $this->assertStringContainsString('test_setting_prices_paid_empty_prepaid_attach_ux_contract', $html);
        $this->assertStringContainsString('Нельзя снять абонемент у оплаченного месяца.', $html);
        $this->assertStringContainsString('только абонемент предоплаты', $html);
        $this->assertStringContainsString('usersPrice.{i}.lesson_package_id', $html);
        $this->assertStringContainsString('applyPaidUserPricePackageChange', $html);
        $this->assertStringContainsString('оплаченный без абона + предоплата', $html);
        $this->assertStringContainsString('пусто→предоплата на оплаченном', $html);
        $this->assertStringNotContainsString('поставить пакет в оплаченный месяц без предоплаты', $html);
        $this->assertStringNotContainsString('селект абонемента у оплаченного месяца disabled', $html);
        $this->assertStringNotContainsString('кроме предоплата→предоплата', $html);
    }

    public function test_doc_index_announces_paid_empty_attach_and_lists_tests(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-paid-empty-prepaid-attach-index"', $html);
        $start = strpos($html, 'id="setting-prices-paid-empty-prepaid-attach-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-tbank-payments-summary-confirmed-default-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('пусто → flexible', $chunk);
        $this->assertStringContainsString('effective_is_paid', $chunk);
        $this->assertStringContainsString('lessons_remaining = lessons_total', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-package-error', $chunk);
        $this->assertStringContainsString('X-Requested-With', $chunk);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachJsContractTest', $chunk);
        $this->assertStringContainsString('SettingPricesPaidEmptyPrepaidAttachDocumentationContractTest', $chunk);
        $this->assertStringContainsString('test_setting_prices_paid_empty_prepaid_attach_ux_contract', $chunk);
        $this->assertStringNotContainsString('npm run build', $chunk);
        $this->assertStringNotContainsString('Снимок тарифа слева оплаченный не-prepaid по-прежнему пропускает молча', $html);
        $this->assertStringContainsString('предоплата на оплаченный месяц без абонемента', $html);
        $this->assertStringContainsString('set-price-all-teams', $chunk);
        $this->assertStringContainsString('lessonPackages.type.flexible', $chunk);
    }

    public function test_controller_and_related_pages_link_attach_announcement(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $payments = $this->docFile('payments.html');
        $journal = $this->docFile('schedule-journal.html');
        $packages = $this->docFile('lesson-packages.html');

        $this->assertStringContainsString('предоплата на оплаченный месяц без абонемента', $controller);
        $this->assertStringContainsString('/doc#setting-prices-paid-empty-prepaid-attach-index', $payments);
        $this->assertStringContainsString('/doc#setting-prices-paid-empty-prepaid-attach-index', $journal);
        $this->assertStringContainsString('/doc#setting-prices-paid-empty-prepaid-attach-index', $packages);
        $this->assertStringContainsString('существующие ячейки журнала не привязываются', $packages);
        $this->assertStringContainsString('пробные и разовые ячейки остаются своими', $journal);
        $this->assertStringContainsString('сумму не меняет', $payments);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
