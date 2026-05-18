<?php

namespace Tests\Feature\Public;

use App\Models\Partner;
use App\Models\PartnerWidget;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolLeadWidgetSubmitTest extends TestCase
{
    use RefreshDatabase;

    private PartnerWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        $partner = Partner::factory()->create();
        $this->widget = app(PartnerWidgetService::class)->ensureForPartner((int) $partner->id);
    }

    public function test_widget_form_page_is_available(): void
    {
        $response = $this->get(route('widget.school-lead.show', ['widgetKey' => $this->widget->widget_key]));

        $response->assertOk();
        $response->assertSee('Оставить заявку');
        $response->assertHeader('Content-Security-Policy', 'frame-ancestors *');
    }

    public function test_submit_creates_school_lead_when_valid(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $response = $this->postJson(
            route('widget.school-lead.submit', ['widgetKey' => $this->widget->widget_key]),
            [
                'name'             => 'Мария',
                'phone'            => '+7 999 111-22-33',
                'consent_accepted' => '1',
                'recaptcha_token'  => 'fake-token',
                'utm_source'       => 'google',
                'utm_campaign'     => 'spring',
                'page_url'         => 'https://partner-school.example/landing',
            ]
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Заявка отправлена!',
            ])
            ->assertJsonStructure(['id']);

        $this->assertDatabaseHas('school_leads', [
            'partner_id'   => $this->widget->partner_id,
            'name'         => 'Мария',
            'phone'        => '+7 999 111-22-33',
            'utm_source'   => 'google',
            'utm_campaign' => 'spring',
            'page_url'     => 'https://partner-school.example/landing',
        ]);

        $lead = SchoolLead::first();
        $this->assertNotNull($lead->consent_accepted_at);
        $this->assertNotNull($lead->policy_url);
    }

    public function test_submit_fails_without_consent(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $response = $this->postJson(
            route('widget.school-lead.submit', ['widgetKey' => $this->widget->widget_key]),
            [
                'name'            => 'Мария',
                'phone'           => '+7 999 111-22-33',
                'recaptcha_token' => 'fake-token',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseCount('school_leads', 0);
    }

    public function test_submit_fails_for_inactive_widget_key(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $this->widget->is_active = false;
        $this->widget->save();

        $response = $this->postJson(
            route('widget.school-lead.submit', ['widgetKey' => $this->widget->widget_key]),
            [
                'name'             => 'Мария',
                'phone'            => '+7 999 111-22-33',
                'consent_accepted' => '1',
                'recaptcha_token'  => 'fake-token',
            ]
        );

        $response->assertNotFound();
    }
}
