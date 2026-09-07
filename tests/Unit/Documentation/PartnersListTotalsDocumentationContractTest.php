<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#admin-partners-list-totals-index совпадает с tfoot итогов /admin/partners.
 */
final class PartnersListTotalsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_partners_list_totals_footer(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="admin-partners-list-totals-index"', $html);
        $start = strpos($html, 'id="admin-partners-list-totals-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-invitation-parent-email-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('GET /admin/partners/data', $chunk);
        $this->assertStringContainsString('totals', $chunk);
        $this->assertStringContainsString('PartnerListMetrics::JSON_KEYS', $chunk);
        $this->assertStringContainsString('totalsPayloadFromJoinedQuery', $chunk);
        $this->assertStringContainsString('не</b> по видимой странице', $chunk);
        $this->assertStringContainsString('не навязывает active', $chunk);
        $this->assertStringContainsString('fillPartnersTotalsFooter', $chunk);
        $this->assertStringContainsString('api.ajax.json().totals', $chunk);
        $this->assertStringContainsString('header: false', $chunk);
        $this->assertStringContainsString('kids-dt-scroll-x', $chunk);
        $this->assertStringContainsString('account-partner-organization#partners-index-totals', $chunk);
        $this->assertStringContainsString('PartnersListTotalsFeatureTest', $chunk);
        $this->assertStringContainsString('PartnersListTotalsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#admin-partners-list-totals-index', $chunk);
        $this->assertStringContainsString('/doc#admin-partners-list-metrics', $chunk);

        $this->assertStringNotContainsString('сумма текущей страницы пагинации', $chunk);
        $this->assertStringNotContainsString('API без status считает только активных', $chunk);
    }

    public function test_related_doc_pages_link_totals_announcement(): void
    {
        $org = $this->docFile('account-partner-organization.html');
        $tbank = $this->docFile('tbank.html');
        $reports = $this->docFile('reports-admin.html');
        $payments = $this->docFile('reports-payments.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $index = $this->docFile('index.html');

        $this->assertStringContainsString('id="partners-index-totals"', $org);
        $this->assertStringContainsString('/doc#admin-partners-list-totals-index', $org);
        $this->assertStringContainsString('totalsPayloadFromJoinedQuery', $org);
        $this->assertStringContainsString('json().totals', $org);
        $this->assertStringContainsString('не навязывает active', $org);
        $this->assertStringContainsString('тот же JSON', $org);
        $this->assertStringContainsString('Mixing of GROUP columns', $org);
        $this->assertStringContainsString('fillPartnersTotalsFooter', $org);
        $this->assertStringContainsString('header: false, footer: true', $org);
        $this->assertStringNotContainsString('Пустой фильтр — все ключи на месте, значения 0', $org);

        $this->assertStringContainsString('/doc#admin-partners-list-totals-index', $tbank);
        $this->assertStringContainsString('/doc#admin-partners-list-totals-index', $reports);
        $this->assertStringContainsString('partners-index-totals', $reports);
        $this->assertStringContainsString('/doc#admin-partners-list-totals-index', $payments);
        $this->assertStringContainsString('partners-index-totals', $ui);
        $this->assertStringContainsString('/doc#admin-partners-list-totals-index', $index);
        $this->assertStringContainsString('итоги tfoot по фильтрам (не сумма страницы)', $this->controller());
    }

    public function test_live_code_computes_totals_from_filters_not_page_rows(): void
    {
        $root = dirname(__DIR__, 3);
        $metrics = (string) file_get_contents($root.'/app/Support/PartnerListMetrics.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/PartnerController.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/partners/tabs/partners.blade.php');

        $totalsFnStart = strpos($metrics, 'function totalsPayloadFromJoinedQuery');
        $this->assertNotFalse($totalsFnStart);
        $totalsFn = substr($metrics, $totalsFnStart, 1800);
        $this->assertStringContainsString("->select([", $totalsFn);
        $this->assertStringContainsString('SUM(COALESCE(partner_turnover.turnover_all_cents', $totalsFn);
        $this->assertStringNotContainsString('selectRaw(', $totalsFn);

        $this->assertStringContainsString('PartnerListMetrics::totalsPayloadFromJoinedQuery($baseQuery)', $controller);
        $totalsPos = strpos($controller, 'totalsPayloadFromJoinedQuery');
        $skipPos = strpos($controller, '->skip($start)');
        $this->assertNotFalse($totalsPos);
        $this->assertNotFalse($skipPos);
        $this->assertLessThan($skipPos, $totalsPos);
        $this->assertStringContainsString("'totals'          => \$totals", $controller);

        $this->assertStringContainsString('function fillPartnersTotalsFooter(api)', $blade);
        $this->assertStringContainsString('json.totals', $blade);
        $this->assertStringContainsString('header: false', $blade);
        $this->assertStringContainsString('footer: true', $blade);
        $pluginPos = strpos($blade, 'dataTables.fixedHeader.min.js');
        $createPos = strpos($blade, "KidsCrmDataTable.create('#partners-table'");
        $this->assertNotFalse($pluginPos);
        $this->assertNotFalse($createPos);
        $this->assertLessThan($createPos, $pluginPos);
        preg_match('/<tfoot>(.*?)<\/tfoot>/s', $blade, $tfoot);
        $this->assertNotEmpty($tfoot[1] ?? null);
        $this->assertStringNotContainsString('Итого', $tfoot[1]);
    }

    private function controller(): string
    {
        $path = dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
