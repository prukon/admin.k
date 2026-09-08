<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\Team;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UX-контракт фильтров Select2 и модалки: разметка, дефолты, JS-ветки apply/reset/populate.
 */
final class SchoolLeadsFilterSelect2UiContractsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantLocationsView(): void
    {
        $this->grantPermission('locations.view');
    }

    private function grantDistrictsView(): void
    {
        $this->grantPermission('districts.view');
    }

    private function leadsBlade(): string
    {
        return (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));
    }

    private function modalBlade(): string
    {
        return (string) file_get_contents(resource_path('views/admin/school-leads/partials/edit-lead-modal.blade.php'));
    }

    public function test_first_open_renders_filters_in_status_district_location_team_order(): void
    {
        $this->grantDistrictsView();
        $this->grantLocationsView();

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'UiСекция',
            'is_enabled' => true,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'UiОбъект',
            'is_enabled' => true,
        ]);

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $statusPos = strpos($html, 'id="sl-filter-status"');
        $districtPos = strpos($html, 'id="sl-filter-district"');
        $locationPos = strpos($html, 'id="sl-filter-location"');
        $teamPos = strpos($html, 'id="sl-filter-team"');

        $this->assertNotFalse($statusPos);
        $this->assertNotFalse($districtPos);
        $this->assertNotFalse($locationPos);
        $this->assertNotFalse($teamPos);
        $this->assertLessThan($districtPos, $statusPos);
        $this->assertLessThan($locationPos, $districtPos);
        $this->assertLessThan($teamPos, $locationPos);
    }

    public function test_first_open_does_not_preselect_section_or_location_unlike_status(): void
    {
        $this->grantLocationsView();

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        preg_match('/<select[^>]*id="sl-filter-team"[^>]*>(.*?)<\/select>/s', $html, $team);
        preg_match('/<select[^>]*id="sl-filter-location"[^>]*>(.*?)<\/select>/s', $html, $location);
        preg_match('/<select[^>]*id="sl-filter-status"[^>]*>(.*?)<\/select>/s', $html, $status);

        $this->assertNotSame('', $team[1] ?? '');
        $this->assertStringNotContainsString('selected', $team[1]);
        $this->assertStringNotContainsString('selected', $location[1]);
        $this->assertStringContainsString('selected', $status[1]);
    }

    public function test_filter_markup_uses_generic_multiselect_and_modal_stays_single(): void
    {
        $this->grantDistrictsView();
        $this->grantLocationsView();

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('id="sl-filter-team"', $html);
        $this->assertStringContainsString('name="team_ids[]"', $html);
        $this->assertStringContainsString('id="sl-filter-location"', $html);
        $this->assertStringContainsString('name="location_ids[]"', $html);
        $this->assertStringContainsString('js-generic-multiselect-select', $html);
        $this->assertStringContainsString('data-placeholder="Все секции"', $html);
        $this->assertStringContainsString('data-placeholder="Все объекты"', $html);
        $this->assertStringContainsString('>Без секции</option>', $html);
        $this->assertStringContainsString('>Без объекта</option>', $html);

        preg_match('/<select[^>]*id="leadTeam"[^>]*>/', $html, $leadTeam);
        $this->assertNotEmpty($leadTeam[0] ?? '');
        $this->assertStringContainsString('name="team_id"', $leadTeam[0]);
        $this->assertStringNotContainsString('multiple', $leadTeam[0]);
        $this->assertStringNotContainsString('js-generic-multiselect-select', $leadTeam[0]);

        preg_match('/<select[^>]*id="leadLocation"[^>]*>/', $html, $leadLocation);
        $this->assertNotEmpty($leadLocation[0] ?? '');
        $this->assertStringContainsString('name="location_id"', $leadLocation[0]);
        $this->assertStringNotContainsString('multiple', $leadLocation[0]);

        preg_match('/<select[^>]*id="sl-filter-district"[^>]*>/', $html, $district);
        $this->assertNotEmpty($district[0] ?? '');
        $this->assertStringNotContainsString('js-generic-multiselect-select', $district[0]);
        $this->assertStringNotContainsString('multiple', $district[0]);
    }

    public function test_js_applies_filters_only_on_submit_and_sends_id_arrays(): void
    {
        $js = $this->leadsBlade();

        $this->assertStringContainsString('e.preventDefault();', $js);
        $this->assertStringContainsString('appliedFilters = readFiltersFromForm();', $js);
        $this->assertStringContainsString('d.team_ids = appliedFilters.team_ids;', $js);
        $this->assertStringContainsString('d.location_ids = appliedFilters.location_ids;', $js);
        $this->assertStringContainsString('var teamIds = $teamFilter.length ? ($teamFilter.val() || []) : [];', $js);
        $this->assertStringContainsString('locationIds = $locationFilter.val() || [];', $js);

        $this->assertStringNotContainsString('$teamFilter.on(\'change\'', $js);
        $this->assertStringNotContainsString('$locationFilter.on(\'change\'', $js);
        $this->assertStringNotContainsString('KidsCrmGenericMultiselectSelect2.init($districtFilter', $js);
        $this->assertStringNotContainsString("KidsCrmGenericMultiselectSelect2.init($('#sl-filter-district')", $js);
    }

    public function test_js_reset_clears_section_and_location_but_restores_default_statuses(): void
    {
        $js = $this->leadsBlade();
        $resetStart = strpos($js, 'function resetFiltersFormToDefault()');
        $this->assertNotFalse($resetStart);
        $reset = substr($js, $resetStart, 1600);

        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.reset($teamFilter)', $reset);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.reset($locationFilter)', $reset);
        $this->assertStringContainsString('KidsCrmFilterMultiselectSelect2.setValues($statusFilter, defaultStatusFilters)', $reset);
        $this->assertStringContainsString('$districtFilter.val(\'\')', $reset);

        $this->assertStringNotContainsString('KidsCrmGenericMultiselectSelect2.reset($statusFilter)', $reset);
        $this->assertStringNotContainsString('KidsCrmFilterMultiselectSelect2.reset($teamFilter)', $reset);

        $this->assertStringContainsString("$('#schoolLeadsFiltersResetBtn').on('click'", $js);
    }

    public function test_js_inits_generic_multiselect_for_filters_and_single_select2_in_modal(): void
    {
        $js = $this->leadsBlade();

        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.init($teamFilter', $js);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.init($locationFilter', $js);
        $this->assertStringContainsString("dropdownParent: $('#school-leads-filters')", $js);
        $this->assertStringContainsString("placeholder: \$teamFilter.data('placeholder') || 'Все секции'", $js);
        $this->assertStringContainsString("placeholder: \$locationFilter.data('placeholder') || 'Все объекты'", $js);
        $this->assertStringContainsString('allowClear: true', $js);

        $initStart = strpos($js, 'if (canViewLocations && $locationFilter.length');
        $this->assertNotFalse($initStart);

        $this->assertStringContainsString('function initLeadModalSingleSelect2($select)', $js);
        $this->assertStringContainsString("theme: 'bootstrap-5'", $js);
        $this->assertStringContainsString("language: @include('partials.select2.ru')", $js);
        $this->assertStringContainsString('dropdownParent: $dropdownParent.length ? $dropdownParent : $editLeadModal', $js);
        $this->assertStringContainsString('initLeadModalSingleSelect2($leadTeamSelect)', $js);
        $this->assertStringContainsString('initLeadModalSingleSelect2($leadLocationSelect)', $js);
    }

    public function test_reopening_modal_clears_previous_group_and_location_when_lead_has_none(): void
    {
        $js = $this->leadsBlade();
        $populateStart = strpos($js, 'function populateLeadForm(rowData)');
        $this->assertNotFalse($populateStart);
        $populate = substr($js, $populateStart, 2200);

        $this->assertStringContainsString(
            '$leadTeamSelect.val(rowData.team_id ? String(rowData.team_id) : \'\').trigger(\'change\')',
            $populate
        );
        $this->assertStringContainsString(
            '$leadLocationSelect.val(rowData.location_id ? String(rowData.location_id) : \'\').trigger(\'change\')',
            $populate
        );
        $this->assertStringNotContainsString('$leadTeamSelect.val(rowData.team_id).trigger', $populate);
        $this->assertStringNotContainsString('$leadLocationSelect.val(rowData.location_id).trigger', $populate);
    }

    public function test_modal_save_sends_scalar_team_id_and_shows_field_errors_on_select2(): void
    {
        $js = $this->leadsBlade();
        $payloadStart = strpos($js, 'function collectLeadPayload()');
        $this->assertNotFalse($payloadStart);
        $payload = substr($js, $payloadStart, 2800);

        $this->assertStringContainsString('team_id: $leadTeamSelect.val()', $payload);
        $this->assertStringContainsString('payload.location_id = $leadLocationSelect.val()', $payload);
        $this->assertStringNotContainsString('team_ids', $payload);

        $this->assertStringContainsString(
            '$input.next(\'.select2-container\').find(\'.select2-selection\').addClass(\'is-invalid\')',
            $js
        );
        $this->assertStringContainsString(
            '$editLeadForm.find(\'.select2-container .select2-selection\').removeClass(\'is-invalid\')',
            $js
        );

        $modal = $this->modalBlade();
        $this->assertStringContainsString('data-field-error="team_id"', $modal);
        $this->assertStringContainsString('data-field-error="location_id"', $modal);
        $this->assertStringNotContainsString('.select2-selection.is-invalid', $modal);

        $css = (string) file_get_contents(resource_path('css/school-leads-table.css'));
        $this->assertStringContainsString('.select2-selection.is-invalid', $css);
        $this->assertStringContainsString('#editLeadModal .select2-container', $css);
    }
}
