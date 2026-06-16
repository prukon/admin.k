<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolLeadLocationFeatureTest extends CrmTestCase
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

    private function grantSchoolLeadsView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function actorWithSchoolLeadsOnly(): User
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        return $actor;
    }

    public function test_school_leads_page_shows_location_ui_with_locations_view(): void
    {
        $this->grantPermission('locations.view');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Филиал А',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('canViewLocations', true)
            ->assertViewHas('activeLocations')
            ->assertSee('id="sl-filter-location"', false)
            ->assertSee('Филиал А', false)
            ->assertSee('id="leadLocation"', false)
            ->assertSee('data-column-key="location"', false)
            ->assertSee('id="columnsDropdownSchoolLeads"', false);
    }

    public function test_school_leads_page_hides_location_ui_without_locations_view(): void
    {
        $this->actorWithSchoolLeadsOnly();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewHas('canViewLocations', false)
            ->assertDontSee('id="leadLocation"', false)
            ->assertDontSee('id="sl-filter-location"', false)
            ->assertDontSee('data-column-key="location"', false);
    }

    public function test_datatable_returns_location_fields_and_filters_by_location(): void
    {
        $this->grantPermission('locations.view');

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Локация A',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Локация B',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Без локации',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => null,
        ]);
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'В A',
            'phone'       => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $locA->id,
        ]);
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'В B',
            'phone'       => '+7 900 333-33-33',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $locB->id,
        ]);

        $all = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));
        $all->assertOk();
        $this->assertEquals(3, $all->json('recordsTotal'));
        $all->assertJsonStructure(['stats' => ['total', 'new']]);
        $this->assertArrayHasKey('location_name', $all->json('data.0'));
        $this->assertArrayHasKey('location_id', $all->json('data.0'));

        $withoutLocation = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'location_id' => 'none',
        ]));
        $withoutLocation->assertOk();
        $this->assertEquals(1, $withoutLocation->json('recordsFiltered'));
        $this->assertSame('Без локации', $withoutLocation->json('data.0.name'));

        $inLocationA = $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'location_id' => (string) $locA->id,
        ]));
        $inLocationA->assertOk();
        $this->assertEquals(1, $inLocationA->json('recordsFiltered'));
        $this->assertSame('В A', $inLocationA->json('data.0.name'));
    }

    public function test_datatable_filters_by_single_location(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Одна',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Целевой',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Другой',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'location_id' => (string) $loc->id,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('Целевой', $response->json('data.0.name'));
        $this->assertSame('Одна', $response->json('data.0.location_name'));
    }

    public function test_datatable_empty_location_filter_returns_all(): void
    {
        $this->grantPermission('locations.view');

        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Первый',
            'phone'      => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Второй',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'location_id' => '',
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
    }

    public function test_datatable_search_by_location_name(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'УникальнаяЛокацияXYZ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Найти по локации',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Не попадёт',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'search' => ['value' => 'УникальнаяЛокацияXYZ'],
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('recordsFiltered'));
        $this->assertSame('Найти по локации', $response->json('data.0.name'));
    }

    public function test_datatable_sorts_by_location_name(): void
    {
        $this->grantPermission('locations.view');

        $locZ = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Я-локация',
            'is_enabled' => true,
        ]);
        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'А-локация',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Z-lead',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $locZ->id,
        ]);
        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'A-lead',
            'phone'       => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $locA->id,
        ]);

        $columns = [
            ['data' => 'id'],
            ['data' => 'name'],
            ['data' => 'phone'],
            ['data' => 'location_name'],
            ['data' => 'utm_summary'],
            ['data' => 'page_url'],
            ['data' => 'status_label'],
            ['data' => 'comment'],
        ];

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'order'   => [['column' => 3, 'dir' => 'asc']],
            'columns' => $columns,
        ]));

        $response->assertOk();
        $names = array_column($response->json('data'), 'location_name');
        $this->assertSame(['А-локация', 'Я-локация'], $names);
    }

    public function test_datatable_ignores_location_filter_without_locations_view(): void
    {
        $this->actorWithSchoolLeadsOnly();

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'С локацией',
            'phone'       => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $loc->id,
        ]);
        SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Без локации',
            'phone'      => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'location_id' => (string) $loc->id,
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $firstRow = $response->json('data.0');
        $this->assertIsArray($firstRow);
        $this->assertArrayNotHasKey('location_name', $firstRow);
        $this->assertArrayNotHasKey('location_id', $firstRow);
    }

    public function test_update_school_lead_location(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Центр',
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'location_id' => $location->id,
        ])
            ->assertOk()
            ->assertJson([
                'location_id'   => $location->id,
                'location_name' => 'Центр',
            ]);

        $lead->refresh();
        $this->assertEquals($location->id, $lead->location_id);
    }

    public function test_update_clears_location(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Иван',
            'phone'       => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id' => $location->id,
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'location_id' => null,
        ])
            ->assertOk()
            ->assertJson([
                'location_id'   => null,
                'location_name' => null,
            ]);

        $lead->refresh();
        $this->assertNull($lead->location_id);
    }

    public function test_update_ignores_location_without_locations_view(): void
    {
        $this->actorWithSchoolLeadsOnly();

        $foreignLoc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'location_id' => $foreignLoc->id,
            'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
        ])->assertOk();

        $lead->refresh();
        $this->assertNull($lead->location_id);
        $this->assertSame($this->schoolLeadProcessingStatusId(), (int) $lead->school_lead_status_id);
    }

    public function test_update_rejects_disabled_location(): void
    {
        $this->grantPermission('locations.view');

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => false,
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'location_id' => $location->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_update_rejects_foreign_partner_location(): void
    {
        $this->grantPermission('locations.view');

        $foreignLoc = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'location_id' => $foreignLoc->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_columns_settings_save_and_load_all_column_keys(): void
    {
        $this->grantPermission('locations.view');

        $payload = [
            'name'     => true,
            'phone'    => true,
            'location' => false,
            'utm'      => true,
            'page_url' => false,
            'status'   => true,
            'comment'  => false,
            'actions'  => true,
        ];

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJson([]);

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => $payload,
        ])->assertOk();

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJson($payload);
    }

    public function test_columns_settings_work_without_locations_view(): void
    {
        $this->actorWithSchoolLeadsOnly();

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'   => true,
                'phone'  => true,
                'status' => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.school-leads.columns-settings.get'))
            ->assertOk()
            ->assertJsonFragment(['name' => true, 'phone' => true]);
    }
}
