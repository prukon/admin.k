<?php

namespace Tests\Feature\Crm\SchoolLeads;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

class SchoolLeadsAuthorizationTest extends CrmTestCase
{
    public function test_school_leads_routes_have_view_middleware(): void
    {
        foreach (['admin.school-leads', 'admin.school-leads.data'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");
            $this->assertContains('can:schoolLeads.view', $route->gatherMiddleware());
        }
    }

    public function test_school_widget_route_has_widget_view_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.school-widget');
        $this->assertNotNull($route);
        $this->assertContains('can:schoolWidget.view', $route->gatherMiddleware());
    }
}
