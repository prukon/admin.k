<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\SchoolLeadSource;
use App\Models\SchoolLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Non-AJAX safety-net и AJAX-контракт для POST /lead/{slug}/submit.
 */
final class SchoolLeadLandingNonAjaxSafetyNetFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_submit_non_ajax_redirects_and_creates_lead(): void
    {
        $this->fakeRecaptchaSuccess();

        $slug = (string) $this->landingWidget->landing_slug;
        $payload = $this->validLandingPayload([
            'parent_email' => 'non-ajax@example.com',
        ]);

        $this->from(route('lead.show', ['landingSlug' => $slug]))
            ->post(route('lead.submit', ['landingSlug' => $slug]), $payload)
            ->assertRedirect(route('lead.show', ['landingSlug' => $slug]))
            ->assertSessionHas('landing_submitted', true);

        $this->assertDatabaseHas('school_leads', [
            'partner_id'   => $this->landingPartner->id,
            'source'       => SchoolLeadSource::Landing->value,
            'parent_email' => 'non-ajax@example.com',
            'district_id'  => $this->landingDistrict->id,
            'location_id'  => $this->landingLocation->id,
            'team_id'      => $this->landingTeam->id,
        ]);
    }

    public function test_submit_non_ajax_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $this->fakeRecaptchaSuccess();

        $slug = (string) $this->landingWidget->landing_slug;
        $showUrl = route('lead.show', ['landingSlug' => $slug]);

        $this->from($showUrl)
            ->post(route('lead.submit', ['landingSlug' => $slug]), [
                'recaptcha_token' => 'fake-token',
            ])
            ->assertStatus(302)
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors(['parent_lastname']);

        $this->assertDatabaseCount('school_leads', 0);
    }

    public function test_submit_non_ajax_recaptcha_validation_failure_redirects_back_with_errors_not_empty_200(): void
    {
        $slug = (string) $this->landingWidget->landing_slug;
        $showUrl = route('lead.show', ['landingSlug' => $slug]);

        $this->from($showUrl)
            ->post(route('lead.submit', ['landingSlug' => $slug]), $this->validLandingPayload([
                'recaptcha_token' => '',
            ]))
            ->assertStatus(302)
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors(['recaptcha_token']);

        $this->assertDatabaseCount('school_leads', 0);
    }

    public function test_submit_non_ajax_recaptcha_score_failure_redirects_back_with_form_error(): void
    {
        $this->fakeRecaptchaLowScore();

        $slug = (string) $this->landingWidget->landing_slug;
        $showUrl = route('lead.show', ['landingSlug' => $slug]);

        $this->from($showUrl)
            ->post(route('lead.submit', ['landingSlug' => $slug]), $this->validLandingPayload([
                'parent_email' => 'recaptcha-fail@example.com',
            ]))
            ->assertStatus(302)
            ->assertRedirect($showUrl)
            ->assertSessionHasErrors(['form']);

        $this->assertDatabaseCount('school_leads', 0);
    }

    public function test_submit_ajax_returns_json_structure_on_success(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'ajax-success@example.com',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message'])
            ->assertJsonPath('message', 'Заявка отправлена! Мы свяжемся с вами в ближайшее время.');

        $this->assertDatabaseHas('school_leads', [
            'parent_email' => 'ajax-success@example.com',
        ]);
    }

    public function test_submit_ajax_validation_returns_422_json(): void
    {
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            [
                'recaptcha_token' => 'fake-token',
            ],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['parent_lastname']);

        $this->assertDatabaseCount('school_leads', 0);
    }
}
