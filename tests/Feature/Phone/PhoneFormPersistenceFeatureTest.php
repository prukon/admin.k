<?php

namespace Tests\Feature\Phone;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\PartnerLead;
use App\Models\SchoolLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Публичные формы: сохранение случайного номера.
 */
final class PhoneFormPersistenceFeatureTest extends TestCase
{
    use InteractsWithPhoneInput;
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_contact_form_persists_random_phone(): void
    {
        Mail::fake();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $masked = $this->randomRuPhoneMasked();

        $this->postJson('/contact/send', [
            'name'            => 'Тест',
            'phone'           => $masked,
            'recaptcha_token' => 'fake-token',
        ])->assertOk();

        $lead = PartnerLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($masked, $lead->phone);
    }

    public function test_widget_form_persists_random_phone(): void
    {
        $this->fakeRecaptchaSuccess();
        $masked = $this->randomRuPhoneMasked();

        $this->postJson(
            route('widget.school-lead.submit', ['widgetKey' => $this->landingWidget->widget_key]),
            [
                'name'             => 'Виджет',
                'phone'            => $masked,
                'consent_accepted' => '1',
                'recaptcha_token'  => 'fake-token',
            ]
        )->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($masked, $lead->phone);
    }

    public function test_landing_form_persists_random_parent_phone(): void
    {
        $this->fakeRecaptchaSuccess();
        $masked = $this->randomRuPhoneMasked();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload(['parent_phone' => $masked])
        )->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($masked, $lead->phone);
        $this->assertSame($masked, $lead->parent_phone);
    }
}
