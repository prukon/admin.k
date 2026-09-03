<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Support\OpsMonitor;
use RuntimeException;

/**
 * P1: нативный GET /cabinet/system-monitors/ops — JSON, не белая страница.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsNonAjaxSafetyNetFeatureTest extends SystemMonitorsTestCase
{
    public function test_native_get_returns_json_snapshot_not_blank_html_page(): void
    {
        $this->asSuperadmin();

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->get($this->opsUrl());

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('window_hours', 24)
            ->assertJsonPath('till.failed_intents', 0)
            ->assertJsonPath('day.turnover', 0)
            ->assertJsonPath('day.commission', 0)
            ->assertJsonPath('day.payments_count', 0)
            ->assertJsonPath('yesterday.turnover', 0)
            ->assertJsonPath('yesterday.commission', 0)
            ->assertJsonPath('yesterday.payments_count', 0);
        $this->assertIsInt($response->json('day.turnover'));
        $this->assertIsInt($response->json('yesterday.payments_count'));
        $this->assertStringContainsString(
            'json',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_native_get_from_chat_still_returns_json_not_cabinet_html(): void
    {
        $this->asSuperadmin();

        $response = $this->from(route('chat.index'))
            ->actingAs($this->user)
            ->get($this->opsUrl());

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('queue.overdue_payouts', 0);
        $this->assertStringContainsString(
            'json',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
        $this->assertStringNotContainsString('id="js-ops-monitors"', (string) $response->getContent());
    }

    public function test_native_get_includes_recent_errors_and_is_not_blank_200(): void
    {
        $this->asSuperadmin();
        OpsMonitor::recordException(new RuntimeException('native-recent-ops'));

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->get($this->opsUrl());

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('errors.last_class', 'RuntimeException')
            ->assertJsonPath('errors.recent.0.class', 'RuntimeException');
        $this->assertStringContainsString('native-recent-ops', (string) $response->json('errors.last_message'));
        $this->assertStringContainsString('native-recent-ops', (string) $response->json('errors.recent.0.message'));
        $this->assertStringContainsString(
            'json',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_native_mutating_methods_are_not_empty_200(): void
    {
        $this->asSuperadmin();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $html = $this->from(route('dashboard'))
                ->actingAs($this->user)
                ->call($method, $this->opsUrl());
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML');

            $json = $this->actingAs($this->user)
                ->json($method, $this->opsUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не пустой 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON');
        }
    }
}
