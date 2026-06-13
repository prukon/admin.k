<?php

namespace Tests\Feature\Public;

use App\Enums\SchoolLeadSource;
use App\Models\District;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Models\SportType;
use App\Models\Team;
use App\Services\LocationTeamSyncService;
use App\Services\PartnerWidgetService;
use Database\Seeders\WeekdaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $response = $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]));

        $response->assertOk()
            ->assertSee('Записаться', false)
            ->assertSee('на регулярные тренировочные занятия', false)
            ->assertSee('Детская школа «Радуга»', false)
            ->assertSee('Законный представитель', false)
            ->assertSee('Ребёнок', false)
            ->assertSee('Район, объект и услуга', false)
            ->assertSee('Центральный', false)
            ->assertSee('raduga-test', false)
            ->assertSee('id="leadForm"', false)
            ->assertSee('id="district_id"', false)
            ->assertSee('id="location_id"', false)
            ->assertSee('id="team_id"', false);
    }

    public function test_display_name_uses_organization_name_when_title_empty(): void
    {
        $this->landingPartner->update([
            'title'             => '',
            'organization_name' => 'ООО Спорт Кids',
        ]);

        $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertOk()
            ->assertSee('ООО Спорт Кids', false);
    }

    public function test_show_returns_404_for_unknown_landing_slug(): void
    {
        $this->get(route('lead.show', ['landingSlug' => 'unknown-landing-page']))
            ->assertNotFound();
    }

    public function test_show_returns_404_when_landing_inactive(): void
    {
        $this->landingWidget->update(['is_landing_active' => false]);

        $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertNotFound();
    }

    public function test_locations_returns_404_for_unknown_landing_slug(): void
    {
        $this->getJson(route('lead.locations', [
            'landingSlug' => 'unknown-landing-page',
            'district_id' => $this->landingDistrict->id,
        ]))
            ->assertNotFound();
    }

    public function test_teams_returns_404_for_unknown_landing_slug(): void
    {
        $this->getJson(route('lead.teams', [
            'landingSlug'  => 'unknown-landing-page',
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertNotFound();
    }

    public function test_submit_returns_404_for_unknown_landing_slug(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => 'unknown-landing-page']),
            $this->validLandingPayload()
        )
            ->assertNotFound();
    }

    public function test_locations_returns_422_without_district_id(): void
    {
        $this->getJson(route('lead.locations', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['district_id']);
    }

    public function test_locations_returns_empty_for_foreign_partner_district(): void
    {
        $foreignPartner = Partner::factory()->create();
        $foreignDistrict = District::factory()->forPartner((int) $foreignPartner->id)->create([
            'name' => 'Чужой район',
        ]);

        $this->getJson(route('lead.locations', [
            'landingSlug' => $this->landingWidget->landing_slug,
            'district_id' => $foreignDistrict->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_locations_endpoint_returns_locations_for_district(): void
    {
        $this->getJson(route('lead.locations', [
            'landingSlug' => $this->landingWidget->landing_slug,
            'district_id' => $this->landingDistrict->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->landingLocation->id)
            ->assertJsonPath('data.0.name', 'Школа «Радуга»');
    }

    public function test_locations_excludes_objects_without_district(): void
    {
        Location::query()->create([
            'partner_id' => $this->landingPartner->id,
            'district_id' => null,
            'name'       => 'Без района',
            'is_enabled' => true,
        ]);

        $this->getJson(route('lead.locations', [
            'landingSlug' => $this->landingWidget->landing_slug,
            'district_id' => $this->landingDistrict->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->landingLocation->id);
    }

    public function test_teams_returns_422_without_location_id(): void
    {
        $this->getJson(route('lead.teams', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_teams_returns_empty_for_foreign_partner_location(): void
    {
        $foreignPartner = Partner::factory()->create();
        $foreignLocation = Location::query()->create([
            'partner_id'  => $foreignPartner->id,
            'district_id' => District::factory()->forPartner((int) $foreignPartner->id)->create()->id,
            'name'        => 'Чужой объект',
            'is_enabled'  => true,
        ]);

        $this->getJson(route('lead.teams', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $foreignLocation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_show_lists_only_enabled_districts_with_enabled_locations(): void
    {
        District::factory()->forPartner((int) $this->landingPartner->id)->disabled()->create([
            'name' => 'Скрытый район',
        ]);

        District::factory()->forPartner((int) $this->landingPartner->id)->create([
            'name' => 'Пустой район',
        ]);

        $html = $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Центральный', $html);
        $this->assertStringNotContainsString('Скрытый район', $html);
        $this->assertStringNotContainsString('Пустой район', $html);
    }

    public function test_teams_endpoint_returns_teams_for_location(): void
    {
        $this->getJson(route('lead.teams', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->landingTeam->id)
            ->assertJsonPath('data.0.title', 'Плавание');
    }

    public function test_team_info_returns_service_details(): void
    {
        $this->seed(WeekdaysSeeder::class);

        $sportType = SportType::query()->create([
            'partner_id' => $this->landingPartner->id,
            'name'       => 'Плавание',
            'sort'       => 1,
            'is_enabled' => true,
        ]);

        $this->landingTeam->update([
            'training_base'            => 'СК Олимп',
            'address'                  => 'ул. Спортивная, 5',
            'sport_type_id'            => $sportType->id,
            'month_price'              => 4500,
            'default_duration_minutes' => 90,
        ]);
        $this->landingTeam->weekdays()->sync([1, 3, 5]);

        Carbon::setTestNow(Carbon::create(2026, 3, 15));

        $response = $this->getJson(route('lead.team-info', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.title', 'Плавание')
            ->assertJsonPath('data.rows.0.label', 'Тренировочная база')
            ->assertJsonPath('data.rows.0.value', 'СК Олимп')
            ->assertJsonPath('data.rows.1.value', 'ул. Спортивная, 5')
            ->assertJsonPath('data.rows.2.value', 'Плавание')
            ->assertJsonPath('data.rows.3.value', '4 500 ₽')
            ->assertJsonPath('data.rows.4.value', '3')
            ->assertJsonPath('data.rows.5.value', '12')
            ->assertJsonPath('data.rows.6.value', '1 ч 30 мин')
            ->assertJsonPath('data.rows.7.value', '12.01.2026 — 30.06.2026')
            ->assertJsonPath('data.rows.8.value', 'Понедельник, Среда, Пятница');

        Carbon::setTestNow();
    }

    public function test_team_info_uses_september_period_in_second_half_of_year(): void
    {
        $this->seed(WeekdaysSeeder::class);

        Carbon::setTestNow(Carbon::create(2026, 10, 1));

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.rows.7.value', '01.09.2026 — 30.06.2027');

        Carbon::setTestNow();
    }

    public function test_team_info_returns_404_for_foreign_team(): void
    {
        $foreignPartner = Partner::factory()->create();
        $foreignTeam = Team::factory()->create([
            'partner_id' => $foreignPartner->id,
            'is_enabled' => true,
        ]);

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $foreignTeam->id,
        ]))
            ->assertNotFound();
    }

    public function test_team_info_returns_422_without_team_id(): void
    {
        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_submit_creates_landing_school_lead_with_all_core_fields(): void
    {
        $this->fakeRecaptchaSuccess();

        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
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
            'district_id'            => $this->landingDistrict->id,
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
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
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
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id' => $this->landingWidget->partner_id,
            'team_id'    => null,
        ]);
    }

    public function test_submit_requires_district(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload();
        unset($payload['district_id']);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['district_id']);
    }

    public function test_submit_requires_location(): void
    {
        $this->fakeRecaptchaSuccess();

        $payload = $this->validLandingPayload();
        unset($payload['location_id']);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_submit_rejects_location_from_another_partner(): void
    {
        $this->fakeRecaptchaSuccess();

        $foreignPartner = Partner::factory()->create();
        $foreignDistrict = District::factory()->forPartner((int) $foreignPartner->id)->create();
        $foreignLocation = Location::query()->create([
            'partner_id'  => $foreignPartner->id,
            'district_id' => $foreignDistrict->id,
            'name'        => 'Чужой',
            'is_enabled'  => true,
        ]);

        $payload = $this->validLandingPayload([
            'location_id' => $foreignLocation->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_submit_rejects_location_without_district(): void
    {
        $this->fakeRecaptchaSuccess();

        $locationWithoutDistrict = Location::query()->create([
            'partner_id' => $this->landingPartner->id,
            'name'       => 'Без района',
            'is_enabled' => true,
        ]);

        $payload = $this->validLandingPayload([
            'location_id' => $locationWithoutDistrict->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_submit_rejects_location_from_another_district(): void
    {
        $this->fakeRecaptchaSuccess();

        $otherDistrict = District::factory()->forPartner((int) $this->landingPartner->id)->create([
            'name' => 'Северный',
        ]);

        $otherLocation = Location::query()->create([
            'partner_id'  => $this->landingPartner->id,
            'district_id' => $otherDistrict->id,
            'name'        => 'Северная школа',
            'is_enabled'  => true,
        ]);

        app(LocationTeamSyncService::class)->syncTeamsForLocation(
            $otherLocation,
            [(int) $this->landingTeam->id],
        );

        $payload = $this->validLandingPayload([
            'district_id' => $this->landingDistrict->id,
            'location_id' => $otherLocation->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_submit_rejects_team_not_available_at_location(): void
    {
        $this->fakeRecaptchaSuccess();

        $otherDistrict = District::factory()->forPartner((int) $this->landingPartner->id)->create([
            'name' => 'Северный',
        ]);

        $otherLocation = Location::query()->create([
            'partner_id'  => $this->landingPartner->id,
            'district_id' => $otherDistrict->id,
            'name'        => 'Северная школа',
            'is_enabled'  => true,
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
            'district_id' => $this->landingDistrict->id,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $otherTeam->id,
        ]);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
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
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
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
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
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
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
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
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['consent_accepted']);
    }

    public function test_submit_returns_field_errors_for_invalid_phone(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
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
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $payload
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recaptcha_token']);
    }

    public function test_submit_fails_when_recaptcha_score_is_too_low(): void
    {
        $this->fakeRecaptchaLowScore();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )
            ->assertStatus(422);
    }

    public function test_partner_widget_has_landing_slug_null_until_partner_sets_it(): void
    {
        $partner = Partner::factory()->create();

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $partner->id);

        $this->assertNull($widget->landing_slug);
        $this->assertTrue($widget->is_landing_active);
        $this->assertDatabaseHas('partner_widgets', [
            'partner_id'   => $partner->id,
            'landing_slug' => null,
        ]);
    }

    public function test_show_returns_404_when_landing_slug_not_set(): void
    {
        $this->landingWidget->update(['landing_slug' => null]);

        $this->get(route('lead.show', ['landingSlug' => 'raduga-test']))
            ->assertNotFound();
    }

    public function test_submit_returns_404_when_landing_inactive(): void
    {
        $this->fakeRecaptchaSuccess();
        $this->landingWidget->update(['is_landing_active' => false]);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )
            ->assertNotFound();
    }
}
