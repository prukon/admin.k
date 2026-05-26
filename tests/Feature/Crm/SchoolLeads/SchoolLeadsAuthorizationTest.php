<?php

namespace Tests\Feature\Crm\SchoolLeads;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

class SchoolLeadsAuthorizationTest extends CrmTestCase
{
    public function test_school_leads_routes_use_school_leads_view_middleware(): void
    {
        $expected = [
            'admin.school-leads'                      => 'can:schoolLeads.view',
            'admin.school-leads.data'                   => 'can:schoolLeads.view',
            'admin.school-leads.update'                 => 'can:schoolLeads.view',
            'admin.school-leads.destroy'                => 'can:schoolLeads.view',
            'admin.school-leads.columns-settings.get'   => 'can:schoolLeads.view',
            'admin.school-leads.columns-settings.save'  => 'can:schoolLeads.view',
        ];

        foreach ($expected as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_school_widget_routes_use_school_widget_view_middleware(): void
    {
        $expected = [
            'admin.school-leads.widget'                => 'can:schoolWidget.view',
            'admin.school-widget'                      => 'can:schoolWidget.view',
            'admin.school-widget.telegram-link'        => 'can:schoolWidget.view',
            'admin.school-widget.telegram-disconnect'  => 'can:schoolWidget.view',
        ];

        foreach ($expected as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_admin_role_has_school_leads_permissions_in_config(): void
    {
        $adminPermissions = config('role_base_permissions.roles.admin', []);

        $this->assertContains('schoolLeads.view', $adminPermissions);
        $this->assertContains('schoolWidget.view', $adminPermissions);
    }

    public function test_user_role_does_not_have_school_leads_permissions_in_config(): void
    {
        $userPermissions = config('role_base_permissions.roles.user', []);

        $this->assertNotContains('schoolLeads.view', $userPermissions);
        $this->assertNotContains('schoolWidget.view', $userPermissions);
    }
}
