<?php

namespace Tests\Feature\Crm\PartnerLeads;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

class PartnerLeadsAuthorizationTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_partner_leads_index_renders_tabs_shell(): void
    {
        $this->asSuperadmin();

        $this->get(route('admin.partner-leads'))
            ->assertOk()
            ->assertViewIs('admin.partners.index')
            ->assertSee('partnersSectionTabs', false)
            ->assertSee('role="tab">Лиды</a>', false)
            ->assertSee('partnerLeadsReportToolbar', false)
            ->assertSee('partnerLeadsFiltersCollapse', false)
            ->assertSee('columnsDropdownPartnerLeads', false)
            ->assertSee('partner-leads-stat-new', false)
            ->assertSee('id="leads-table"', false);
    }

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

    public function test_partner_leads_columns_settings_routes_have_partner_leads_view_middleware(): void
    {
        foreach ([
            'admin.partner-leads.columns-settings.get',
            'admin.partner-leads.columns-settings.save',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");
            $this->assertTrue(
                in_array('can:partnerLeads.view', $route->gatherMiddleware(), true),
                "Маршрут {$routeName} должен быть защищён middleware can:partnerLeads.view"
            );
        }
    }
}
