<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use Illuminate\Support\Facades\Auth;

/**
 * HTTP-матрица GET /cabinet/system-monitors/ops для строки Welcome:
 * гость / без права / с правом, JSON и нативный GET, мутации не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsWelcomeAccountingFullAccessFeatureTest extends SystemMonitorsTestCase
{
    public function test_guest_is_denied_on_ops_welcome_snapshot_json_and_html(): void
    {
        Auth::logout();

        foreach (['GET'] as $method) {
            $json = $this->json($method, $this->opsUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
            $this->assertTrue(
                $json->isRedirect() || in_array($json->getStatusCode(), [401, 403, 419], true),
                $method.' JSON гость: отказ, получено '.$json->getStatusCode()
            );
            $this->assertArrayNotHasKey('welcome', $json->json() ?? []);

            $html = $this->call($method, $this->opsUrl());
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML гость не 200');
        }
    }

    public function test_admin_without_permission_gets_403_not_empty_200_on_ops_welcome(): void
    {
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $json = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertNotSame('', trim((string) $json->getContent()));
        $this->assertArrayNotHasKey('welcome', $json->json() ?? []);

        $html = $this->actingAs($this->user)->get($this->opsUrl());
        $html->assertForbidden();
        $this->assertNotSame(200, $html->getStatusCode());
    }

    public function test_authorized_operator_gets_welcome_snapshot_on_ajax_and_native_get(): void
    {
        $this->asAdmin();
        $this->grantSystemMonitorsView($this->user);

        $ajax = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('welcome.missing_count', 0)
            ->assertJsonPath('welcome.last_user_id', null);
        $this->assertIsInt($ajax->json('welcome.missing_count'));
        $this->assertArrayNotHasKey('email', $ajax->json('welcome'));
        $this->assertNotSame('', trim((string) $ajax->getContent()));

        $native = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->get($this->opsUrl())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('welcome.missing_count', 0);
        $this->assertStringContainsString(
            'json',
            strtolower((string) $native->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $native->getContent()));
    }

    public function test_trainer_and_student_with_permission_see_welcome_counts_without_email(): void
    {
        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner);
            $this->grantSystemMonitorsView($actor);

            $response = $this->actingInCurrentPartner($actor)
                ->getJson($this->opsUrl(), $this->ajaxHeaders())
                ->assertOk()
                ->assertJsonPath('ok', true);
            $this->assertArrayHasKey('missing_count', $response->json('welcome'));
            $this->assertArrayNotHasKey('email', $response->json('welcome'));
        }
    }

    public function test_mutating_ops_welcome_is_not_empty_200_for_operator_and_guest(): void
    {
        $this->asSuperadmin();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->actingAs($this->user)
                ->json($method, $this->opsUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON оператор не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON оператор не 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON оператор');

            $html = $this->from(route('dashboard'))
                ->actingAs($this->user)
                ->call($method, $this->opsUrl());
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML оператор не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML оператор не 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML оператор');
        }

        Auth::logout();
        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->opsUrl());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
        }
    }
}
