<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI и API страницы «Заявки с сайта»: toolbar, фильтры, статистика, колонки.
 */
final class SchoolLeadsPageFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function grantLocationsView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_index_passes_lead_stats_and_renders_toolbar(): void
    {
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Н1',
            'phone'      => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Н2',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'П1',
            'phone'      => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Продажа',
            'phone'      => '+7 900 444-44-44',
            'school_lead_status_id' => $this->schoolLeadSaleStatusId(),
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'leads')
            ->assertViewHas('leadStats', [
                'total' => 4,
                'new'   => 2,
            ])
            ->assertSee('id="schoolLeadsReportToolbar"', false)
            ->assertSee('id="schoolLeadsFiltersCollapse"', false)
            ->assertSee('id="schoolLeadsFiltersToggle"', false)
            ->assertSee('school-leads-stat-new', false)
            ->assertDontSee('school-leads-stat-processing', false)
            ->assertSee('school-leads-stat-total', false)
            ->assertSee('id="schoolLeadStatusesModal"', false)
            ->assertSee('id="school-leads-filters"', false)
            ->assertSee('id="sl-filter-status"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('KidsCrmGenericMultiselectSelect2', false)
            ->assertSee('KidsCrmDataTable.create', false)
            ->assertSee('school-leads-column-toggle', false)
            ->assertSee('id="sl-filter-team"', false)
            ->assertSee('id="sl-filter-special-conditions"', false)
            ->assertSee('id="leads-table"', false);
    }

    public function test_datatable_stats_are_partner_wide_and_ignore_table_filters(): void
    {
        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Филиал',
            'is_enabled' => true,
        ]);

        $this->grantLocationsView();

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Новый в филиале',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Новый без филиала',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'В обработке',
            'phone'      => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Продажа',
            'phone'      => '+7 900 444-44-44',
            'school_lead_status_id' => $this->schoolLeadSaleStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId()],
            'location_id' => (string) $loc->id,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('Новый в филиале', $response->json('data.0.name'));
        $this->assertSame([
            'total' => 4,
            'new'   => 2,
        ], $response->json('stats'));
    }

    public function test_index_location_filter_lists_only_enabled_partner_locations(): void
    {
        $this->grantLocationsView();

        $enabled = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'АктивныйФилиал',
            'is_enabled' => true,
        ]);
        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'ОтключённыйФилиал',
            'is_enabled' => false,
        ]);
        Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'ЧужойФилиал',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-location"', false)
            ->assertSee('Все объекты', false)
            ->assertSee('Без объекта', false)
            ->assertSee('>АктивныйФилиал</option>', false)
            ->assertDontSee('>ОтключённыйФилиал</option>', false)
            ->assertDontSee('>ЧужойФилиал</option>', false)
            ->assertSee('value="' . $enabled->id . '"', false);
    }

    public function test_datatable_combines_status_and_location_filters(): void
    {
        $this->grantLocationsView();

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Центр',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Подходит',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Другой статус',
            'phone'       => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
            'location_id' => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Другая локация',
            'phone'      => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId()],
            'location_id' => (string) $loc->id,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('Подходит', $response->json('data.0.name'));
    }

    public function test_datatable_filters_by_multiple_statuses(): void
    {
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Новый',
            'phone'      => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Обработка',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Продажа',
            'phone'      => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSaleStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId(), $this->schoolLeadProcessingStatusId()],
        ]));

        $response->assertOk();
        $this->assertEquals(3, $response->json('recordsTotal'));
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $names = array_column($response->json('data'), 'name');
        $this->assertEqualsCanonicalizing(['Новый', 'Обработка'], $names);
    }

    public function test_datatable_row_contains_utm_and_referrer_fields(): void
    {
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Маркетинг',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'utm_source'  => 'google',
            'utm_medium'  => 'cpc',
            'utm_campaign'=> 'spring',
            'page_url'    => 'https://example.com/landing',
            'referrer'    => 'https://google.com',
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertSame('Маркетинг', $row['name']);
        $this->assertStringContainsString('source: google', $row['utm_summary']);
        $this->assertStringContainsString('medium: cpc', $row['utm_summary']);
        $this->assertStringContainsString('campaign: spring', $row['utm_summary']);
        $this->assertSame('https://example.com/landing', $row['page_url']);
        $this->assertSame('https://google.com', $row['referrer']);
        $this->assertNotNull($row['created_at']);
    }

    public function test_datatable_pagination_returns_correct_slice(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            SchoolLead::create([
                'partner_id' => $this->partner->id,
                'name'       => 'Лид ' . $i,
                'phone'      => '+7 900 000-00-0' . $i,
                'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            ]);
        }

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 2,
            'length' => 2,
        ]));

        $response->assertOk();
        $this->assertEquals(5, $response->json('recordsTotal'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_datatable_filters_by_team(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Футбол',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'С секцией',
            'phone'      => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'    => $team->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Без секции',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId()],
            'team_id'  => (string) $team->id,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('С секцией', $response->json('data.0.name'));
    }

    public function test_datatable_filters_by_special_conditions(): void
    {
        SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'С особенностями',
            'phone'                  => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'is_individual_traits'   => true,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Обычный',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'                     => 1,
            'start'                    => 0,
            'length'                   => 10,
            'status_ids' => [$this->schoolLeadSystemStatusId()],
            'has_special_conditions'   => '1',
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('С особенностями', $response->json('data.0.name'));
    }

    public function test_index_team_filter_lists_only_enabled_partner_teams(): void
    {
        $enabled = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'АктивнаяСекция',
            'is_enabled' => true,
        ]);
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ОтключённаяСекция',
            'is_enabled' => false,
        ]);
        Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'ЧужаяСекция',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-team"', false)
            ->assertSee('Все секции', false)
            ->assertSee('Без секции', false)
            ->assertSee('>АктивнаяСекция</option>', false)
            ->assertDontSee('>ЧужаяСекция</option>', false)
            ->assertSee('value="' . $enabled->id . '"', false);
    }

    public function test_columns_settings_rejects_invalid_payload(): void
    {
        $this->postJson(route('admin.school-leads.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_columns_settings_persist_per_user(): void
    {
        $otherAdmin = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $otherAdmin->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => ['name' => true, 'phone' => false],
        ])->assertOk();

        $this->actingAs($otherAdmin);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJson([]);
    }
}
