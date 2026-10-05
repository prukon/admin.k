<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class SettingPricesAlphabetSortDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_alphabet_when_order_by_is_empty(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="list-sort"', $html);
        $this->assertStringContainsString('order_by', $html);
        $this->assertStringContainsString('NULL', $html);
        $this->assertStringContainsString('По месяцам', $html);
        $this->assertStringContainsString('По ученикам', $html);
        $this->assertStringContainsString('по названию', $html);
        $this->assertStringContainsString('по фамилии', $html);
        $this->assertStringContainsString('Ё', $html);
        $this->assertStringContainsString('get-team-price', $html);
        $this->assertStringContainsString('SettingPricesAlphabetSortFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesAlphabetSortDocumentationContractTest', $html);
        $this->assertStringContainsString('/doc#setting-prices-alphabet-sort-index', $html);

        $start = strpos($html, 'id="list-sort"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="cabinet-academic-year"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('/admin/setting-prices/users', $chunk);
        $this->assertStringContainsString('teams.order_by', $chunk);
        $this->assertStringContainsString('без учёта регистра', $chunk);
        $this->assertStringContainsString('затем по имени', $chunk);
        $this->assertStringContainsString('Текущие и бывшие', $chunk);
        $this->assertStringContainsString('Селект группы', $chunk);
    }

    public function test_doc_index_announces_alphabet_sort(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-alphabet-sort-index"', $html);
        $start = strpos($html, 'id="setting-prices-alphabet-sort-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="platform-payments-methods-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/setting-prices/monthly', $chunk);
        $this->assertStringContainsString('/admin/setting-prices/users', $chunk);
        $this->assertStringContainsString('order_by', $chunk);
        $this->assertStringContainsString('NULL', $chunk);
        $this->assertStringContainsString('по фамилии', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#list-sort', $chunk);
        $this->assertStringContainsString('SettingPricesAlphabetSortFeatureTest', $chunk);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
