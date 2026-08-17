<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Сайдбар кабинета не прыгает при смене пункта меню: gutter на всех страницах admin2,
 * overflow с первого кадра, без OverlayScrollbars.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminSidebarLayoutStabilityFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
        $this->withSession($this->cabinetSession());
        $this->asAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    private function cabinetSession(): array
    {
        return [
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ];
    }

    private function grantUsersView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * UX-баг: gutter только в toolbar-CSS → при переходе «список ↔ консоль» колонка прыгает.
     *
     * @param  array<string, string>  $extra
     */
    private function assertRenderedAdminSidebarDoesNotJump(string $html, string $page, array $extra = []): void
    {
        $this->assertStringContainsString('scrollbar-gutter: stable', $html, $page.': нет резерва под скроллбар окна');
        $this->assertStringContainsString('.layout-fixed .main-sidebar .sidebar', $html, $page);
        $this->assertStringContainsString('overflow-y: auto', $html, $page.': overflow сайдбара должен быть в первом кадре');
        $this->assertStringContainsString('scrollbar-width: thin', $html, $page);
        $this->assertStringContainsString('.nav-sidebar > .nav-item .nav-icon', $html, $page);
        $this->assertStringContainsString('display: inline-block', $html, $page.': слот иконки иначе схлопывается');
        $this->assertStringContainsString('min-width: 1.6rem', $html, $page);
        $this->assertStringNotContainsString('OverlayScrollbars.min.css', $html, $page);
        $this->assertStringNotContainsString('jquery.overlayScrollbars', $html, $page);
        $this->assertNotSame('', trim($html), $page.': пустой 200');

        foreach ($extra as $needle => $message) {
            $this->assertStringContainsString($needle, $html, $page.': '.$message);
        }
    }

    public function test_admin_layout_head_reserves_scrollbar_gutter_and_sidebar_overflow(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));

        $this->assertStringContainsString('scrollbar-gutter: stable', $layout);
        $this->assertStringContainsString('.layout-fixed .main-sidebar .sidebar', $layout);
        $this->assertStringContainsString('overflow-y: auto', $layout);
        $this->assertStringContainsString('scrollbar-width: thin', $layout);
        $this->assertStringNotContainsString('OverlayScrollbars.min.css', $layout);
        $this->assertStringNotContainsString('jquery.overlayScrollbars', $layout);
        $this->assertStringNotContainsString('OverlayScrollbars.min.js', $layout);
        $this->assertStringContainsString("@include('includes.fontawesome')", $layout);
        $this->assertStringContainsString('.nav-sidebar > .nav-item .nav-icon', $layout);
        $this->assertStringContainsString('display: inline-block', $layout);
    }

    public function test_admin_style_css_keeps_the_same_sidebar_stability_rules(): void
    {
        $css = (string) file_get_contents(resource_path('css/style.css'));

        $this->assertStringContainsString('html {', $css);
        $this->assertStringContainsString('scrollbar-gutter: stable', $css);
        $this->assertStringContainsString('.layout-fixed .main-sidebar .sidebar', $css);
        $this->assertStringContainsString('overflow-y: auto', $css);
        $this->assertStringContainsString('.nav-sidebar > .nav-item .nav-icon', $css);
        $this->assertStringContainsString('display: inline-block', $css);
    }

    public function test_toolbar_css_does_not_own_html_scrollbar_gutter(): void
    {
        $css = (string) file_get_contents(resource_path('css/admin-list-toolbar.css'));

        $this->assertDoesNotMatchRegularExpression('/^html\s*\{/m', $css);
        $this->assertStringNotContainsString('scrollbar-gutter:', $css);
    }

    public function test_admin_with_rights_sees_stable_sidebar_on_cabinet(): void
    {
        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertRenderedAdminSidebarDoesNotJump($html, 'cabinet');
        $this->assertStringContainsString('<p>Консоль</p>', $html);
        $this->assertStringContainsString('fa-solid fa-house', $html);
    }

    public function test_admin_sees_the_same_sidebar_stability_on_toolbar_list_and_cabinet(): void
    {
        $this->grantUsersView();

        $cabinet = $this->get(route('dashboard'))->assertOk()->getContent();
        $users = $this->get(route('admin.user1'))->assertOk()->getContent();

        $dashboardBlade = (string) file_get_contents(resource_path('views/dashboard.blade.php'));
        $usersBlade = (string) file_get_contents(resource_path('views/admin/user.blade.php'));
        $this->assertStringNotContainsString('admin-list-toolbar.css', $dashboardBlade);
        $this->assertStringContainsString('admin-list-toolbar.css', $usersBlade);

        $this->assertStringNotContainsString('payments-report-toolbar', $cabinet, 'консоль без тулбара списка');
        $this->assertStringContainsString('payments-report-toolbar', $users);

        $this->assertRenderedAdminSidebarDoesNotJump($cabinet, 'cabinet');
        $this->assertRenderedAdminSidebarDoesNotJump($users, 'users');
    }

    public function test_landing_does_not_get_admin_sidebar_gutter_or_nav_icon_slot(): void
    {
        $html = $this->get(route('landing.home'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('.layout-fixed .main-sidebar .sidebar', $html);
        $this->assertStringNotContainsString('.nav-sidebar > .nav-item .nav-icon', $html);
        $this->assertStringNotContainsString('scrollbar-gutter: stable', $html);
        $this->assertStringNotContainsString('OverlayScrollbars.min.css', $html);
        $this->assertNotSame('', trim($html));
    }

    public function test_guest_is_redirected_from_cabinet_and_does_not_get_server_error(): void
    {
        Auth::logout();

        $html = $this->get(route('dashboard'));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertTrue($html->isRedirect(), 'гость HTML /cabinet → редирект на логин');
        $this->assertGuest();

        $json = $this->getJson(route('dashboard'));
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertContains($json->getStatusCode(), [401, 302]);
    }

    public function test_manager_without_dashboard_view_gets_403_on_cabinet(): void
    {
        $denied = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession($this->cabinetSession());

        $html = $this->get(route('dashboard'));
        $this->assertNotSame(500, $html->getStatusCode());
        $html->assertForbidden();

        $json = $this->getJson(route('dashboard'));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertForbidden();
    }

    public function test_sidebar_hides_console_item_when_actor_cannot_view_dashboard(): void
    {
        $denied = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $denied->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($denied);
        $this->withSession($this->cabinetSession());

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertRenderedAdminSidebarDoesNotJump($html, 'users-without-dashboard.view');
        $this->assertStringNotContainsString('<p>Консоль</p>', $html);
        $this->assertStringNotContainsString('fa-solid fa-house', $html);
    }

    public function test_guest_mutating_cabinet_does_not_return_server_error(): void
    {
        Auth::logout();

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $web = $this->call($method, route('dashboard'));
            $this->assertNotSame(500, $web->getStatusCode(), $method.' HTML гость');
            $this->assertTrue(
                $web->isRedirect() || in_array($web->getStatusCode(), [401, 403, 405, 419], true),
                $method.' HTML гость: редирект/401/403/405/419, получено '.$web->getStatusCode()
            );

            $json = $this->json($method, route('dashboard'));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость');
            $this->assertContains($json->getStatusCode(), [401, 403, 405, 419]);
        }
    }

    public function test_admin_non_ajax_post_to_cabinet_keeps_layout_and_is_not_empty_200(): void
    {
        $response = $this->from(route('dashboard'))
            ->post(route('dashboard'));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302]);

        if ($response->isRedirect()) {
            return;
        }

        $html = $response->assertOk()->getContent();
        $this->assertRenderedAdminSidebarDoesNotJump($html, 'cabinet-post');
    }

    public function test_admin_json_post_to_cabinet_does_not_return_server_error(): void
    {
        $response = $this->postJson(route('dashboard'));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(204, $response->getStatusCode());
    }

    public function test_landing_mutating_verbs_do_not_return_server_error_or_empty_ok(): void
    {
        Auth::logout();

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $web = $this->call($method, route('landing.home'));
            $this->assertNotSame(500, $web->getStatusCode(), $method.' HTML лендинг');
            if ($web->getStatusCode() === 200) {
                $this->assertNotSame('', trim((string) $web->getContent()), $method.' лендинг пустой 200');
            }

            $json = $this->json($method, route('landing.home'));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON лендинг');
        }
    }
}
