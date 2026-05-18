<?php

namespace Tests\Feature\Crm\PartnerLeads;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

class PartnerLeadsAuthorizationTest extends CrmTestCase
{
    public function test_partner_leads_page_route_has_partner_leads_view_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.partner-leads');

        $this->assertNotNull($route, 'Маршрут admin.partner-leads не найден');

        $middlewares = $route->gatherMiddleware();

        $this->assertTrue(
            in_array('can:partnerLeads.view', $middlewares, true),
            'Маршрут /admin/partner-leads должен быть защищён middleware can:partnerLeads.view'
        );
    }

    public function test_partner_leads_datatable_route_has_partner_leads_view_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.partner-leads.data');

        $this->assertNotNull($route, 'Маршрут admin.partner-leads.data не найден');

        $middlewares = $route->gatherMiddleware();

        $this->assertTrue(
            in_array('can:partnerLeads.view', $middlewares, true),
            'Маршрут /admin/partner-leads/data должен быть защищён middleware can:partnerLeads.view'
        );
    }
}
