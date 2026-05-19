<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: страница и все endpoint'ы раздела «Заявки с сайта» отдают 200 при наличии schoolLeads.view.
 */
final class SchoolLeadsFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->lead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Доступный лид',
            'phone'      => '+7 900 111-11-11',
            'status'     => 'new',
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

    private function grantLocationsView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('locations.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function actingAsSchoolLeadsViewer(bool $withLocationsView = false): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        if ($withLocationsView) {
            $this->grantLocationsView($actor);
        }

        return $actor;
    }

    public function test_guest_is_denied_on_all_school_leads_endpoints(): void
    {
        Auth::logout();

        $routes = $this->authorizedRoutesPayload();

        foreach ($routes as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_permission_gets_403_on_all_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->authorizedRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolLeads.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_without_locations_permission_all_endpoints_return_200(): void
    {
        $this->actingAsSchoolLeadsViewer(withLocationsView: false);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('Заявки с сайта', false)
            ->assertDontSee('id="sl-filter-location"', false);

        $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'statuses' => ['new', 'processing'],
        ]))->assertOk();

        $this->getJson(route('admin.school-leads.columns-settings.get'))->assertOk();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'    => true,
                'phone'   => true,
                'utm'     => true,
                'status'  => true,
                'actions' => true,
            ],
        ])->assertOk();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'status'  => 'processing',
            'comment' => 'OK',
        ])->assertOk();

        $tempLead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'На удаление',
            'phone'      => '+7 900 999-99-99',
            'status'     => 'new',
        ]);

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $tempLead->id]))
            ->assertOk();
    }

    public function test_viewer_with_locations_permission_all_endpoints_return_200(): void
    {
        $this->actingAsSchoolLeadsViewer(withLocationsView: true);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Доступная локация',
            'is_enabled' => true,
        ]);

        $this->lead->update(['location_id' => $location->id]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-location"', false)
            ->assertSee('Доступная локация', false)
            ->assertSee('id="leadLocation"', false);

        $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'statuses'    => ['new'],
            'location_id' => (string) $location->id,
            'search'      => ['value' => 'Доступный'],
        ]))->assertOk();

        $this->getJson(route('admin.school-leads.data', [
            'draw'        => 1,
            'start'       => 0,
            'length'      => 10,
            'location_id' => 'none',
            'order'       => [['column' => 3, 'dir' => 'asc']],
            'columns'     => [
                ['data' => 'id'],
                ['data' => 'name'],
                ['data' => 'phone'],
                ['data' => 'location_name'],
                ['data' => 'utm_summary'],
                ['data' => 'page_url'],
                ['data' => 'status_label'],
                ['data' => 'comment'],
            ],
        ]))->assertOk();

        $this->postJson(route('admin.school-leads.columns-settings.save'), [
            'columns' => [
                'name'     => true,
                'phone'    => true,
                'location' => true,
                'utm'      => false,
                'page_url' => true,
                'status'   => true,
                'comment'  => false,
                'actions'  => true,
            ],
        ])->assertOk();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'status'      => 'processing',
            'comment'     => 'С локацией',
            'location_id' => $location->id,
        ])->assertOk();

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $this->lead->id]), [
            'location_id' => null,
        ])->assertOk();
    }

    public function test_admin_all_school_leads_endpoints_return_200(): void
    {
        $this->asAdmin();

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        foreach ($this->authorizedRoutesPayload($location->id) as $item) {
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

    public function test_foreign_partner_lead_endpoints_return_not_found(): void
    {
        $this->asAdmin();

        $foreignLead = SchoolLead::create([
            'partner_id' => $this->foreignPartner->id,
            'name'       => 'Чужой',
            'phone'      => '+7 900 000-00-00',
            'status'     => 'new',
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $foreignLead->id]), [
            'status' => 'spam',
        ])->assertNotFound();

        $this->deleteJson(route('admin.school-leads.destroy', ['schoolLead' => $foreignLead->id]))
            ->assertNotFound();
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function authorizedRoutesPayload(?int $locationId = null): array
    {
        $locationId ??= Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ])->id;

        $deleteLead = SchoolLead::create([
            'partner_id' => $this->partner->id,
            'name'       => 'Удалить',
            'phone'      => '+7 900 555-55-55',
            'status'     => 'new',
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
                    'location_id' => (string) $locationId,
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
                    'comment'     => 'Smoke',
                    'location_id' => $locationId,
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.school-leads.destroy', ['schoolLead' => $deleteLead->id]),
            ],
        ];
    }
}
