<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Иконки кабинета и лендинга — локальный Font Awesome 6, без Kit/CDN.
 * UX-баг: Kit качал шрифт с ka-f.fontawesome.com после текста пунктов → меню дёргалось.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class FontAwesomeSelfHostedFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
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

    private function assertHtmlUsesLocalFontAwesomeNotKit(string $html, string $page): void
    {
        $this->assertStringContainsString('plugins/fontawesome-free/css/all.min.css', $html, $page);
        $this->assertStringContainsString('fa-solid-900.woff2', $html, $page);
        $this->assertStringNotContainsString('ka-f.fontawesome.com', $html, $page);
        $this->assertStringNotContainsString('js/fontawesome/fontawesome.js', $html, $page);
        $this->assertStringNotContainsString('FontAwesomeKitConfig', $html, $page);
        $this->assertStringNotContainsString('kit.fontawesome.com', $html, $page);
        $this->assertNotSame('', trim($html), $page.': пустой 200');

        $preloadPos = strpos($html, 'fa-solid-900.woff2');
        $cssPos = strpos($html, 'fontawesome-free/css/all.min.css');
        $this->assertNotFalse($preloadPos, $page.': нет preload woff2');
        $this->assertNotFalse($cssPos, $page.': нет all.min.css');
        $this->assertLessThan($cssPos, $preloadPos, $page.': preload должен быть до CSS');
    }

    public function test_local_fa6_webfonts_and_css_are_present(): void
    {
        $root = public_path('plugins/fontawesome-free');

        $this->assertFileExists($root.'/css/all.min.css');
        $this->assertFileExists($root.'/css/v4-shims.min.css');
        $this->assertFileExists($root.'/webfonts/fa-solid-900.woff2');
        $this->assertFileExists($root.'/webfonts/fa-regular-400.woff2');
        $this->assertFileExists($root.'/webfonts/fa-brands-400.woff2');

        $css = (string) file_get_contents($root.'/css/all.min.css');
        $this->assertStringContainsString('Font Awesome Free 6.5.1', $css);
        $this->assertStringContainsString('../webfonts/fa-solid-900.woff2', $css);
        $this->assertStringContainsString('.fa-solid', $css);
        $this->assertStringContainsString('.fa-house:before', $css);
        $this->assertStringNotContainsString('ka-f.fontawesome.com', $css);
    }

    public function test_kit_loader_is_removed(): void
    {
        $this->assertFileDoesNotExist(public_path('js/fontawesome/fontawesome.js'));
    }

    public function test_layouts_include_local_fontawesome_partial_and_not_kit(): void
    {
        $partial = (string) file_get_contents(resource_path('views/includes/fontawesome.blade.php'));
        $this->assertStringContainsString('plugins/fontawesome-free/css/all.min.css', $partial);
        $this->assertStringContainsString('fa-solid-900.woff2', $partial);
        $this->assertStringContainsString('rel="preload"', $partial);
        $this->assertStringNotContainsString('fontawesome.js', $partial);
        $this->assertStringNotContainsString('ka-f.fontawesome.com', $partial);
        $this->assertStringNotContainsString('<script', $partial);

        foreach (['layouts/admin2.blade.php', 'layouts/landingPage.blade.php'] as $relative) {
            $layout = (string) file_get_contents(resource_path('views/'.$relative));
            $this->assertStringContainsString("@include('includes.fontawesome')", $layout, $relative);
            $this->assertStringNotContainsString('js/fontawesome/fontawesome.js', $layout, $relative);
        }
    }

    public function test_cabinet_html_uses_local_fontawesome_and_reserves_nav_icon_slot(): void
    {
        $html = $this->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertHtmlUsesLocalFontAwesomeNotKit($html, 'cabinet');
        $this->assertStringContainsString('display: inline-block', $html);
        $this->assertStringContainsString('.nav-sidebar > .nav-item .nav-icon', $html);
        $this->assertStringContainsString('fa-solid fa-house', $html);
        $this->assertStringContainsString('<p>Консоль</p>', $html);
    }

    public function test_toolbar_list_and_cabinet_both_load_the_same_local_fontawesome(): void
    {
        $this->grantUsersView();

        $cabinet = $this->get(route('dashboard'))->assertOk()->getContent();
        $users = $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertHtmlUsesLocalFontAwesomeNotKit($cabinet, 'cabinet');
        $this->assertHtmlUsesLocalFontAwesomeNotKit($users, 'users');
    }

    public function test_guest_landing_uses_local_fontawesome_not_kit(): void
    {
        Auth::logout();

        $html = $this->get(route('landing.home'))
            ->assertOk()
            ->getContent();

        $this->assertHtmlUsesLocalFontAwesomeNotKit($html, 'landing');
        $this->assertStringNotContainsString('.nav-sidebar > .nav-item .nav-icon', $html);
    }

    public function test_guest_is_denied_cabinet_icons_page_without_server_error(): void
    {
        Auth::logout();

        $html = $this->get(route('dashboard'));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertTrue($html->isRedirect());

        $json = $this->getJson(route('dashboard'));
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertContains($json->getStatusCode(), [401, 302]);
    }

    public function test_manager_without_dashboard_view_gets_403_on_cabinet_icons_page(): void
    {
        $denied = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('dashboard'))->assertForbidden();
        $this->getJson(route('dashboard'))->assertForbidden();
    }
}
