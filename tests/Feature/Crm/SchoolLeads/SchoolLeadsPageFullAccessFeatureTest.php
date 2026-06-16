<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа: вкладка «Заявки» (/admin/school-leads) и все её endpoint'ы —
 * 200 при schoolLeads.view, отказ для гостя и без права.
 */
final class SchoolLeadsPageFullAccessFeatureTest extends CrmTestCase
{
    private SchoolLead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Page access',
            'phone'                 => '+7 900 222-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
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

    private function actingAsViewer(): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantSchoolLeadsView($actor);

        return $actor;
    }

    public function test_guest_is_denied_on_all_leads_page_endpoints(): void
    {
        Auth::logout();

        foreach ($this->routesPayload() as $item) {
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

        foreach ($this->routesPayload() as $item) {
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

    public function test_viewer_with_school_leads_view_all_endpoints_return_200(): void
    {
        $this->actingAsViewer();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false)
            ->assertSee('id="sl-filter-status"', false)
            ->assertSee('js-filter-multiselect-select', false)
            ->assertSee('KidsCrmFilterMultiselectSelect2', false)
            ->assertSee('id="schoolLeadStatusesModal"', false)
            ->assertSee('schoolLeadStatusRoutes', false);

        foreach ($this->routesPayload() as $item) {
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
                "Viewer: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_all_leads_page_endpoints_return_200(): void
    {
        $this->asAdmin();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-status"', false)
            ->assertSee('KidsCrmFilterMultiselectSelect2', false);

        foreach ($this->routesPayload() as $item) {
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function routesPayload(): array
    {
        $deleteLead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Denied delete',
            'phone'                 => '+7 900 444-44-44',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        return array_merge($this->readOnlyRoutesPayload($location, $team), [
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.columns-settings.save'),
                'data'   => ['columns' => ['name' => true, 'phone' => true, 'status' => true]],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.update', ['schoolLead' => $this->lead->id]),
                'data'   => [
                    'school_lead_status_id' => $this->schoolLeadProcessingStatusId(),
                    'comment'               => 'access smoke',
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.school-leads.destroy', ['schoolLead' => $deleteLead->id]),
            ],
        ], $this->schoolLeadStatusManagementRoutesPayload());
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function readOnlyRoutesPayload(Location $location, Team $team): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'   => 1,
                    'start'  => 0,
                    'length' => 10,
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'       => 1,
                    'start'      => 0,
                    'length'     => 10,
                    'status_ids' => [
                        $this->schoolLeadSystemStatusId(),
                        $this->schoolLeadProcessingStatusId(),
                    ],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'                     => 1,
                    'start'                    => 0,
                    'length'                   => 10,
                    'status_ids'               => [$this->schoolLeadSystemStatusId()],
                    'location_id'              => (string) $location->id,
                    'team_id'                  => (string) $team->id,
                    'has_special_conditions'   => '1',
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.columns-settings.get'),
            ],
            [
                'method' => 'GET',
                'url'    => route('logs.data.school-lead', ['draw' => 1, 'start' => 0, 'length' => 10]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.statuses.index'),
            ],
        ];
    }
}
