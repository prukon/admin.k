<?php

namespace Tests\Feature\Crm\Leads;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;


class LeadsAuthorizationTest extends CrmTestCase
{
    /**
     * Роут /leads (landing.submissions) должен быть защищён middleware can:leads-view.
     */
    public function test_leads_page_route_has_leads_view_middleware(): void
    {
        $route = Route::getRoutes()->getByName('landing.submissions');

        $this->assertNotNull($route, 'Маршрут landing.submissions не найден');

        $middlewares = $route->gatherMiddleware();

        $this->assertTrue(
            in_array('can:leads-view', $middlewares, true),
            'Маршрут /leads должен быть защищён middleware can:leads-view'
        );
    }

    /**
     * DataTables-роут /admin/leads/data также должен быть защищён middleware can:leads-view.
     */
    public function test_leads_datatable_route_has_leads_view_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.leads.data');

        $this->assertNotNull($route, 'Маршрут admin.leads.data не найден');

        $middlewares = $route->gatherMiddleware();

        $this->assertTrue(
            in_array('can:leads-view', $middlewares, true),
            'Маршрут /admin/leads/data должен быть защищён middleware can:leads-view'
        );
    }
}