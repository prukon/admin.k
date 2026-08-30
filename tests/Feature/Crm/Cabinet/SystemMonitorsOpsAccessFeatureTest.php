<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Support\OpsMonitor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * P1: доступ к GET /cabinet/system-monitors/ops и видимости оверлея «Пульт».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsAccessFeatureTest extends SystemMonitorsTestCase
{
    public function test_guest_cannot_read_ops_via_json_or_html(): void
    {
        Auth::logout();

        $json = $this->getJson($this->opsUrl());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue(
            $json->isRedirect() || $json->status() === 401,
            'гость JSON: редирект или 401, получено '.$json->getStatusCode()
        );

        $html = $this->get($this->opsUrl());
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertTrue(
            $html->isRedirect() || in_array($html->getStatusCode(), [401, 403, 419], true),
            'гость HTML GET: отказ, получено '.$html->getStatusCode()
        );
    }

    public function test_guest_mutating_ops_is_not_empty_200(): void
    {
        Auth::logout();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->opsUrl());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
            $this->assertTrue(
                $json->isRedirect() || in_array($json->getStatusCode(), [401, 403, 404, 405, 419], true),
                $method.' JSON гость: отказ, получено '.$json->getStatusCode()
            );

            $html = $this->call($method, $this->opsUrl());
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML гость не 200');
            $this->assertTrue(
                $html->isRedirect() || in_array($html->getStatusCode(), [401, 403, 404, 405, 419], true),
                $method.' HTML гость: отказ, получено '.$html->getStatusCode()
            );
        }
    }

    public function test_admin_without_permission_gets_403_on_json_and_html(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $json = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertNotSame('', trim((string) $json->getContent()));

        $html = $this->actingAs($this->user)->get($this->opsUrl());
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $html->assertForbidden();
    }

    public function test_admin_with_hidden_permission_can_read_ops_and_sees_overlay(): void
    {
        $this->asAdmin();
        $this->grantSystemMonitorsView($this->user);
        $this->user->forceFill(['system_monitors' => false])->save();

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="js-ops-monitors"', $html);
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bsystem-monitors-on\b/', $html);
    }

    public function test_trainer_with_granted_permission_can_read_ops(): void
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner);
        $this->grantSystemMonitorsView($trainer);

        $this->actingInCurrentPartner($trainer)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_student_with_granted_permission_can_read_ops(): void
    {
        $student = $this->createUserWithRole('user', $this->partner);
        $this->grantSystemMonitorsView($student);

        $response = $this->actingInCurrentPartner($student)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('window_hours', 24);
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_student_without_permission_gets_403_not_empty_200(): void
    {
        $student = $this->createUserWithRole('user', $this->partner);

        $json = $this->actingInCurrentPartner($student)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertNotSame('', trim((string) $json->getContent()));
    }

    public function test_route_requires_auth_and_system_monitors_permission(): void
    {
        $route = Route::getRoutes()->getByName('cabinet.system-monitors.ops');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('can:settings.systemMonitors.view', $middleware);
        $this->assertContains('auth', $middleware);
    }

    public function test_admin_without_permission_does_not_see_cached_last_message(): void
    {
        $this->asSuperadmin();
        OpsMonitor::recordException(new RuntimeException('ops-forbidden-leak'));

        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $json = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertStringNotContainsString('ops-forbidden-leak', (string) $json->getContent());
        $this->assertArrayNotHasKey('errors', $json->json() ?? []);
    }
}
