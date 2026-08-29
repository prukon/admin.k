<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

/**
 * P1: нативный GET без X-Requested-With — JSON, не белая страница.
 * Мутации URI — не сырой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOnlineUsersNonAjaxSafetyNetFeatureTest extends SystemMonitorsTestCase
{
    public function test_native_get_returns_json_snapshot_not_blank_html_page(): void
    {
        $this->asSuperadmin();
        $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Натив',
            'name' => 'Пинг',
            'last_seen_at' => now(),
        ]);

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->get($this->onlineUsersUrl());

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('partners.0.users.0.name', 'Натив Пинг');
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
                ->call($method, $this->onlineUsersUrl());
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML');

            $json = $this->actingAs($this->user)
                ->json($method, $this->onlineUsersUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не пустой 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON');
        }
    }
}
