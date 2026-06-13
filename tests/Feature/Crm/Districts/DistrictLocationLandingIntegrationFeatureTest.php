<?php

namespace Tests\Feature\Crm\Districts;

use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\TeamLocationSyncService;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Сквозной сценарий: район → объект → лендинг → заявка в CRM.
 */
final class DistrictLocationLandingIntegrationFeatureTest extends CrmTestCase
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
    }

    public function test_full_chain_district_location_landing_and_crm_lead(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $this->asAdmin();

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'integration-landing']);
        $slug = (string) $widget->fresh()->landing_slug;

        $district = District::factory()->forPartner($this->partner->id)->create([
            'name'       => 'Северный',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $location = Location::query()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $district->id,
            'name'        => 'ДЮСШ «Север»',
            'is_enabled'  => true,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Футбол',
            'is_enabled' => true,
        ]);

        app(TeamLocationSyncService::class)->syncTeamsForLocation($location, [(int) $team->id]);

        $this->get(route('lead.show', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertSee('Северный', false)
            ->assertSee('id="district_id"', false);

        $this->getJson(route('lead.locations', [
            'landingSlug' => $slug,
            'district_id' => $district->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $location->id)
            ->assertJsonPath('data.0.name', 'ДЮСШ «Север»');

        $this->getJson(route('lead.teams', [
            'landingSlug' => $slug,
            'location_id' => $location->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $team->id);

        $this->postJson(route('lead.submit', ['landingSlug' => $slug]), [
            'parent_lastname'   => 'Петров',
            'parent_firstname'  => 'Пётр',
            'parent_middlename' => 'Петрович',
            'parent_phone'      => '+7 999 555-66-77',
            'parent_email'      => 'petrov@example.com',
            'child_lastname'    => 'Петров',
            'child_firstname'   => 'Иван',
            'child_middlename'  => 'Петрович',
            'child_birthday'    => '2017-03-15',
            'district_id'       => $district->id,
            'location_id'       => $location->id,
            'team_id'           => $team->id,
            'consent_accepted'  => '1',
            'recaptcha_token'   => 'fake-token',
        ])->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($district->id, $lead->district_id);
        $this->assertSame($location->id, $lead->location_id);
        $this->assertSame($team->id, $lead->team_id);

        $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.district_name', 'Северный')
            ->assertJsonPath('data.0.location_name', 'ДЮСШ «Север»');
    }

    public function test_object_without_district_not_on_landing_but_visible_in_crm_locations(): void
    {
        $this->asAdmin();

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['landing_slug' => 'crm-only-objects']);
        $slug = (string) $widget->fresh()->landing_slug;

        $withDistrict = District::factory()->forPartner($this->partner->id)->create([
            'name' => 'Видимый',
        ]);

        Location::query()->create([
            'partner_id'  => $this->partner->id,
            'district_id' => $withDistrict->id,
            'name'        => 'На лендинге',
            'is_enabled'  => true,
        ]);

        Location::query()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Без района CRM-only',
            'is_enabled' => true,
        ]);

        $this->get(route('lead.show', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertSee('Видимый', false)
            ->assertDontSee('Без района CRM-only', false);

        $this->getJson(route('admin.locations.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2);
    }
}
