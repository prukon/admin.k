<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\PartnerWidget;
use App\Services\PartnerWidgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Публичные URL /lead/{slug}: только slug, не legacy landing_key.
 */
final class SchoolLeadLandingSlugPublicFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_legacy_forty_eight_char_landing_key_url_returns_404(): void
    {
        Auth::logout();

        $legacyKey = str_repeat('a', 48);
        PartnerWidget::query()
            ->where('id', $this->landingWidget->id)
            ->update(['landing_key' => $legacyKey]);

        $this->get('/lead/' . $legacyKey)
            ->assertNotFound();
    }

    public function test_uppercase_slug_in_url_does_not_match_route(): void
    {
        Auth::logout();

        $this->get('/lead/RADUGA-TEST')
            ->assertNotFound();
    }

    public function test_reserved_slug_without_widget_record_returns_404(): void
    {
        Auth::logout();

        $this->get('/lead/admin')
            ->assertNotFound();
    }

    public function test_valid_slug_resolves_correct_partner_widget(): void
    {
        Auth::logout();

        $otherPartner = \App\Models\Partner::factory()->create(['title' => 'Чужая школа']);
        $otherWidget = app(PartnerWidgetService::class)->ensureForPartner((int) $otherPartner->id);
        $otherWidget->update(['landing_slug' => 'other-school']);

        $this->get(route('lead.show', ['landingSlug' => 'raduga-test']))
            ->assertOk()
            ->assertSee('Детская школа «Радуга»', false)
            ->assertDontSee('Чужая школа', false);
    }

    public function test_submit_resolves_partner_by_slug_not_by_body(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            array_merge($this->validLandingPayload(), [
                'partner_id' => 999999,
            ])
        )
            ->assertOk();

        $this->assertDatabaseHas('school_leads', [
            'partner_id' => $this->landingPartner->id,
            'source'     => 'landing',
        ]);
    }
}
