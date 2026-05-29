<?php

namespace Tests\Feature\Public;

use App\Models\Location;
use App\Models\Partner;
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

    public function test_landing_shows_sport_type_select_when_sport_types_exist(): void
    {
        $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertOk()
            ->assertSee('Район, вид спорта и услуга', false)
            ->assertSee('id="sport_type_id"', false)
            ->assertSee('Плавание вид', false);
    }

    public function test_landing_hides_sport_type_select_when_no_sport_types(): void
    {
        SportType::query()->where('partner_id', $this->landingPartner->id)->delete();

        $html = $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="sport_type_id"', $html);
    }

    public function test_teams_endpoint_filters_by_sport_type_id(): void
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
            'landingKey' => $this->landingWidget->landing_key,
            'location_id' => $this->landingLocation->id,
            'sport_type_id' => $this->landingSportType->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->landingTeam->id);

        $this->getJson(route('lead.teams', [
            'landingKey' => $this->landingWidget->landing_key,
            'location_id' => $this->landingLocation->id,
            'sport_type_id' => $otherSport->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $otherTeam->id);
    }

    public function test_submit_stores_sport_type_id_on_school_lead(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload([
                'sport_type_id' => $this->landingSportType->id,
            ])
        )->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id' => $this->landingWidget->partner_id,
            'sport_type_id' => $this->landingSportType->id,
            'team_id' => $this->landingTeam->id,
        ]);
    }

    public function test_submit_rejects_foreign_partner_sport_type(): void
    {
        $this->fakeRecaptchaSuccess();

        $foreignSport = SportType::factory()->create([
            'partner_id' => Partner::factory()->create()->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload([
                'sport_type_id' => $foreignSport->id,
            ])
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sport_type_id']);
    }

    public function test_submit_rejects_disabled_sport_type(): void
    {
        $this->fakeRecaptchaSuccess();

        $disabled = SportType::factory()->disabled()->create([
            'partner_id' => $this->landingPartner->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload([
                'sport_type_id' => $disabled->id,
            ])
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sport_type_id']);
    }

    public function test_submit_rejects_team_not_matching_selected_sport_type(): void
    {
        $this->fakeRecaptchaSuccess();

        $otherSport = SportType::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'name' => 'Другой вид',
        ]);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload([
                'sport_type_id' => $otherSport->id,
                'team_id' => $this->landingTeam->id,
            ])
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_submit_without_sport_type_id_is_allowed(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload();
        unset($payload['sport_type_id']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->sport_type_id);
    }
}
