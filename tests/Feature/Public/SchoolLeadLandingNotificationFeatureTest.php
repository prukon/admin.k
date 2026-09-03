<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Mail\NewSchoolLeadSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Публичный POST /lead/{slug}/submit использует те же настройки email/Telegram,
 * что кнопка «Уведомления» на вкладке «Заявки».
 */
final class SchoolLeadLandingNotificationFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
        config([
            'services.telegram.bot_token' => 'test-bot-token',
        ]);
    }

    public function test_landing_submit_sends_only_configured_notification_emails(): void
    {
        Mail::fake();
        $this->fakeRecaptchaSuccess();

        $adminRoleId = Role::where('name', 'admin')->value('id');
        User::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'role_id'    => $adminRoleId,
            'email'      => 'landing-admin@example.test',
        ]);

        $this->landingPartner->email = 'landing-org@example.test';
        $this->landingPartner->school_leads_notification_emails = ['landing-custom@example.test'];
        $this->landingPartner->school_leads_email_notifications_disabled = false;
        $this->landingPartner->save();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )->assertOk();

        Mail::assertSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            return $mail->hasTo('landing-custom@example.test');
        });
        Mail::assertNotSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            return $mail->hasTo('landing-admin@example.test')
                || $mail->hasTo('landing-org@example.test');
        });
    }

    public function test_landing_submit_skips_email_when_partner_disabled_notifications(): void
    {
        Mail::fake();
        $this->fakeRecaptchaSuccess();

        $this->landingPartner->school_leads_notification_emails = ['landing-custom@example.test'];
        $this->landingPartner->school_leads_email_notifications_disabled = true;
        $this->landingPartner->save();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )->assertOk();

        Mail::assertNothingSent();
    }

    public function test_landing_submit_still_sends_telegram_when_email_disabled(): void
    {
        Mail::fake();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->landingPartner->school_leads_notification_emails = ['landing-custom@example.test'];
        $this->landingPartner->school_leads_email_notifications_disabled = true;
        $this->landingPartner->school_leads_telegram_chat_id = '-100222111000';
        $this->landingPartner->save();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )->assertOk();

        Mail::assertNothingSent();
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org')
                && $request['chat_id'] === '-100222111000'
                && str_contains($request['text'], 'Новая заявка (страница)');
        });
    }
}
