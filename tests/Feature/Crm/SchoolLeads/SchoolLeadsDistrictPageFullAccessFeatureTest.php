<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: заявки с районом/объектом — страница и endpoint'ы → 200 при schoolLeads.view + districts.view + locations.view.
 */
final class SchoolLeadsDistrictPageFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    private District $district;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->district = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'FA-район',
        ]);

        $this->location = Location::factory()->forDistrict($this->district)->create([
            'partner_id' => $this->partner->id,
            'name'       => 'FA-объект',
            'is_enabled' => true,
        ]);

        $this->lead = SchoolLead::query()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $this->district->id,
            'location_id' => $this->location->id,
            'name'        => 'FA лид иерархии',
            'phone'       => '+7 900 700-00-01',
            'status'      => 'new',
        ]);
    }

    public function test_admin_all_school_leads_district_endpoints_return_200(): void
    {
        $this->asAdmin();

        foreach ($this->districtAwareRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Админ: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_with_all_hierarchy_permissions_all_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'districts.view');
        $this->grantPermission($actor, 'locations.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-district"', false)
            ->assertSee('id="sl-filter-location"', false)
            ->assertSee('id="leadDistrict"', false)
            ->assertSee('id="leadLocation"', false)
            ->assertSee('FA-район', false)
            ->assertSee('FA-объект', false);

        foreach ($this->districtAwareRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Актор: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_guest_denied_on_district_aware_school_leads_endpoints(): void
    {
        Auth::logout();

        foreach ($this->districtAwareRoutesPayload() as $item) {
            $status = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            )->getStatusCode();

            $this->assertContains($status, [302, 401, 403, 419], "Гость: {$item['method']} {$item['url']} → {$status}");
        }
    }

    public function test_user_without_districts_view_gets_403_on_page_but_data_works_without_district_fields(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'locations.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertDontSee('id="sl-filter-district"', false)
            ->assertSee('id="sl-filter-location"', false);

        $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'district_id' => (string) $this->district->id,
        ]))->assertOk();
    }

    private function grantPermission(User $user, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function districtAwareRoutesPayload(): array
    {
        $deleteLead = SchoolLead::query()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $this->district->id,
            'location_id' => $this->location->id,
            'name'        => 'На удаление FA',
            'phone'       => '+7 900 700-00-99',
            'status'      => 'new',
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'        => 1,
                    'start'       => 0,
                    'length'      => 10,
                    'statuses'    => ['new', 'processing'],
                    'district_id' => (string) $this->district->id,
                    'location_id' => (string) $this->location->id,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'        => 1,
                    'start'       => 0,
                    'length'      => 10,
                    'district_id' => 'none',
                    'order'       => [['column' => 4, 'dir' => 'asc']],
                    'columns'     => [
                        ['data' => 'id'],
                        ['data' => 'name'],
                        ['data' => 'phone'],
                        ['data' => 'location_name'],
                        ['data' => 'district_name'],
                        ['data' => 'status_label'],
                    ],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.columns-settings.get'),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.columns-settings.save'),
                'data'   => [
                    'columns' => [
                        'name'     => true,
                        'phone'    => true,
                        'district' => true,
                        'location' => true,
                        'status'   => true,
                        'actions'  => true,
                    ],
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.update', ['schoolLead' => $this->lead->id]),
                'data'   => [
                    'status'      => 'processing',
                    'comment'     => 'FA district update',
                    'district_id' => $this->district->id,
                    'location_id' => $this->location->id,
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.school-leads.destroy', ['schoolLead' => $deleteLead->id]),
            ],
        ];
    }
}
