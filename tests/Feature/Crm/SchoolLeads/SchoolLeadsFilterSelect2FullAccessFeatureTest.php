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
 * Полный доступ: страница фильтров и GET data с team_ids[] / location_ids[] → 200, не 500.
 */
final class SchoolLeadsFilterSelect2FullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function actingAsViewerWith(string ...$permissions): User
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        foreach ($permissions as $permission) {
            $this->grantPermission($actor, $permission);
        }

        return $actor;
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    private function endpoints(Team $team, Location $location): array
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
                    'draw'     => 1,
                    'start'    => 0,
                    'length'   => 10,
                    'team_ids' => [$team->id, 'none'],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'         => 1,
                    'start'        => 0,
                    'length'       => 10,
                    'location_ids' => [$location->id],
                ]),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.data', [
                    'draw'         => 1,
                    'start'        => 0,
                    'length'       => 10,
                    'team_ids'     => [$team->id],
                    'location_ids' => [$location->id, 'none'],
                ]),
            ],
        ];
    }

    public function test_guest_is_denied_on_filter_select2_endpoints(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        Auth::logout();

        foreach ($this->endpoints($team, $location) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
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

    public function test_viewer_with_school_leads_and_locations_view_all_filter_endpoints_return_200(): void
    {
        $this->actingAsViewerWith('schoolLeads.view', 'locations.view');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'FullAccessСекция',
            'is_enabled' => true,
        ]);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'FullAccessОбъект',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'FullAccessЛид',
            'phone'                 => '+7 900 611-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $team->id,
            'location_id'           => $location->id,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-team"', false)
            ->assertSee('name="team_ids[]"', false)
            ->assertSee('js-generic-multiselect-select', false)
            ->assertSee('id="sl-filter-location"', false)
            ->assertSee('name="location_ids[]"', false)
            ->assertSee('id="leadTeam"', false)
            ->assertSee('id="leadLocation"', false)
            ->assertSee('initLeadModalSingleSelect2', false);

        foreach ($this->endpoints($team, $location) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Viewer: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame('', trim((string) $response->getContent()));
        }
    }

    public function test_admin_filter_select2_endpoints_return_200(): void
    {
        $this->asAdmin();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        foreach ($this->endpoints($team, $location) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
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
}
