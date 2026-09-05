<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class SettingPricesMonthlyProlongDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_prolongation(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('Ручная пролонгация месяца', $html);
        $this->assertStringContainsString('id="month-prolong"', $html);
        $this->assertStringContainsString('setting-prices.prolong-month.preview', $html);
        $this->assertStringContainsString('setting-prices.prolong-month.apply', $html);
        $this->assertStringContainsString('MonthlyPricesProlongService', $html);
        $this->assertStringContainsString('pricing.month_prolonged', $html);
        $this->assertStringContainsString('Вкладка «По ученикам» кнопку <b>не</b> показывает', $html);
        $this->assertStringContainsString('текущая</b> цена шаблона', $html);
        $this->assertStringContainsString('SettingPricesMonthlyProlongFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyProlongMarkupFeatureTest', $html);
        $this->assertStringContainsString('setting_prices_monthly_prolong_vite_module_ux_contract', $html);
        $this->assertStringContainsString('GET/PATCH/DELETE на POST-рутах', $html);
        $this->assertStringContainsString('confirm <code>disabled</code>', $html);
        $this->assertStringContainsString('skip_reasons', $html);
        $this->assertStringContainsString('students</code> / <code>teams', $html);
        $this->assertStringContainsString('partials.ui.tooltip-hint', $html);
        $this->assertStringContainsString('setting-prices-prolong-skip-hint-tpl', $html);
        $this->assertStringContainsString('В сентябре не установлены абонементы', $html);
        $this->assertStringContainsString('summaryMessage', $html);
        $this->assertStringContainsString('cell-edit-modal', $html);
        $this->assertStringContainsString('Modal.hide', $html);
    }

    public function test_doc_index_announces_month_prolong(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-month-prolong-index"', $html);
        $start = strpos($html, 'id="setting-prices-month-prolong-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="platform-payments-methods-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('setting-prices-monthly-users#month-prolong', $chunk);
        $this->assertStringContainsString('Пролонгировать на следующий месяц', $chunk);
        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('setPrices.view', $chunk);
        $this->assertStringContainsString('is_enabled=1', $chunk);
        $this->assertStringContainsString('students</code> / <code>teams', $chunk);
        $this->assertStringContainsString('без общего <code>count</code>', $chunk);
        $this->assertStringContainsString('В сентябре не установлены абонементы', $chunk);
        $this->assertStringContainsString('summaryMessage', $chunk);
        $this->assertStringContainsString('Modal.hide', $chunk);
        $this->assertStringContainsString('errors.selectedDate', $chunk);
        $this->assertStringContainsString('initMonthProlong', $chunk);
        $this->assertStringContainsString('pricing.month_prolonged', $chunk);
        $this->assertStringContainsString('lessonPackages.type.postpay', $chunk);
        $this->assertStringContainsString('SettingPricesMonthlyProlongFeatureTest', $chunk);
        $this->assertStringContainsString('setting_prices_monthly_prolong_vite_module_ux_contract', $chunk);
        $this->assertStringContainsString('fixed-lesson-package-auto-prolong-index', $chunk);
        $this->assertStringNotContainsString('cron копирует абонементы', $chunk);
        $this->assertStringNotContainsString('вкладка «По ученикам» кнопку показывает', $chunk);

        $this->assertStringContainsString('ручная пролонгация месяца', $html);
        $this->assertStringContainsString('id="fixed-lesson-package-auto-prolong-index"', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
