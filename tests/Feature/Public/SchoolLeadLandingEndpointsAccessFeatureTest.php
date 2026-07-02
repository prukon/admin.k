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
 * Контроль доступа и ожидаемые HTTP-статусы всех endpoint'ов публичной страницы заявки.
 */
final class SchoolLeadLandingEndpointsAccessFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
    }

    /**
     * @return list<array{
     *     name: string,
     *     method: string,
     *     url: string,
     *     data?: array<string, mixed>,
     *     headers?: array<string, string>,
     *     expected: int
     * }>
     */
    private function guestExpectedStatusesPayload(): array
    {
        $slug = (string) $this->landingWidget->landing_slug;

        return [
            [
                'name'     => 'show',
                'method'   => 'GET',
                'url'      => route('lead.show', ['landingSlug' => $slug]),
                'headers'  => ['HTTP_ACCEPT' => 'text/html'],
                'expected' => 200,
            ],
            [
                'name'     => 'locations ok',
                'method'   => 'GET',
                'url'      => route('lead.locations', [
                    'landingSlug' => $slug,
                    'district_id' => $this->landingDistrict->id,
                ]),
                'expected' => 200,
            ],
            [
                'name'     => 'locations missing district',
                'method'   => 'GET',
                'url'      => route('lead.locations', ['landingSlug' => $slug]),
                'expected' => 422,
            ],
            [
                'name'     => 'teams ok',
                'method'   => 'GET',
                'url'      => route('lead.teams', [
                    'landingSlug'  => $slug,
                    'location_id' => $this->landingLocation->id,
                ]),
                'expected' => 200,
            ],
            [
                'name'     => 'teams missing location',
                'method'   => 'GET',
                'url'      => route('lead.teams', ['landingSlug' => $slug]),
                'expected' => 422,
            ],
            [
                'name'     => 'team-info ok',
                'method'   => 'GET',
                'url'      => route('lead.team-info', [
                    'landingSlug'  => $slug,
                    'location_id' => $this->landingLocation->id,
                    'team_id'     => $this->landingTeam->id,
                ]),
                'expected' => 200,
            ],
            [
                'name'     => 'team-info missing team',
                'method'   => 'GET',
                'url'      => route('lead.team-info', [
                    'landingSlug'  => $slug,
                    'location_id' => $this->landingLocation->id,
                ]),
                'expected' => 422,
            ],
            [
                'name'     => 'submit invalid payload',
                'method'   => 'POST',
                'url'      => route('lead.submit', ['landingSlug' => $slug]),
                'data'     => ['recaptcha_token' => 'fake-token'],
                'headers'  => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
                'expected' => 422,
            ],
        ];
    }

    public function test_guest_all_public_endpoints_return_expected_statuses(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();

        foreach ($this->guestExpectedStatusesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                $item['expected'],
                $response->getStatusCode(),
                "Гость: {$item['name']} → {$response->getStatusCode()}"
            );

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                "Гость: {$item['name']} не должен возвращать 500"
            );
        }
    }

    public function test_authenticated_user_all_public_endpoints_return_200_on_valid_requests(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->landingPartner->id,
        ]);
        $this->actingAs($user);
        $this->fakeRecaptchaSuccess();

        $slug = (string) $this->landingWidget->landing_slug;

        $this->get(route('lead.show', ['landingSlug' => $slug]))->assertOk();

        $this->getJson(route('lead.locations', [
            'landingSlug' => $slug,
            'district_id' => $this->landingDistrict->id,
        ]))->assertOk()->assertJsonStructure(['data']);

        $this->getJson(route('lead.teams', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
        ]))->assertOk()->assertJsonStructure(['data']);

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]))->assertOk()->assertJsonStructure(['data']);

        $this->postJson(
            route('lead.submit', ['landingSlug' => $slug]),
            $this->validLandingPayload([
                'parent_email' => 'auth-endpoints@example.com',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);
    }

    public function test_guest_submit_with_valid_payload_returns_json_not_empty_200(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();

        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'guest-json@example.com',
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk()
            ->assertJsonStructure(['id', 'message']);

        $json = $response->json();
        $this->assertNotEmpty($json['message'] ?? null);
        $this->assertNotEmpty($json['id'] ?? null);
    }

    public function test_inactive_landing_returns_404_for_all_endpoints(): void
    {
        Auth::logout();
        $this->fakeRecaptchaSuccess();
        $this->landingWidget->update(['is_landing_active' => false]);

        $slug = (string) $this->landingWidget->landing_slug;

        foreach ([
            ['GET', route('lead.show', ['landingSlug' => $slug]), []],
            ['GET', route('lead.locations', ['landingSlug' => $slug, 'district_id' => $this->landingDistrict->id]), []],
            ['GET', route('lead.teams', ['landingSlug' => $slug, 'location_id' => $this->landingLocation->id]), []],
            ['GET', route('lead.team-info', [
                'landingSlug'  => $slug,
                'location_id' => $this->landingLocation->id,
                'team_id'     => $this->landingTeam->id,
            ]), []],
            ['POST', route('lead.submit', ['landingSlug' => $slug]), $this->validLandingPayload()],
        ] as [$method, $url, $data]) {
            $response = $this->call($method, $url, $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertSame(404, $response->getStatusCode(), "{$method} {$url}");
        }
    }

    public function test_public_landing_routes_are_not_protected_by_auth_or_crm_permissions(): void
    {
        foreach (['lead.show', 'lead.locations', 'lead.teams', 'lead.team-info', 'lead.submit'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");

            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth', $middleware, $routeName);
            $this->assertNotContains('can:schoolLeadLanding.view', $middleware, $routeName);
            $this->assertNotContains('can:schoolLeads.view', $middleware, $routeName);
        }
    }
}
