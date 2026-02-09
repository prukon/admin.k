<?php

namespace Tests\Feature\Crm;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class DashboardAuthorizationTest extends CrmTestCase
{
    /**
     * P0.1 — Маршруты защищены нужным middleware can:dashboard-view
     */
    public function test_dashboard_routes_have_dashboard_view_middleware(): void
    {
        $routeNames = ['dashboard', 'getUserDetails', 'getTeamDetails'];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Маршрут {$routeName} не найден");

            $middlewares = $route->gatherMiddleware();

            $this->assertTrue(
                in_array('can:dashboard-view', $middlewares, true),
                "Маршрут {$routeName} должен быть защищён middleware can:dashboard-view"
            );
        }
    }

    /**
     * P0.2 — Гость не имеет доступа к AJAX-ручкам
     *
     * Не завязываемся жёстко на 302/401, просто проверяем, что не 200.
     */
    public function test_guest_cannot_access_ajax_routes(): void
    {
        auth()->logout();

        $userDetailsResponse = $this->get(route('getUserDetails', ['userId' => 1]));
        $teamDetailsResponse = $this->get(route('getTeamDetails', ['teamId' => 1, 'teamName' => 'test']));

        $this->assertNotEquals(
            200,
            $userDetailsResponse->status(),
            'Гость не должен получать 200 от /get-user-details'
        );

        $this->assertNotEquals(
            200,
            $teamDetailsResponse->status(),
            'Гость не должен получать 200 от /get-team-details'
        );
    }

    /**
     * P0.3 — Пользователь без права dashboard-view получает 403
     *
     * Переопределяем Gate локально в тесте.
     */
    public function test_user_without_dashboard_view_permission_gets_403_for_ajax_routes(): void
    {
        // Переопределяем gate на запрет
        Gate::define('dashboard-view', fn () => false);

        // /get-user-details
        $this->getJson(route('getUserDetails', ['userId' => $this->user->id]))
            ->assertStatus(403);

        // /get-team-details
        $this->getJson(route('getTeamDetails', ['teamId' => 1, 'teamName' => 'test']))
            ->assertStatus(403);
    }}