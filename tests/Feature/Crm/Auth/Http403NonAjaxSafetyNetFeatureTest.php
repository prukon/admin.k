<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

/**
 * P1: native POST/GET /broadcasting/auth без X-Requested-With —
 * как Echo (без Accept: application/json): 403 HTML, не пустой 200 и не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class Http403NonAjaxSafetyNetFeatureTest extends SessionAuthTestCase
{
    public function test_echo_like_guest_post_is_forbidden_html_not_empty_200(): void
    {
        $this->actingAsGuest();

        $response = $this->post($this->broadcastingAuthUrl(), $this->broadcastingAuthPayload());
        $this->assertNotServerError($response, 'Echo-like POST гость');
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('403 — Доступ запрещён', false);
        $response->assertSee('Войти', false);
        $response->assertDontSee('main-sidebar', false);
        $this->assertStringNotContainsString('Attempt to read property', $response->getContent());
    }

    public function test_native_authenticated_foreign_channel_is_forbidden_admin_layout(): void
    {
        $this->asAdmin();

        $response = $this->actingAs($this->user)->post(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->foreignUser->id)
        );
        $this->assertNotServerError($response, 'native чужой канал');
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $response->assertSee('На главную', false);
        $response->assertSee('main-sidebar', false);
    }

    public function test_native_authenticated_own_channel_is_ok_not_empty_200_html_error(): void
    {
        $this->enableBroadcastAuthDriver();
        $this->asAdmin();

        $response = $this->actingAs($this->user)->post(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->user->id)
        );
        $this->assertNotServerError($response, 'native свой канал');
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringNotContainsString('403 — Доступ запрещён', $response->getContent());
    }

    public function test_guest_get_broadcasting_auth_is_not_server_error(): void
    {
        $this->actingAsGuest();

        $response = $this->get($this->broadcastingAuthUrl());
        $this->assertNotServerError($response, 'GET broadcasting гость');
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 405], true) || $response->isRedirect(),
            'GET broadcasting гость: 403/405/redirect, получено '.$response->getStatusCode()
        );
    }
}
