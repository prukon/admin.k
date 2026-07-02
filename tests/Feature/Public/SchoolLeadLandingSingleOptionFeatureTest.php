<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\SchoolLeadSource;
use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\TeamLocationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Автовыбор и блокировка единственного района / объекта / услуги на публичной странице заявки.
 */
final class SchoolLeadLandingSingleOptionFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_landing_page_with_single_district_includes_auto_select_script(): void
    {
        $html = $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('autoSelectSingleDistrict', $html);
        $this->assertStringContainsString('lockSelectWithSingleItem', $html);
        $this->assertStringContainsString('appendLockedSelectValues', $html);
        $this->assertStringContainsString('districtLocked', $html);
        $this->assertStringContainsString('locationLocked', $html);
        $this->assertStringContainsString('teamLocked', $html);
        $this->assertStringContainsString('#district_id:disabled', $html);
        $this->assertStringContainsString('value="' . $this->landingDistrict->id . '"', $html);
        $this->assertSame(1, substr_count($html, '<option value="' . $this->landingDistrict->id . '">'));
    }

    public function test_landing_page_with_multiple_districts_lists_all_enabled_options(): void
    {
        $secondDistrict = District::factory()->forPartner((int) $this->landingPartner->id)->create([
            'name' => 'Северный',
        ]);

        Location::query()->create([
            'partner_id'  => $this->landingPartner->id,
            'district_id' => $secondDistrict->id,
            'name'        => 'Школа «Север»',
            'is_enabled'  => true,
        ]);

        $html = $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Центральный', $html);
        $this->assertStringContainsString('Северный', $html);
        $this->assertStringContainsString('autoSelectSingleDistrict()', $html);
    }

    public function test_locations_endpoint_returns_single_item_for_district_with_one_object(): void
    {
        $this->getJson(route('lead.locations', [
            'landingSlug' => $this->landingWidget->landing_slug,
            'district_id' => $this->landingDistrict->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->landingLocation->id)
            ->assertJsonPath('data.0.name', 'Школа «Радуга»');
    }

    public function test_locations_endpoint_returns_multiple_items_when_district_has_several_objects(): void
    {
        Location::query()->create([
            'partner_id'  => $this->landingPartner->id,
            'district_id' => $this->landingDistrict->id,
            'name'        => 'Школа «Восток»',
            'is_enabled'  => true,
        ]);

        $this->getJson(route('lead.locations', [
            'landingSlug' => $this->landingWidget->landing_slug,
            'district_id' => $this->landingDistrict->id,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_teams_endpoint_returns_single_item_for_location_with_one_service(): void
    {
        $this->getJson(route('lead.teams', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->landingTeam->id)
            ->assertJsonPath('data.0.title', 'Плавание');
    }

    public function test_teams_endpoint_returns_multiple_items_when_location_has_several_services(): void
    {
        $secondTeam = Team::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'title'      => 'Футбол',
            'is_enabled' => true,
        ]);

        app(TeamLocationSyncService::class)->syncTeamsForLocation(
            $this->landingLocation,
            [(int) $this->landingTeam->id, (int) $secondTeam->id],
        );

        $this->getJson(route('lead.teams', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_submit_creates_lead_for_partner_with_single_district_object_and_service(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'single-option@example.com',
            ])
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('school_leads', [
            'partner_id'      => $this->landingPartner->id,
            'source'          => SchoolLeadSource::Landing->value,
            'district_id'     => $this->landingDistrict->id,
            'location_id'     => $this->landingLocation->id,
            'team_id'         => $this->landingTeam->id,
            'parent_email'    => 'single-option@example.com',
            'needs_contact_help' => 0,
        ]);

        $lead = SchoolLead::query()
            ->where('parent_email', 'single-option@example.com')
            ->first();

        $this->assertNotNull($lead);
    }

    public function test_submit_with_needs_contact_help_omits_team_for_single_service_partner(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email'       => 'help-single@example.com',
                'needs_contact_help' => '1',
                'team_id'            => null,
            ])
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('school_leads', [
            'parent_email'       => 'help-single@example.com',
            'district_id'        => $this->landingDistrict->id,
            'location_id'        => $this->landingLocation->id,
            'team_id'            => null,
            'needs_contact_help' => 1,
        ]);
    }
}
