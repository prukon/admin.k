<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#school-leads-filter-select2-index совпадает с фильтрами заявок.
 */
final class SchoolLeadsFilterSelect2DocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_school_leads_filter_select2(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="school-leads-filter-select2-index"', $html);
        $start = strpos($html, 'id="school-leads-filter-select2-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-list-updated-at-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/school-leads', $chunk);
        $this->assertStringContainsString('/admin/teams', $chunk);
        $this->assertStringContainsString('хотя бы одно', $chunk);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2', $chunk);
        $this->assertStringContainsString('generic-multiselect', $chunk);
        $this->assertStringContainsString('school-leads-table.css', $chunk);
        $this->assertStringContainsString('generic-multiselect.css', $chunk);
        $this->assertStringContainsString('npm run build', $chunk);
        $this->assertStringContainsString('team_ids[]', $chunk);
        $this->assertStringContainsString('location_ids[]', $chunk);
        $this->assertStringContainsString('любого', $chunk);
        $this->assertStringContainsString('Район не переводился', $chunk);
        $this->assertStringContainsString('Select2 single', $chunk);
        $this->assertStringContainsString('school-leads-widget#school-leads-filter-select2', $chunk);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2FeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2AccessFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2FullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2AjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2NonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2UiContractsFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('/doc#school-leads-filter-select2-index', $html);
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($html, 'school-leads-filter-select2-index'),
            'Анонс должен быть на /doc и в оглавлении списка разделов'
        );
    }

    public function test_related_docs_and_blade_match_filter_select2_contract(): void
    {
        $widget = $this->docFile('school-leads-widget.html');
        $ui = $this->docFile('reusable-ui-partials.html');
        $indexBlade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/school-leads/index.blade.php');
        $leads = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/school-leads/tabs/leads.blade.php');
        $modal = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/school-leads/partials/edit-lead-modal.blade.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/SchoolLeadController.php');
        $layout = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/layouts/admin2.blade.php');
        $vite = (string) file_get_contents(dirname(__DIR__, 3).'/vite.config.js');

        $this->assertStringContainsString('id="school-leads-filter-select2"', $widget);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2', $widget);
        $this->assertStringContainsString('team_ids[]', $widget);
        $this->assertStringContainsString('location_ids[]', $widget);
        $this->assertStringContainsString('любого', $widget);
        $this->assertStringContainsString('school-leads-table.css', $widget);
        $this->assertStringContainsString('generic-multiselect.css', $widget);
        $this->assertStringContainsString('/doc#school-leads-filter-select2-index', $widget);
        $this->assertStringContainsString('initLeadModalSingleSelect2', $widget);
        $this->assertStringContainsString('не относится к фильтрам «Секция» и «Объект»', $widget);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2AccessFeatureTest', $widget);
        $this->assertStringContainsString('SchoolLeadsFilterSelect2UiContractsFeatureTest', $widget);

        $this->assertStringContainsString('school-leads-widget#school-leads-filter-select2', $ui);
        $this->assertStringContainsString('фильтры «Секция»/«Объект»', $ui);

        $hierarchy = $this->docFile('directories-hierarchy.html');
        $this->assertStringContainsString('location_ids[]', $hierarchy);
        $this->assertStringContainsString('team_ids[]', $hierarchy);
        $this->assertStringContainsString('school-leads-widget#school-leads-filter-select2', $hierarchy);
        $this->assertStringNotContainsString(
            'параметры <code>district_id</code>, <code>location_id</code> (значение <code>none</code> — без района/объекта)',
            $hierarchy
        );

        $this->assertStringContainsString("@include('partials.select2.generic-multiselect')", $indexBlade);

        $this->assertStringContainsString('name="team_ids[]"', $leads);
        $this->assertStringContainsString('name="location_ids[]"', $leads);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.init', $leads);
        $this->assertStringContainsString('d.team_ids = appliedFilters.team_ids', $leads);
        $this->assertStringContainsString('d.location_ids = appliedFilters.location_ids', $leads);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.reset($teamFilter)', $leads);
        $this->assertStringContainsString('initLeadModalSingleSelect2', $leads);
        $this->assertStringContainsString("language: @include('partials.select2.ru')", $leads);

        $this->assertStringContainsString('id="leadTeam"', $modal);
        $this->assertStringContainsString('data-placeholder="Без группы"', $modal);
        $this->assertStringNotContainsString('#editLeadModal .select2-container', $modal);

        $this->assertStringContainsString('applyNullableIdListFilter', $controller);
        $this->assertStringContainsString("'team_ids'", $controller);
        $this->assertStringContainsString("'location_ids'", $controller);

        $docsController = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('фильтры секция/объект Select2 §5.1.7', $docsController);

        $this->assertStringContainsString('css/select2/select2.min.css', $layout);
        $this->assertStringContainsString('select2-bootstrap-5-theme.min.css', $layout);
        $this->assertStringContainsString("'resources/css/school-leads-table.css'", $vite);
        $this->assertStringNotContainsString('select2.min.css', $vite);

        $tableCss = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/school-leads-table.css');
        $this->assertStringContainsString("@import './generic-multiselect.css'", $tableCss);
        $this->assertStringContainsString('#editLeadModal .select2-container', $tableCss);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
