<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Публичная страница заявки: доступ без авторизации, все endpoint'ы отвечают 200 при валидном запросе.
 */
final class SchoolLeadLandingPublicAccessFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_guest_can_open_landing_page(): void
    {
        Auth::logout();

        $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertOk()
            ->assertViewIs('landing.partner-lead');
    }

    public function test_guest_can_load_teams_for_location(): void
    {
        Auth::logout();

        $this->getJson(route('lead.teams', [
            'landingKey'  => $this->landingWidget->landing_key,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->landingTeam->id);
    }

    public function test_guest_can_submit_valid_lead(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload()
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);
    }

    public function test_authenticated_user_can_access_all_public_landing_endpoints(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->landingPartner->id,
        ]);
        $this->actingAs($user);
        $this->fakeRecaptchaSuccess();

        $this->get(route('lead.show', ['landingKey' => $this->landingWidget->landing_key]))
            ->assertOk();

        $this->getJson(route('lead.teams', [
            'landingKey'  => $this->landingWidget->landing_key,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk();

        $this->postJson(
            route('lead.submit', ['landingKey' => $this->landingWidget->landing_key]),
            $this->validLandingPayload([
                'parent_email' => 'auth-user@example.com',
            ])
        )
            ->assertOk();
    }

    public function test_all_public_landing_routes_are_registered_without_auth_middleware(): void
    {
        $routeNames = ['lead.show', 'lead.teams', 'lead.submit'];

        foreach ($routeNames as $routeName) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");

            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth', $middleware, "Маршрут {$routeName} не должен требовать auth");
            $this->assertNotContains('can:schoolWidget.view', $middleware);
            $this->assertNotContains('can:schoolLeads.view', $middleware);
        }
    }
}
