<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#school-leads-sticky-header-index совпадает с вкладкой заявок.
 */
final class SchoolLeadsStickyHeaderDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_school_leads_sticky_header_and_hscroll(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="school-leads-sticky-header-index"', $html);
        $start = strpos($html, 'id="school-leads-sticky-header-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="users-sticky-header-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/school-leads', $chunk);
        $this->assertStringContainsString('KidsCrmDataTable.create(\'#leads-table\')', $chunk);
        $this->assertStringContainsString('header: true', $chunk);
        $this->assertStringContainsString('footer: false', $chunk);
        $this->assertStringContainsString('school-leads-table.css', $chunk);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $chunk);
        $this->assertStringContainsString('kids-dt-scroll-x', $chunk);
        $this->assertStringContainsString('datatables-fixedheader', $chunk);
        $this->assertStringContainsString('<code>scrollY</code>', $chunk);
        $this->assertStringContainsString('это не', $chunk);
        $this->assertStringContainsString('scrollLeft', $chunk);
        $this->assertStringContainsString('не через', $chunk);
        $this->assertStringContainsString('margin-left', $chunk);
        $this->assertStringContainsString('school-leads-widget#school-leads-sticky-header', $chunk);
        $this->assertStringContainsString('SchoolLeadsPageFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadsTabsFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadsStickyHeaderDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#school-leads-sticky-header-index', $html);
    }

    public function test_related_docs_and_blade_match_sticky_header_contract(): void
    {
        $widget = $this->docFile('school-leads-widget.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/school-leads/tabs/leads.blade.php');

        $this->assertStringContainsString('id="school-leads-sticky-header"', $widget);
        $this->assertStringContainsString('/doc#school-leads-sticky-header-index', $widget);
        $this->assertStringContainsString('school-leads-table.css', $widget);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $widget);
        $this->assertStringContainsString('header: true, footer: false', $widget);
        $this->assertStringContainsString('bindSchoolLeadsStickyHScroll', $widget);
        $this->assertStringContainsString('getBoundingClientRect', $widget);
        $this->assertStringContainsString('overflow: hidden', $widget);
        $this->assertStringContainsString('scrollLeft', $widget);
        $this->assertStringContainsString('не <code>margin-left</code>', $widget);
        $this->assertStringContainsString('box-sizing: border-box', $widget);

        $this->assertStringContainsString('school-leads-widget#school-leads-sticky-header', $ui);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $ui);

        $this->assertStringContainsString("@vite(['resources/css/admin-list-toolbar.css', 'resources/css/school-leads-table.css'])", $blade);
        $this->assertStringNotContainsString('<style>', $blade);
        $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/school-leads-table.css');
        $this->assertStringContainsString('.dtfh-floatingparenthead', $css);
        $this->assertStringContainsString('overflow: hidden', $css);
        $vite = (string) file_get_contents(dirname(__DIR__, 3).'/vite.config.js');
        $this->assertStringContainsString("'resources/css/school-leads-table.css'", $vite);

        $this->assertStringContainsString('dataTables.fixedHeader.min.js', $blade);
        $this->assertStringContainsString('kids-dt-sticky-hscroll', $blade);
        $this->assertStringContainsString('header: true', $blade);
        $this->assertStringContainsString('footer: false', $blade);
        $this->assertStringContainsString('bindSchoolLeadsStickyHScroll', $blade);
        $this->assertStringContainsString('dtfh-floatingparenthead', $blade);
        $this->assertStringContainsString('getBoundingClientRect()', $blade);
        $this->assertStringContainsString("setProperty('overflow'", $blade);
        $this->assertStringContainsString('parent.scrollLeft', $blade);
        $this->assertStringContainsString('matchSchoolLeadsFloatingHeaderWidths', $blade);
        $this->assertStringContainsString('boxSizing', $blade);
        $this->assertStringContainsString('maxWidth', $blade);
        $this->assertStringNotContainsString("margin-left', (-", $blade);
        $this->assertStringNotContainsString('scrollY:', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
