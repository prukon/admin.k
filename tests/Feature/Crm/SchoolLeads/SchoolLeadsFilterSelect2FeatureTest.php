<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтры «Секция» и «Объект»: Select2 generic-multiselect и API team_ids[] / location_ids[].
 */
final class SchoolLeadsFilterSelect2FeatureTest extends CrmTestCase
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

    public function test_index_renders_team_filter_as_generic_multiselect(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'СекцияSelect2',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-team"', false)
            ->assertSee('name="team_ids[]"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('KidsCrmGenericMultiselectSelect2.init', false)
            ->assertSee('data-placeholder="Все секции"', false)
            ->assertSee('>Без секции</option>', false)
            ->assertSee('>СекцияSelect2</option>', false)
            ->assertSee('value="' . $team->id . '"', false)
            ->assertSee("dropdownParent: $('#school-leads-filters')", false);
    }

    public function test_index_renders_location_filter_as_generic_multiselect(): void
    {
        $this->grantLocationsView();

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ОбъектSelect2',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-location"', false)
            ->assertSee('name="location_ids[]"', false)
            ->assertSee('data-placeholder="Все объекты"', false)
            ->assertSee('>Без объекта</option>', false)
            ->assertSee('>ОбъектSelect2</option>', false)
            ->assertSee('id="leadLocation"', false)
            ->assertSee('initLeadModalSingleSelect2', false);
    }

    public function test_index_renders_lead_modal_group_as_select2_single(): void
    {
        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('id="leadTeam"', $html);
        $this->assertStringContainsString('initLeadModalSingleSelect2', $html);
        $this->assertStringContainsString("theme: 'bootstrap-5'", $html);
        $this->assertStringContainsString('$leadTeamSelect.val(', $html);
        $this->assertStringNotContainsString('name="team_id[]"', $html);
    }

    public function test_datatable_filters_by_multiple_team_ids(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ГруппаА',
            'is_enabled' => true,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ГруппаБ',
            'is_enabled' => true,
        ]);
        $teamC = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ГруппаВ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ЛидА',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ЛидБ',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamB->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ЛидВ',
            'phone'                 => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamC->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'БезСекции',
            'phone'                 => '+7 900 444-44-44',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => [$teamA->id, $teamB->id],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['ЛидА', 'ЛидБ'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_datatable_filters_by_team_ids_including_none(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ССекцией',
            'is_enabled' => true,
        ]);
        $other = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Другая',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'СГруппой',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $team->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'БезГруппы',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ДругаяГруппа',
            'phone'                 => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $other->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => ['none', (string) $team->id],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['СГруппой', 'БезГруппы'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_datatable_empty_team_ids_does_not_filter(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Любой',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => [],
        ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('recordsFiltered'));
    }

    public function test_datatable_filters_by_multiple_location_ids(): void
    {
        $this->grantLocationsView();

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ОбъектА',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ОбъектБ',
            'is_enabled' => true,
        ]);
        $locC = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ОбъектВ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ВАобъекте',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $locA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ВБобъекте',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $locB->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ВВобъекте',
            'phone'                 => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $locC->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'location_ids' => [$locA->id, $locB->id],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['ВАобъекте', 'ВБобъекте'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_datatable_legacy_scalar_team_id_still_works(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Legacy',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'СLegacy',
            'phone'                 => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $team->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ДругойLegacy',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'team_id' => (string) $team->id,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('СLegacy', $response->json('data.0.name'));
    }

    public function test_datatable_returns_lead_when_any_of_several_team_ids_matches(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ОрСекцияА',
            'is_enabled' => true,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ОрСекцияБ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ТолькоА',
            'phone'                 => '+7 900 501-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ТолькоБ',
            'phone'                 => '+7 900 501-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamB->id,
        ]);

        $url = route('admin.school-leads.data')
            .'?draw=1&start=0&length=10'
            .'&team_ids[]='.$teamA->id
            .'&team_ids[]='.$teamB->id;

        $response = $this->getJson($url);

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['ТолькоА', 'ТолькоБ'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_datatable_does_not_require_lead_to_match_all_selected_teams(): void
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'НеИАсекция',
            'is_enabled' => true,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'НеИБсекция',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ОднаСекция',
            'phone'                 => '+7 900 502-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamA->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => [$teamA->id, $teamB->id],
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('ОднаСекция', $response->json('data.0.name'));
    }

    public function test_datatable_filters_by_location_ids_including_none(): void
    {
        $this->grantLocationsView();

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'СОбъектом',
            'is_enabled' => true,
        ]);
        $other = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ДругойОбъект',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'СОбъектомЛид',
            'phone'                 => '+7 900 503-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'БезОбъектаЛид',
            'phone'                 => '+7 900 503-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ДругойОбъектЛид',
            'phone'                 => '+7 900 503-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $other->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'location_ids' => ['none', (string) $loc->id],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['СОбъектомЛид', 'БезОбъектаЛид'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_datatable_empty_location_ids_does_not_filter(): void
    {
        $this->grantLocationsView();

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ЛюбойОбъект',
            'phone'                 => '+7 900 504-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'location_ids' => [],
        ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('recordsFiltered'));
    }

    public function test_datatable_legacy_scalar_location_id_still_works(): void
    {
        $this->grantLocationsView();

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'LegacyОбъект',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'СLegacyОбъектом',
            'phone'                 => '+7 900 505-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'БезLegacyОбъекта',
            'phone'                 => '+7 900 505-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'location_id' => (string) $loc->id,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('СLegacyОбъектом', $response->json('data.0.name'));
    }

    public function test_datatable_combines_team_and_location_filters_with_and(): void
    {
        $this->grantLocationsView();

        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ИСекцияА',
            'is_enabled' => true,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ИСекцияБ',
            'is_enabled' => true,
        ]);
        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ИОбъектА',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ИОбъектБ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'АиА',
            'phone'                 => '+7 900 506-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamA->id,
            'location_id'           => $locA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'БиА',
            'phone'                 => '+7 900 506-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamB->id,
            'location_id'           => $locA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'АиБ',
            'phone'                 => '+7 900 506-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $teamA->id,
            'location_id'           => $locB->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'team_ids'     => [$teamA->id, $teamB->id],
            'location_ids' => [$locA->id],
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertEqualsCanonicalizing(
            ['АиА', 'БиА'],
            array_column($response->json('data'), 'name')
        );
    }

    public function test_datatable_garbage_team_ids_do_not_hide_leads(): void
    {
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'МусорФильтр',
            'phone'                 => '+7 900 507-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => ['foo', 'bar'],
        ]));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('recordsFiltered'));
        $this->assertContains('МусорФильтр', array_column($response->json('data'), 'name'));
    }

    public function test_datatable_does_not_leak_foreign_partner_leads_when_filtering_teams(): void
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'СвояСекция',
            'is_enabled' => true,
        ]);
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'ЧужаяСекцияФильтр',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'СвойЛидСекция',
            'phone'                 => '+7 900 508-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $ownTeam->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->foreignPartner->id,
            'name'                  => 'ЧужойЛидСекция',
            'phone'                 => '+7 900 508-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $foreignTeam->id,
        ]);

        $own = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => [$ownTeam->id, $foreignTeam->id],
        ]));

        $own->assertOk();
        $names = array_column($own->json('data'), 'name');
        $this->assertContains('СвойЛидСекция', $names);
        $this->assertNotContains('ЧужойЛидСекция', $names);
    }

    public function test_index_does_not_preselect_team_or_location_filter_values(): void
    {
        $this->grantLocationsView();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'БезПреселекта',
            'is_enabled' => true,
        ]);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ОбъектБезПреселекта',
            'is_enabled' => true,
        ]);

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertTrue((bool) preg_match('/<select[^>]*id="sl-filter-team"[^>]*>(.*?)<\/select>/s', $html, $teamSelect));
        $this->assertStringNotContainsString('selected', $teamSelect[1]);
        $this->assertStringContainsString('value="'.$team->id.'"', $teamSelect[1]);

        $this->assertTrue((bool) preg_match('/<select[^>]*id="sl-filter-location"[^>]*>(.*?)<\/select>/s', $html, $locSelect));
        $this->assertStringNotContainsString('selected', $locSelect[1]);
        $this->assertStringContainsString('value="'.$location->id.'"', $locSelect[1]);

        $this->assertTrue((bool) preg_match('/<select[^>]*id="sl-filter-status"[^>]*>(.*?)<\/select>/s', $html, $statusSelect));
        $this->assertStringContainsString('selected', $statusSelect[1]);
    }

    public function test_index_keeps_district_as_native_single_select(): void
    {
        $this->grantPermission('districts.view');

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();

        $this->assertTrue((bool) preg_match('/<select[^>]*id="sl-filter-district"[^>]*>/', $html, $districtOpen));
        $this->assertStringNotContainsString('js-generic-multiselect-select', $districtOpen[0]);
        $this->assertStringNotContainsString('multiple', $districtOpen[0]);
        $this->assertStringContainsString('name="district_id"', $districtOpen[0]);
    }
}
