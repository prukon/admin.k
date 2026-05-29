<?php

namespace Tests\Feature\Public;

use App\Enums\SchoolLeadSource;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Services\LocationTeamSyncService;
use App\Services\PartnerWidgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Полное покрытие функционала брендированной страницы заявки партнёра.
 */
final class SchoolLeadLandingFullFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
        Notification::fake();
    }

    public function test_landing_page_shows_partner_and_form_sections(): void
    {
        $response = $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]));

        $response->assertOk()
            ->assertSee('Записаться', false)
            ->assertSee('на регулярные тренировочные занятия', false)
            ->assertSee('Детская школа «Радуга»', false)
            ->assertSee('Законный представитель', false)
            ->assertSee('Ребёнок', false)
            ->assertSee('Район и услуга', false)
            ->assertSee('Центральный', false)
            ->assertSee($this->landingWidget->landing_key, false)
            ->assertSee('id="leadForm"', false)
            ->assertSee('id="location_id"', false)
            ->assertSee('id="team_id"', false);
    }

    public function test_display_name_uses_organization_name_when_title_empty(): void
    {
        $this->landingPartner->update([
            'title'             => '',
            'organization_name' => 'ООО Спорт Кids',
        ]);

        $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertOk()
            ->assertSee('ООО Спорт Кids', false);
    }

    public function test_show_returns_404_for_unknown_landing_key(): void
    {
        $this->get(route('lead.show', ['landingKey' => str_repeat('a', 48)]))
            ->assertNotFound();
    }

    public function test_show_returns_404_when_landing_inactive(): void
    {
        $this->landingWidget->update(['is_landing_active' => false]);

        $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertNotFound();
    }

    public function test_teams_returns_404_for_unknown_landing_key(): void
    {
        $this->getJson(route('lead.teams', [
            'landingKey'  => str_repeat('b', 48),
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertNotFound();
    }

    public function test_submit_returns_404_for_unknown_landing_key(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingKey' => str_repeat('c', 48)]),
            $this->validLandingPayload()
        )
            ->assertNotFound();
    }

    public function test_teams_returns_422_without_location_id(): void
    {
        $this->getJson(route('lead.teams', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_teams_returns_empty_for_foreign_partner_location(): void
    {
        $foreignPartner = Partner::factory()->create();
        $foreignLocation = Location::query()->create([
            'partner_id' => $foreignPartner->id,
            'name'       => 'Чужой район',
            'is_enabled' => true,
        ]);

        $this->getJson(route('lead.teams', [
            'landingKey'  => $this->landingWidget->landing_key,
            'location_id' => $foreignLocation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_show_lists_only_enabled_locations(): void
    {
        Location::query()->create([
            'partner_id' => $this->landingPartner->id,
            'name'       => 'Скрытый район',
            'is_enabled' => false,
        ]);

        $html = $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Центральный', $html);
        $this->assertStringNotContainsString('Скрытый район', $html);
    }

    public function test_teams_endpoint_returns_teams_for_location(): void
    {
        $this->getJson(route('lead.teams', [
            'landingKey'  => $this->landingWidget->landing_key,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->landingTeam->id)
            ->assertJsonPath('data.0.title', 'Плавание');
    }

    public function test_submit_creates_landing_school_lead_with_all_core_fields(): void
    {
        $this->fakeRecaptchaSuccess();

        $response = $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload([
                'comment'                => 'Удобно по вторникам',
                'is_individual_traits'   => '1',
                'is_on_medical_register' => '1',
                'is_with_disability'     => '0',
                'utm_source'             => 'vk',
                'utm_campaign'           => 'spring',
                'page_url'               => 'https://example.com/promo',
                'referrer'               => 'https://vk.com/',
            ])
        );

        $response->assertOk()->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('school_leads', [
            'partner_id'             => $this->landingWidget->partner_id,
            'partner_widget_id'      => $this->landingWidget->id,
            'source'                 => SchoolLeadSource::Landing->value,
            'parent_lastname'        => 'Иванов',
            'parent_email'           => 'parent@example.com',
            'child_firstname'        => 'Пётр',
            'location_id'            => $this->landingLocation->id,
            'team_id'                => $this->landingTeam->id,
            'needs_contact_help'     => 0,
            'comment'                => 'Удобно по вторникам',
            'is_individual_traits'   => 1,
            'is_on_medical_register' => 1,
            'is_with_disability'     => 0,
            'utm_source'             => 'vk',
            'utm_campaign'           => 'spring',
            'page_url'               => 'https://example.com/promo',
            'referrer'               => 'https://vk.com/',
        ]);

        $lead = SchoolLead::first();
        $this->assertNotNull($lead);
        $this->assertNotNull($lead->consent_accepted_at);
        $this->assertNull($lead->policy_url);
        $this->assertSame('Иванов Иван Иванович', $lead->name);
        $this->assertSame('+7 999 111-22-33', $lead->phone);
    }

    public function test_submit_without_team_when_needs_contact_help(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload([
            'needs_contact_help' => '1',
        ]);
        unset($payload['team_id']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id'         => $this->landingWidget->partner_id,
            'needs_contact_help' => 1,
            'team_id'            => null,
        ]);
    }

    public function test_submit_without_team_id_is_allowed(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload();
        unset($payload['team_id']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id' => $this->landingWidget->partner_id,
            'team_id'    => null,
        ]);
    }

    public function test_submit_requires_location(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload();
        unset($payload['location_id']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_submit_rejects_location_from_another_partner(): void
    {
        $this->fakeRecaptchaSuccess();

        $foreignPartner = Partner::factory()->create();
        $foreignLocation = Location::query()->create([
            'partner_id' => $foreignPartner->id,
            'name'       => 'Чужой',
            'is_enabled' => true,
        ]);

        $payload = $this->validLandingPayload([
            'location_id' => $foreignLocation->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_submit_rejects_team_not_available_at_location(): void
    {
        $this->fakeRecaptchaSuccess();

        $otherLocation = Location::query()->create([
            'partner_id' => $this->landingPartner->id,
            'name'       => 'Северный',
            'is_enabled' => true,
        ]);

        $otherTeam = Team::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'title'      => 'Футбол',
            'is_enabled' => true,
        ]);

        app(LocationTeamSyncService::class)->syncTeamsForLocation(
            $otherLocation,
            [(int) $otherTeam->id],
        );

        $payload = $this->validLandingPayload([
            'location_id' => $this->landingLocation->id,
            'team_id'     => $otherTeam->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_submit_rejects_team_from_another_partner(): void
    {
        $this->fakeRecaptchaSuccess();

        $foreignPartner = Partner::factory()->create();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $foreignPartner->id,
            'is_enabled' => true,
        ]);

        $payload = $this->validLandingPayload([
            'team_id' => $foreignTeam->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_submit_validates_required_parent_fields(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload();
        unset($payload['parent_lastname'], $payload['parent_email']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_lastname', 'parent_email']);
    }

    public function test_submit_validates_required_child_fields(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload([
            'child_birthday' => now()->format('Y-m-d'),
        ]);
        unset($payload['child_firstname']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['child_firstname', 'child_birthday']);
    }

    public function test_submit_fails_without_consent(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload();
        unset($payload['consent_accepted']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['consent_accepted']);
    }

    public function test_submit_returns_field_errors_for_invalid_phone(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload(['parent_phone' => '12'])
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_phone']);
    }

    public function test_submit_fails_without_recaptcha_token(): void
    {
        $payload = $this->validLandingPayload();
        unset($payload['recaptcha_token']);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recaptcha_token']);
    }

    public function test_submit_fails_when_recaptcha_score_is_too_low(): void
    {
        $this->fakeRecaptchaLowScore();

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload()
        )
            ->assertStatus(422);
    }

    public function test_partner_widget_has_landing_key_after_provisioning(): void
    {
        $partner = Partner::factory()->create();

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $partner->id);

        $this->assertNotNull($widget->landing_key);
        $this->assertSame(48, strlen((string) $widget->landing_key));
        $this->assertTrue($widget->is_landing_active);
        $this->assertDatabaseHas('partner_widgets', [
            'partner_id'  => $partner->id,
            'landing_key' => $widget->landing_key,
        ]);
    }

    public function test_submit_returns_404_when_landing_inactive(): void
    {
        $this->fakeRecaptchaSuccess();
        $this->landingWidget->update(['is_landing_active' => false]);

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload()
        )
            ->assertNotFound();
    }
}
