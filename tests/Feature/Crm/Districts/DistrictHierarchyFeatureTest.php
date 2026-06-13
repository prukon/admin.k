<?php

namespace Tests\Feature\Crm\Districts;

use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\TeamLocationSyncService;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Функциональное покрытие иерархии «Район → Объект → заявка / лендинг».
 */
final class DistrictHierarchyFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
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

    public function test_district_data_search_by_name_and_numeric_id(): void
    {
        $this->grantPermission('districts.view');

        $target = District::factory()->forPartner($this->partner->id)->create(['name' => 'Кудрово']);
        District::factory()->forPartner($this->partner->id)->create(['name' => 'Мурино']);

        $this->getJson(route('admin.districts.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'name'   => 'Кудр',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Кудрово');

        $this->getJson(route('admin.districts.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'search' => ['value' => (string) $target->id],
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_district_data_sorts_by_sort_order_locations_count_and_is_enabled(): void
    {
        $this->grantPermission('districts.view');

        $low = District::factory()->forPartner($this->partner->id)->create([
            'name'       => 'A-low',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $high = District::factory()->forPartner($this->partner->id)->create([
            'name'       => 'B-high',
            'sort_order' => 9,
            'is_enabled' => false,
        ]);

        Location::factory()->forDistrict($high)->create(['name' => 'Obj 1']);
        Location::factory()->forDistrict($high)->create(['name' => 'Obj 2']);

        $sortOrder = $this->getJson(route('admin.districts.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'order'   => [['column' => 0, 'dir' => 'asc']],
            'columns' => [['name' => 'sort_order']],
        ]))->assertOk();

        $this->assertSame('A-low', $sortOrder->json('data.0.name'));

        $locationsCount = $this->getJson(route('admin.districts.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'order'   => [['column' => 0, 'dir' => 'desc']],
            'columns' => [['name' => 'locations_count']],
        ]))->assertOk();

        $this->assertSame('B-high', $locationsCount->json('data.0.name'));
        $this->assertSame(2, $locationsCount->json('data.0.locations_count'));

        $enabled = $this->getJson(route('admin.districts.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'order'   => [['column' => 0, 'dir' => 'desc']],
            'columns' => [['name' => 'is_enabled_label']],
        ]))->assertOk();

        $this->assertSame('A-low', $enabled->json('data.0.name'));
        $this->assertSame($low->id, $enabled->json('data.0.id'));
    }

    public function test_destroy_district_without_locations_nulls_district_id_on_school_leads(): void
    {
        $this->grantPermission('districts.view');

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Удаляемый']);

        $lead = SchoolLead::query()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'name'        => 'Лид с районом',
            'phone'       => '+7 900 100-00-01',
            'status'      => 'new',
        ]);

        $this->deleteJson(route('admin.districts.destroy', $district->id))->assertOk();

        $this->assertDatabaseMissing('districts', ['id' => $district->id]);
        $this->assertNull($lead->fresh()->district_id);
    }

    public function test_location_crud_with_district_id_roundtrip(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $districtA = District::factory()->forPartner($this->partner->id)->create(['name' => 'Район A']);
        $districtB = District::factory()->forPartner($this->partner->id)->create(['name' => 'Район B']);

        $create = $this->postJson(route('admin.locations.store'), [
            'name'        => 'Объект иерархии',
            'district_id' => $districtA->id,
            'is_enabled'  => 1,
        ])->assertOk();

        $locationId = (int) ($create->json('location.id') ?? 0);
        $this->assertGreaterThan(0, $locationId);

        $this->getJson(route('admin.locations.show', $locationId))
            ->assertOk()
            ->assertJsonPath('district_id', $districtA->id);

        $this->putJson(route('admin.locations.update', $locationId), [
            'name'        => 'Объект иерархии v2',
            'district_id' => $districtB->id,
            'is_enabled'  => 1,
        ])->assertOk();

        $this->assertDatabaseHas('locations', [
            'id'          => $locationId,
            'district_id' => $districtB->id,
            'name'        => 'Объект иерархии v2',
        ]);
    }

    public function test_location_data_filters_and_sorts_by_district(): void
    {
        $this->grantPermission('locations.view');

        $districtA = District::factory()->forPartner($this->partner->id)->create(['name' => 'Я-район']);
        $districtB = District::factory()->forPartner($this->partner->id)->create(['name' => 'А-район']);

        Location::factory()->forDistrict($districtA)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'В Я',
        ]);
        Location::factory()->forDistrict($districtB)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'В А',
        ]);
        Location::factory()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => null,
            'name'        => 'Без района',
        ]);

        $this->getJson(route('admin.locations.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'district_id' => (string) $districtB->id,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.district_name', 'А-район');

        $this->getJson(route('admin.locations.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'district_id' => 'none',
        ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Без района');

        $sorted = $this->getJson(route('admin.locations.data', [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'order'   => [['column' => 0, 'dir' => 'asc']],
            'columns' => [['name' => 'district_name']],
        ]))->assertOk();

        $names = array_column($sorted->json('data'), 'district_name');
        $this->assertCount(3, $names);
        $this->assertSame(['А-район', 'Я-район'], array_values(array_filter($names)));
        $this->assertContains('', $names);
    }

    public function test_landing_hierarchy_endpoints_return_200_for_enabled_chain(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'hierarchy-access']);
        $slug = (string) $widget->fresh()->landing_slug;

        $district = District::factory()->forPartner($this->partner->id)->create(['name' => 'Лендинг-район']);
        $location = Location::query()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'name'        => 'Лендинг-объект',
            'is_enabled'  => true,
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Лендинг-услуга',
            'is_enabled' => true,
        ]);
        app(TeamLocationSyncService::class)->syncTeamsForLocation($location, [(int) $team->id]);

        $this->get(route('lead.show', ['landingSlug' => $slug]))->assertOk();

        $this->getJson(route('lead.locations', [
            'landingSlug' => $slug,
            'district_id' => $district->id,
        ]))->assertOk()->assertJsonPath('data.0.id', $location->id);

        $this->getJson(route('lead.teams', [
            'landingSlug' => $slug,
            'location_id' => $location->id,
        ]))->assertOk()->assertJsonPath('data.0.id', $team->id);

        $this->getJson(route('lead.team-info', [
            'landingSlug' => $slug,
            'location_id' => $location->id,
            'team_id'     => $team->id,
        ]))->assertOk()->assertJsonStructure(['data']);

        $this->postJson(route('lead.submit', ['landingSlug' => $slug]), [
            'parent_lastname'   => 'Иванов',
            'parent_firstname'  => 'Иван',
            'parent_middlename' => 'Иванович',
            'parent_phone'      => '+7 999 111-22-33',
            'parent_email'      => 'parent@example.com',
            'child_lastname'    => 'Иванов',
            'child_firstname'   => 'Пётр',
            'child_middlename'  => 'Иванович',
            'child_birthday'    => '2018-05-10',
            'district_id'       => $district->id,
            'location_id'       => $location->id,
            'team_id'           => $team->id,
            'consent_accepted'  => '1',
            'recaptcha_token'   => 'fake-token',
        ])->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'location_id' => $location->id,
            'team_id'     => $team->id,
        ]);
    }

    public function test_disabled_district_excluded_from_landing_but_storable_on_location_via_api(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $disabled = District::factory()->forPartner($this->partner->id)->disabled()->create(['name' => 'Выключен']);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'disabled-district-test']);
        $slug = (string) $widget->fresh()->landing_slug;

        $this->get(route('lead.show', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertDontSee('Выключен', false);

        $this->postJson(route('admin.locations.store'), [
            'name'        => 'С выключенным районом',
            'district_id' => $disabled->id,
            'is_enabled'  => 1,
        ])->assertOk();

        $this->assertDatabaseHas('locations', [
            'partner_id'  => $this->partner->id,
            'district_id' => $disabled->id,
            'name'        => 'С выключенным районом',
        ]);
    }
}
