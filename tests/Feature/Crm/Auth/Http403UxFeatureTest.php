<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use App\Support\OpsMonitor;
use Illuminate\Support\ViewErrorBag;

/**
 * UX HTML 403: гость — layouts.app без сайдбара admin2 (баг Echo → 500);
 * залогиненный — admin2; пульт errors.count не растёт на гостевом 403.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class Http403UxFeatureTest extends SessionAuthTestCase
{
    public function test_guest_403_view_renders_login_layout_not_admin_sidebar(): void
    {
        $this->actingAsGuest();

        $html = view('errors.403')->render();
        $this->assertStringContainsString('403 — Доступ запрещён', $html);
        $this->assertStringContainsString(route('login', absolute: false), $html);
        $this->assertStringContainsString('Войти', $html);
        $this->assertStringNotContainsString('На главную', $html);
        $this->assertStringNotContainsString('main-sidebar', $html);
        $this->assertStringNotContainsString('js-ops-monitors', $html);
        $this->assertStringContainsString('noindex, nofollow', $html);
    }

    public function test_authenticated_403_http_uses_admin_layout_and_home_button(): void
    {
        $this->asAdmin();

        $response = $this->post(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->foreignUser->id)
        );
        $this->assertNotServerError($response, 'auth HTML 403');
        $response->assertForbidden();
        $response->assertSee('403 — Доступ запрещён', false);
        $response->assertSee('На главную', false);
        $response->assertSee('main-sidebar', false);
        $response->assertDontSee('>Войти</a>', false);
    }

    public function test_guest_broadcasting_auth_is_forbidden_html_not_server_error(): void
    {
        $this->actingAsGuest();

        $response = $this->post($this->broadcastingAuthUrl(), $this->broadcastingAuthPayload());
        $this->assertNotServerError($response, 'гость Echo HTML 403');
        $response->assertForbidden();
        $response->assertSee('403 — Доступ запрещён', false);
        $response->assertSee('Войти', false);
        $response->assertDontSee('main-sidebar', false);
        $this->assertStringNotContainsString('Attempt to read property', $response->getContent());
        $this->assertStringNotContainsString('js-ops-monitors', $response->getContent());
    }

    public function test_admin2_layout_renders_for_guest_without_role_error(): void
    {
        $this->actingAsGuest();
        view()->share('errors', new ViewErrorBag());

        $html = view('layouts.admin2')->render();
        $this->assertStringContainsString('Роль:', $html);
        $this->assertStringContainsString('Не указана', $html);
        $this->assertStringNotContainsString('Attempt to read property', $html);
    }

    public function test_guest_forbidden_html_does_not_increment_ops_error_counter(): void
    {
        $before = (int) OpsMonitor::snapshot()['errors']['count'];

        $this->actingAsGuest();
        $response = $this->post($this->broadcastingAuthUrl(), $this->broadcastingAuthPayload());
        $response->assertForbidden();

        $after = OpsMonitor::snapshot();
        $this->assertSame(
            $before,
            (int) $after['errors']['count'],
            'гость HTML 403 не должен писать ErrorException в строку 500 пульта'
        );
        $this->assertNotSame('ErrorException', $after['errors']['last_class']);
        $this->assertNotSame('ViewException', $after['errors']['last_class']);
    }

    public function test_forbidden_blade_switches_layout_by_auth_and_admin2_uses_nullsafe_role(): void
    {
        $forbidden = (string) file_get_contents(resource_path('views/errors/403.blade.php'));
        $this->assertStringContainsString(
            "@extends(auth()->check() ? 'layouts.admin2' : 'layouts.app')",
            $forbidden
        );
        $this->assertStringContainsString("route('login')", $forbidden);
        $this->assertStringContainsString('Войти', $forbidden);
        $this->assertStringContainsString('На главную', $forbidden);
        $this->assertStringContainsString('noindex, nofollow', $forbidden);
        $this->assertStringNotContainsString("@extends('layouts.admin2')", $forbidden);

        $admin2 = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));
        $this->assertStringContainsString('auth()->user()?->role?->label', $admin2);
        $this->assertStringContainsString('auth()->user()?->full_name', $admin2);
        $this->assertStringContainsString('auth()->user()?->role?->name', $admin2);
        $this->assertStringNotContainsString('optional(auth()->user()->role)', $admin2);
    }
}
