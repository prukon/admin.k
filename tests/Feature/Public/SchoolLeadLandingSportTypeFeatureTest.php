<?php

namespace Tests\Feature\Public;

use App\Models\SchoolLead;
use App\Models\SportType;
use App\Models\Team;
use App\Services\LocationTeamSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

final class SchoolLeadLandingSportTypeFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    private SportType $landingSportType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
        Notification::fake();

        $this->landingSportType = SportType::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'name' => 'Плавание вид',
        ]);

        $this->landingTeam->update(['sport_type_id' => $this->landingSportType->id]);
    }

    public function test_landing_does_not_show_sport_type_select(): void
    {
        $html = $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertOk()
            ->assertSee('Район и услуга', false)
            ->getContent();

        $this->assertStringNotContainsString('id="sport_type_id"', $html);
        $this->assertStringNotContainsString('Плавание вид', $html);
    }

    public function test_teams_endpoint_returns_all_teams_for_location(): void
    {
        $otherSport = SportType::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'name' => 'Футбол вид',
        ]);

        $otherTeam = Team::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'title' => 'Футбол группа',
            'is_enabled' => true,
            'sport_type_id' => $otherSport->id,
        ]);

        app(LocationTeamSyncService::class)->syncTeamsForLocation(
            $this->landingLocation,
            [(int) $this->landingTeam->id, (int) $otherTeam->id],
        );

        $this->getJson(route('lead.teams', [
            'landingSlug' => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_team_info_shows_sport_type(): void
    {
        $this->getJson(route('lead.team-info', [
            'landingSlug' => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
            'team_id' => $this->landingTeam->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.rows.2.label', 'Вид спорта')
            ->assertJsonPath('data.rows.2.value', 'Плавание вид');
    }

    public function test_submit_stores_sport_type_id_from_selected_team(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id' => $this->landingWidget->partner_id,
            'sport_type_id' => $this->landingSportType->id,
            'team_id' => $this->landingTeam->id,
        ]);
    }

    public function test_submit_without_team_has_null_sport_type_id(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload([
            'team_id' => null,
            'needs_contact_help' => '1',
        ]);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->sport_type_id);
    }
}
