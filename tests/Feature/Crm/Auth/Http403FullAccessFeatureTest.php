<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

/**
 * HTTP-матрица GET/POST/PATCH/PUT/DELETE /broadcasting/auth: JSON и HTML,
 * гость и залогиненный. Ни один метод не 500; отказ не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class Http403FullAccessFeatureTest extends SessionAuthTestCase
{
    public function test_guest_http_matrix_on_broadcasting_auth_never_returns_500(): void
    {
        $this->actingAsGuest();
        $payload = $this->broadcastingAuthPayload();

        foreach (['GET', 'POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->broadcastingAuthUrl(), $payload, $this->ajaxHeaders());
            $this->assertNotServerError($json, $method.' JSON гость');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
            if ($json->getStatusCode() === 403) {
                $this->assertStringNotContainsString(
                    'Attempt to read property "role"',
                    (string) $json->getContent(),
                    $method.' JSON гость 403'
                );
            }

            $html = $this->call($method, $this->broadcastingAuthUrl(), $payload);
            $this->assertNotServerError($html, $method.' HTML гость');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML гость не 200');
            if ($html->getStatusCode() === 403) {
                $this->assertStringNotContainsString(
                    'Attempt to read property "role"',
                    (string) $html->getContent(),
                    $method.' HTML гость 403'
                );
                $this->assertStringNotContainsString('main-sidebar', (string) $html->getContent(), $method.' HTML гость 403');
            }
        }
    }

    public function test_authenticated_http_matrix_on_broadcasting_auth_never_returns_500(): void
    {
        $this->enableBroadcastAuthDriver();
        $this->asAdmin();
        $own = $this->broadcastingAuthPayload((int) $this->user->id);
        $foreign = $this->broadcastingAuthPayload((int) $this->foreignUser->id);

        foreach (['GET', 'POST'] as $method) {
            $jsonOwn = $this->actingAs($this->user)
                ->json($method, $this->broadcastingAuthUrl(), $own, $this->ajaxHeaders());
            $this->assertNotServerError($jsonOwn, $method.' JSON свой канал');
            if ($method === 'POST') {
                $jsonOwn->assertOk();
                $this->assertNotSame('', trim((string) $jsonOwn->getContent()));
            } else {
                $this->assertNotSame(500, $jsonOwn->getStatusCode());
            }

            $htmlForeign = $this->actingAs($this->user)
                ->call($method, $this->broadcastingAuthUrl(), $foreign);
            $this->assertNotServerError($htmlForeign, $method.' HTML чужой канал');
            if ($method === 'POST') {
                $htmlForeign->assertForbidden();
                $this->assertNotSame(200, $htmlForeign->getStatusCode());
            }
        }

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->actingAs($this->user)
                ->json($method, $this->broadcastingAuthUrl(), $own, $this->ajaxHeaders());
            $this->assertDeniedWithoutServerError($json, $method.' JSON залогиненный');

            $html = $this->actingAs($this->user)->call($method, $this->broadcastingAuthUrl(), $own);
            $this->assertDeniedWithoutServerError($html, $method.' HTML залогиненный');
        }
    }
}
