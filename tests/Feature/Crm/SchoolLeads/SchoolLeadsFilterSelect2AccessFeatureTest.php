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
 * Доступ к фильтрам секции/объекта на заявках: guest, без schoolLeads.view, без locations.view.
 */
final class SchoolLeadsFilterSelect2AccessFeatureTest extends CrmTestCase
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

    private function actingAsSchoolLeadsViewerWithoutLocations(): User
    {
        $actor = $this->createUserWithoutPermission('locations.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');

        return $actor;
    }

    /**
     * @return list<array{method: string, url: string}>
     */
    private function filterEndpoints(): array
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $location = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        return [
            ['GET', route('admin.school-leads')],
            ['GET', route('admin.school-leads.data', [
                'draw'     => 1,
                'start'    => 0,
                'length'   => 10,
                'team_ids' => [$team->id],
            ])],
            ['GET', route('admin.school-leads.data', [
                'draw'         => 1,
                'start'        => 0,
                'length'       => 10,
                'location_ids' => [$location->id, 'none'],
            ])],
        ];
    }

    public function test_guest_cannot_open_school_leads_or_filter_data(): void
    {
        Auth::logout();

        foreach ($this->filterEndpoints() as [$method, $url]) {
            $response = $this->call($method, $url);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$method} {$url} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(200, $response->getStatusCode());
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_manager_without_school_leads_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->filterEndpoints() as [$method, $url]) {
            $response = $this->call($method, $url, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolLeads.view: {$method} {$url} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_viewer_without_locations_view_does_not_see_location_filter(): void
    {
        $this->actingAsSchoolLeadsViewerWithoutLocations();

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertDontSee('id="sl-filter-location"', false)
            ->assertDontSee('name="location_ids[]"', false)
            ->assertDontSee('id="leadLocation"', false)
            ->assertSee('id="sl-filter-team"', false)
            ->assertSee('name="team_ids[]"', false)
            ->assertSee('KidsCrmGenericMultiselectSelect2.init', false);
    }

    public function test_viewer_without_locations_view_ignores_location_ids_on_datatable(): void
    {
        $this->actingAsSchoolLeadsViewerWithoutLocations();

        $locA = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'СкрытыйОбъектА',
            'is_enabled' => true,
        ]);
        $locB = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'СкрытыйОбъектБ',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ЛидСкрытыйА',
            'phone'                 => '+7 900 601-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $locA->id,
        ]);
        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ЛидСкрытыйБ',
            'phone'                 => '+7 900 601-22-22',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'location_id'           => $locB->id,
        ]);

        $response = $this->getJson(route('admin.school-leads.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'location_ids' => [$locA->id],
        ]));

        $response->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $this->assertNotSame(500, $response->getStatusCode());

        $names = array_column($response->json('data'), 'name');
        $this->assertContains('ЛидСкрытыйА', $names);
        $this->assertContains('ЛидСкрытыйБ', $names);
    }

    public function test_viewer_with_school_leads_view_can_filter_by_team_ids(): void
    {
        $this->actingAsSchoolLeadsViewerWithoutLocations();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ДоступСекция',
            'is_enabled' => true,
        ]);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'ДоступЛид',
            'phone'                 => '+7 900 602-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'team_id'               => $team->id,
        ]);

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="sl-filter-team"', false);

        $this->getJson(route('admin.school-leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'team_ids' => [$team->id],
        ]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'ДоступЛид']);
    }
}
