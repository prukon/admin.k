<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Публичная страница заявки: без auth все endpoint'ы отвечают 200 при валидном slug и данных.
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

    public function test_guest_all_public_landing_endpoints_return_200(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();

        $slug = (string) $this->landingWidget->landing_slug;

        $this->get(route('lead.show', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertViewIs('landing.partner-lead');

        $this->getJson(route('lead.locations', [
            'landingSlug' => $slug,
            'district_id' => $this->landingDistrict->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.id', $this->landingLocation->id);

        $this->getJson(route('lead.teams', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.id', $this->landingTeam->id);

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $slug]),
            $this->validLandingPayload()
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);
    }

    public function test_authenticated_user_all_public_landing_endpoints_return_200(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->landingPartner->id,
        ]);
        $this->actingAs($user);
        $this->fakeRecaptchaSuccess();

        $slug = (string) $this->landingWidget->landing_slug;

        $this->get(route('lead.show', ['landingSlug' => $slug]))
            ->assertOk()
            ->assertViewIs('landing.partner-lead');

        $this->getJson(route('lead.locations', [
            'landingSlug' => $slug,
            'district_id' => $this->landingDistrict->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.id', $this->landingLocation->id);

        $this->getJson(route('lead.teams', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->landingTeam->id);

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $slug]),
            $this->validLandingPayload([
                'parent_email' => 'auth-user@example.com',
            ])
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);
    }

    public function test_guest_can_open_landing_page(): void
    {
        Auth::logout();

        $this->get(route('lead.show', ['landingSlug' => $this->landingWidget->landing_slug]))
            ->assertOk()
            ->assertViewIs('landing.partner-lead');
    }

    public function test_guest_can_load_teams_for_location(): void
    {
        Auth::logout();

        $this->getJson(route('lead.teams', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->landingTeam->id);
    }

    public function test_guest_can_load_team_info(): void
    {
        Auth::logout();

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_guest_can_submit_valid_lead(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload()
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);
    }

    public function test_inactive_landing_returns_404_for_all_public_endpoints(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();
        $this->landingWidget->update(['is_landing_active' => false]);

        $slug = (string) $this->landingWidget->landing_slug;

        $this->get(route('lead.show', ['landingSlug' => $slug]))->assertNotFound();

        $this->getJson(route('lead.locations', [
            'landingSlug' => $slug,
            'district_id' => $this->landingDistrict->id,
        ]))->assertNotFound();

        $this->getJson(route('lead.teams', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
        ]))->assertNotFound();

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]))->assertNotFound();

        $this->postJson(
            route('lead.submit', ['landingSlug' => $slug]),
            $this->validLandingPayload()
        )->assertNotFound();
    }

    public function test_unset_slug_returns_404_for_all_public_endpoints(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();
        $this->landingWidget->update(['landing_slug' => null]);

        $this->get(route('lead.show', ['landingSlug' => 'raduga-test']))->assertNotFound();

        $this->getJson(route('lead.locations', [
            'landingSlug'  => 'raduga-test',
            'district_id' => $this->landingDistrict->id,
        ]))->assertNotFound();

        $this->getJson(route('lead.teams', [
            'landingSlug'  => 'raduga-test',
            'location_id' => $this->landingLocation->id,
        ]))->assertNotFound();

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => 'raduga-test',
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]))->assertNotFound();

        $this->postJson(
            route('lead.submit', ['landingSlug' => 'raduga-test']),
            $this->validLandingPayload()
        )->assertNotFound();
    }

    public function test_all_public_landing_routes_are_registered_without_auth_middleware(): void
    {
        $routeNames = ['lead.show', 'lead.locations', 'lead.teams', 'lead.team-info', 'lead.submit'];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");

            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth', $middleware, "Маршрут {$routeName} не должен требовать auth");
            $this->assertNotContains('can:schoolWidget.view', $middleware);
            $this->assertNotContains('can:schoolLeads.view', $middleware);
            $this->assertNotContains('can:schoolLeadLanding.view', $middleware);
        }
    }

    public function test_public_landing_routes_do_not_require_two_factor(): void
    {
        foreach (['lead.show', 'lead.locations', 'lead.teams', 'lead.team-info', 'lead.submit'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('2fa', $middleware, $routeName);
        }
    }
}
