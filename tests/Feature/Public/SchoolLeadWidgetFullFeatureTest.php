<?php

namespace Tests\Feature\Public;

use App\Models\Partner;
use App\Models\PartnerWidget;
use App\Services\PartnerWidgetService;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolLeadWidgetFullFeatureTest extends TestCase
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

    public function test_submit_returns_field_errors_for_invalid_phone(): void
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
                'name'             => 'Иван',
                'phone'            => '12',
                'consent_accepted' => '1',
                'recaptcha_token'  => 'fake-token',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_submit_fails_when_recaptcha_score_is_too_low(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.1,
            ], 200),
        ]);

        $response = $this->postJson(
            route('widget.school-lead.submit', ['widgetKey' => $this->widget->widget_key]),
            [
                'name'             => 'Иван',
                'phone'            => '+7 999 111-22-33',
                'consent_accepted' => '1',
                'recaptcha_token'  => 'fake-token',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_submit_fails_without_recaptcha_token(): void
    {
        $response = $this->postJson(
            route('widget.school-lead.submit', ['widgetKey' => $this->widget->widget_key]),
            [
                'name'             => 'Иван',
                'phone'            => '+7 999 111-22-33',
                'consent_accepted' => '1',
            ]
        );

        $response->assertStatus(422);
    }

    public function test_widget_show_returns_404_for_unknown_key(): void
    {
        $fakeKey = str_repeat('a', 48);

        $this->get(route('widget.school-lead.show', ['widgetKey' => $fakeKey]))
            ->assertNotFound();
    }

    public function test_partner_widget_created_when_partner_is_created(): void
    {
        $partner = Partner::factory()->create();

        $this->assertDatabaseHas('partner_widgets', [
            'partner_id' => $partner->id,
            'is_active'  => 1,
        ]);
    }
}
