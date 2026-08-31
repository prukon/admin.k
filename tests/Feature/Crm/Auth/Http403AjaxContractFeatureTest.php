<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

/**
 * P1: JSON-контракт POST /broadcasting/auth с Accept: application/json и X-Requested-With.
 * Гость и чужой канал — 403 JSON, не HTML 500. Свой канал — 200 JSON.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class Http403AjaxContractFeatureTest extends SessionAuthTestCase
{
    public function test_guest_json_broadcasting_auth_is_forbidden_json_not_html_500(): void
    {
        $this->actingAsGuest();

        $response = $this->postJson(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload(),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'JSON гость broadcasting');
        $response->assertForbidden();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringContainsString(
            'json',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertIsString($response->json('message'));
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
        $this->assertStringNotContainsString('Attempt to read property', $response->getContent());
        $this->assertStringNotContainsString('main-sidebar', $response->getContent());
        $this->assertStringNotContainsString('403 — Доступ запрещён', $response->getContent());
        $this->assertArrayNotHasKey('auth', $response->json() ?? []);
    }

    public function test_authenticated_json_foreign_channel_is_forbidden_json(): void
    {
        $this->asAdmin();

        $response = $this->actingAs($this->user)->postJson(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->foreignUser->id),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'JSON чужой канал');
        $response->assertForbidden();
        $this->assertIsString($response->json('message'));
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_authenticated_json_own_channel_returns_auth_payload(): void
    {
        $this->enableBroadcastAuthDriver();
        $this->asAdmin();

        $response = $this->actingAs($this->user)->postJson(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->user->id),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'JSON свой канал');
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringContainsString(
            'json',
            strtolower((string) $response->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }
}
