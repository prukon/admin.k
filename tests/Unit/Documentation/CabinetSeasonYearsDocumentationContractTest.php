<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#cabinet-season-years-index и dashboard-cabinet §6.1:
 * шапки сезонов на /cabinet считаются от текущего учебного года вниз до 2021–2022.
 */
final class CabinetSeasonYearsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_dynamic_cabinet_season_years(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="cabinet-season-years-index"', $html);
        $start = strpos($html, 'id="cabinet-season-years-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-contract-fill-modal-ux-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/cabinet', $chunk);
        $this->assertStringContainsString('now()-&gt;month &gt;= 9', $chunk);
        $this->assertStringContainsString('Europe/Moscow', $chunk);
        $this->assertStringContainsString('2021–2022', $chunk);
        $this->assertStringContainsString('data-season="2027"', $chunk);
        $this->assertStringContainsString('data-season="2022"', $chunk);
        $this->assertStringContainsString('id="season-2027"', $chunk);
        $this->assertStringContainsString('range($cabinetSeasonEndYear, 2022)', $chunk);
        $this->assertStringContainsString('createSeasons', $chunk);
        $this->assertStringContainsString('apendPrice', $chunk);
        $this->assertStringContainsString('showSessons', $chunk);
        $this->assertStringContainsString('users_prices', $chunk);
        $this->assertStringContainsString('календарный', $chunk);
        $this->assertStringContainsString('now()-&gt;year - 1', $chunk);
        $this->assertStringContainsString('setPrices.cabinetSeasons.view', $chunk);
        $this->assertStringContainsString('setPrices.cabinetPackages.postpay.view', $chunk);
        $this->assertStringContainsString('activeStudent', $chunk);
        $this->assertStringContainsString('setting-prices-monthly-users#cabinet-academic-year', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#cabinet-season-years', $chunk);
        $this->assertStringContainsString('parents-and-family-cabinet', $chunk);
        $this->assertStringContainsString('DashboardSeasonsSetPricesVisibilityFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetSeasonYearsFeatureTest', $chunk);
        $this->assertStringContainsString('DashboardCabinetSeasonYearsFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('CabinetSeasonYearsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#cabinet-season-years-index', $html);
    }

    public function test_dashboard_cabinet_doc_describes_academic_year_loop(): void
    {
        $html = $this->docFile('dashboard-cabinet.html');

        $this->assertStringContainsString('id="cabinet-season-years"', $html);
        $this->assertStringContainsString('/doc#cabinet-season-years-index', $html);
        $this->assertStringContainsString('range($cabinetSeasonEndYear, 2022)', $html);
        $this->assertStringContainsString('2021–2022', $html);
        $this->assertStringContainsString('data-season="2022"', $html);
        $this->assertStringContainsString('id="season-2027"', $html);
        $this->assertStringContainsString('createSeasons', $html);
        $this->assertStringContainsString('DashboardSeasonsSetPricesVisibilityFeatureTest', $html);
        $this->assertStringContainsString('DashboardCabinetSeasonYearsFeatureTest', $html);
        $this->assertStringContainsString('DashboardCabinetSeasonYearsFullAccessFeatureTest', $html);
        $this->assertStringContainsString('showSessons', $html);
        $this->assertStringContainsString('setting-prices-monthly-users#cabinet-academic-year', $html);
        $this->assertStringContainsString('activeStudent', $html);
        $this->assertStringContainsString('apendPrice', $html);
        $this->assertStringContainsString('CabinetSeasonYearsDocumentationContractTest', $html);
    }

    public function test_related_docs_link_announcement_and_do_not_treat_admin_year_as_cabinet_season(): void
    {
        $prices = $this->docFile('setting-prices-monthly-users.html');
        $family = $this->docFile('parents-and-family-cabinet.html');
        $postpay = $this->docFile('postpay.html');
        $payments = $this->docFile('payments.html');

        $this->assertStringContainsString('id="cabinet-academic-year"', $prices);
        $this->assertStringContainsString('#user-year-select', $prices);
        $this->assertStringContainsString('календарного года', $prices);
        $this->assertStringContainsString('учебный', $prices);
        $this->assertStringContainsString('/doc#cabinet-season-years-index', $prices);
        $this->assertStringContainsString('dashboard-cabinet#cabinet-season-years', $prices);

        $this->assertStringContainsString('/doc#cabinet-season-years-index', $family);
        $this->assertStringContainsString('dashboard-cabinet#cabinet-season-years', $family);
        $this->assertStringContainsString('activeStudent', $family);

        $this->assertStringContainsString('/doc#cabinet-season-years-index', $postpay);
        $this->assertStringContainsString('2026–2027', $postpay);

        $this->assertStringContainsString('/doc#cabinet-season-years-index', $payments);
        $this->assertStringContainsString('dashboard-cabinet#cabinet-season-years', $payments);
    }

    public function test_controller_title_and_blade_match_the_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DocumentationController.php');
        $blade = (string) file_get_contents($root.'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString('шапки сезонов от текущего учебного года (сент–авг) до 2021–2022', $controller);
        $this->assertStringContainsString('$cabinetSeasonEndYear', $blade);
        $this->assertStringContainsString('now()->month >= 9', $blade);
        $this->assertStringContainsString('range($cabinetSeasonEndYear, 2022)', $blade);
        $this->assertStringNotContainsString('id="season-2026"', $blade);
        $this->assertStringNotContainsString('Сезон 2025 - 2026', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
