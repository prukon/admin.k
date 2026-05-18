<?php

namespace Tests\Feature\Public;

use App\Mail\NewSchoolLeadSubmission;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SchoolLeadNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        config([
            'services.telegram.bot_token' => 'test-bot-token',
        ]);
    }

    public function test_submit_sends_email_to_partner_admin(): void
    {
        Mail::fake();

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $partner = Partner::factory()->create(['email' => 'org@school.test']);
        $adminRoleId = Role::where('name', 'admin')->value('id');

        User::factory()->create([
            'partner_id' => $partner->id,
            'role_id'    => $adminRoleId,
            'email'      => 'admin@school.test',
        ]);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $partner->id);

        $this->postJson(route('widget.school-lead.submit', ['widgetKey' => $widget->widget_key]), [
            'name'             => 'Анна',
            'phone'            => '+7 999 000-11-22',
            'consent_accepted' => '1',
            'recaptcha_token'  => 'fake-token',
        ])->assertOk();

        Mail::assertSent(NewSchoolLeadSubmission::class, function (NewSchoolLeadSubmission $mail) {
            return $mail->hasTo('admin@school.test') || $mail->hasTo('org@school.test');
        });
    }

    public function test_submit_sends_telegram_when_partner_chat_id_configured(): void
    {
        Mail::fake();

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $partner = Partner::factory()->create([
            'school_leads_telegram_chat_id' => '-100999888777',
        ]);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $partner->id);

        $this->postJson(route('widget.school-lead.submit', ['widgetKey' => $widget->widget_key]), [
            'name'             => 'Анна',
            'phone'            => '+7 999 000-11-22',
            'consent_accepted' => '1',
            'recaptcha_token'  => 'fake-token',
        ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org')
                && $request['chat_id'] === '-100999888777'
                && str_contains($request['text'], 'Анна');
        });
    }
}
