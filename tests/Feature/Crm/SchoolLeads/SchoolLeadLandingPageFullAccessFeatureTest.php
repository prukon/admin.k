<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\PartnerWidget;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

/**
 * CRM-вкладка «Страница заявки»: permission schoolLeadLanding.view, UI и контроль доступа.
 */
final class SchoolLeadLandingPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /**
     * @return list<array{method: string, url: string, headers?: array<string, string>}>
     */
    private function landingRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.school-leads.landing'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    public function test_guest_cannot_access_landing_route(): void
    {
        Auth::logout();

        foreach ($this->landingRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_school_lead_landing_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->landingRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без schoolLeadLanding.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_school_lead_landing_view_all_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        foreach ($this->landingRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "С schoolLeadLanding.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_landing_page_renders_ui_and_view_data(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $landingUrl = route('lead.show', ['landingKey' => $widget->landing_key]);

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewIs('admin.school-leads.index')
            ->assertViewHas('activeTab', 'landing')
            ->assertViewHas('landingUrl', $landingUrl)
            ->assertViewHas('widget')
            ->assertViewHas('partner')
            ->assertSee('>Страница заявки</a>', false)
            ->assertSee('nav-link active', false)
            ->assertSee('>Заявки</a>', false)
            ->assertSee('id="landingUrl"', false)
            ->assertSee('id="copyLandingUrlBtn"', false)
            ->assertSee('id="copyLandingSuccess"', false)
            ->assertSee('Копировать', false)
            ->assertSee('Открыть страницу', false)
            ->assertSee($landingUrl, false)
            ->assertSee('Брендированная страница с полной формой заявки', false);
    }

    public function test_landing_url_in_view_matches_partner_widget_landing_key(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->assertSame(48, strlen($widget->landing_key));

        $response = $this->get(route('admin.school-leads.landing'))->assertOk();

        $landingUrl = $response->viewData('landingUrl');
        $this->assertSame(
            route('lead.show', ['landingKey' => $widget->landing_key]),
            $landingUrl
        );
        $this->assertStringContainsString($widget->landing_key, (string) $landingUrl);
    }

    public function test_inactive_landing_shows_warning_on_crm_page(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['is_landing_active' => false]);

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertSee('Страница заявки отключена', false)
            ->assertSee('alert-warning', false);
    }

    public function test_active_landing_does_not_show_disabled_warning(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $widget = app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $widget->update(['is_landing_active' => true]);

        $html = $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Страница заявки отключена', $html);
    }

    public function test_user_with_only_landing_permission_cannot_access_leads_or_widget_routes(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->get(route('admin.school-leads.landing'))->assertOk();
        $this->get(route('admin.school-leads'))->assertForbidden();
        $this->get(route('admin.school-leads.widget'))->assertForbidden();
        $this->get(route('admin.school-widget'))->assertForbidden();
    }

    public function test_user_with_landing_permission_does_not_see_widget_tab(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $html = $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Страница заявки</a>', $html);
        $this->assertStringNotContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringNotContainsString(route('admin.school-leads.widget'), $html);
    }

    public function test_user_with_landing_and_widget_permissions_sees_both_optional_tabs(): void
    {
        $actor = $this->createUserWithoutPermission('schoolLeads.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->grantPermission($actor, 'schoolWidget.view');
        $this->actingAs($actor);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $html = $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Страница заявки</a>', $html);
        $this->assertStringContainsString('>Виджет для сайта</a>', $html);
        $this->assertStringContainsString(route('admin.school-leads.landing'), $html);
        $this->assertStringContainsString(route('admin.school-leads.widget'), $html);
    }

    public function test_superadmin_can_access_landing_without_explicit_permission(): void
    {
        $this->asSuperadmin();

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->get(route('admin.school-leads.landing'))
            ->assertOk()
            ->assertViewHas('activeTab', 'landing');
    }

    public function test_landing_page_provisions_partner_widget_if_missing(): void
    {
        PartnerWidget::query()->where('partner_id', $this->partner->id)->delete();

        $actor = $this->createUserWithoutPermission('schoolLeadLanding.view', $this->partner);
        $this->grantPermission($actor, 'schoolLeadLanding.view');
        $this->actingAs($actor);

        $this->assertNull(
            PartnerWidget::query()->where('partner_id', $this->partner->id)->first()
        );

        $this->get(route('admin.school-leads.landing'))->assertOk();

        $widget = PartnerWidget::query()->where('partner_id', $this->partner->id)->first();
        $this->assertNotNull($widget);
        $this->assertSame(48, strlen($widget->landing_key));
        $this->assertSame(48, strlen($widget->widget_key));
        $this->assertTrue($widget->is_landing_active);
    }

    public function test_landing_route_uses_school_lead_landing_view_middleware_only(): void
    {
        $route = Route::getRoutes()->getByName('admin.school-leads.landing');
        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        $this->assertContains('can:schoolLeadLanding.view', $middleware);
        $this->assertNotContains('can:schoolWidget.view', $middleware);
        $this->assertNotContains('can:schoolLeads.view', $middleware);
    }

    public function test_school_lead_landing_view_permission_exists_in_database(): void
    {
        $permission = DB::table('permissions')
            ->where('name', 'schoolLeadLanding.view')
            ->first();

        $this->assertNotNull($permission);
        $this->assertSame('Страница заявки (CRM)', $permission->description);
    }

    public function test_admin_role_does_not_receive_school_lead_landing_view_by_default(): void
    {
        $adminPermissions = config('role_base_permissions.roles.admin', []);

        $this->assertNotContains('schoolLeadLanding.view', $adminPermissions);

        $permissionId = $this->permissionId('schoolLeadLanding.view');
        $adminRoleId = $this->roleId('admin');

        $assigned = DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $adminRoleId)
            ->where('permission_id', $permissionId)
            ->exists();

        $this->assertFalse($assigned, 'Роль admin партнёра не должна получать schoolLeadLanding.view автоматически');
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
