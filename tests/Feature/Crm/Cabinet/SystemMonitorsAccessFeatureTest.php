<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Support\SystemMonitors;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * P1: доступ к POST /cabinet/system-monitors и видимости переключателя —
 * гость, без права, с правом, роли admin/trainer/user.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsAccessFeatureTest extends SystemMonitorsTestCase
{
    public function test_guest_cannot_toggle_system_monitors_via_json_or_html(): void
    {
        Auth::logout();

        $json = $this->postJson($this->toggleUrl(), ['system_monitors' => 1]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue(
            $json->isRedirect() || $json->status() === 401,
            'гость JSON: редирект или 401, получено '.$json->getStatusCode()
        );

        $html = $this->from(route('login'))->post($this->toggleUrl(), ['system_monitors' => 1]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertTrue(
            $html->isRedirect() || in_array($html->getStatusCode(), [401, 403, 419], true),
            'гость HTML: редирект/401/403/419, получено '.$html->getStatusCode()
        );
    }

    public function test_guest_get_patch_put_delete_are_not_empty_200(): void
    {
        Auth::logout();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->toggleUrl(), ['system_monitors' => 1]);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
            $this->assertTrue(
                $json->isRedirect() || in_array($json->getStatusCode(), [401, 403, 404, 405, 419], true),
                $method.' JSON гость: отказ, получено '.$json->getStatusCode()
            );

            $html = $this->call($method, $this->toggleUrl(), ['system_monitors' => 1]);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML гость не 200');
            $this->assertTrue(
                $html->isRedirect() || in_array($html->getStatusCode(), [401, 403, 404, 405, 419], true),
                $method.' HTML гость: отказ, получено '.$html->getStatusCode()
            );
        }
    }

    public function test_admin_without_permission_gets_403_on_ajax_and_native_post(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $ajax = $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders());
        $ajax->assertForbidden();
        $this->assertNotSame('', trim((string) $ajax->getContent()), 'AJAX 403 не должен быть пустым телом');
        $this->assertFalse((bool) $this->user->fresh()->system_monitors);

        $native = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->post($this->toggleUrl(), ['system_monitors' => 1]);
        $this->assertNotSame(500, $native->getStatusCode());
        $this->assertNotSame(200, $native->getStatusCode());
        $native->assertForbidden();
        $this->assertFalse((bool) $this->user->fresh()->system_monitors);
    }

    public function test_trainer_and_student_without_permission_get_403(): void
    {
        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner, [
                'system_monitors' => false,
            ]);
            $this->actingInCurrentPartner($actor);

            $ajax = $this->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders());
            $this->assertNotSame(500, $ajax->getStatusCode(), $roleName.' JSON не 500');
            $ajax->assertForbidden();
            $this->assertFalse((bool) $actor->fresh()->system_monitors, $roleName.' флаг не должен включиться');
        }
    }

    public function test_admin_with_hidden_permission_can_toggle(): void
    {
        $this->asAdmin();
        $this->grantSystemMonitorsView($this->user);
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('system_monitors', true);

        $this->assertTrue((bool) $this->user->fresh()->system_monitors);
    }

    public function test_superadmin_can_toggle_without_explicit_permission_row(): void
    {
        $this->asSuperadmin();
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->assertFalse(
            \Illuminate\Support\Facades\DB::table('permission_role')
                ->where('partner_id', $this->partner->id)
                ->where('role_id', $this->user->role_id)
                ->where('permission_id', $this->permissionId(SystemMonitors::PERMISSION))
                ->exists(),
            'superadmin не обязан иметь строку в permission_role'
        );

        $this->actingAs($this->user)
            ->postJson($this->toggleUrl(), ['system_monitors' => 1], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('system_monitors', true);
    }

    public function test_route_requires_system_monitors_permission_middleware(): void
    {
        $route = Route::getRoutes()->getByName('cabinet.system-monitors.update');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('can:settings.systemMonitors.view', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertNotContains('can:settings.reverbOverlay.manage', $middleware);
    }
}
