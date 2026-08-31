<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * HTTP-матрица GET/POST/PATCH/PUT/DELETE /login: JSON и HTML, гость и залогиненный.
 * Ни один метод не 500; мутации кроме осмысленного POST не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LoginRememberFullAccessFeatureTest extends SessionAuthTestCase
{
    public function test_guest_http_matrix_on_login_never_returns_500(): void
    {
        $this->actingAsGuest();

        $getHtml = $this->get($this->loginUrl());
        $this->assertNotServerError($getHtml, 'GET HTML гость');
        $getHtml->assertOk();
        $this->assertStringContainsString('name="email"', $getHtml->getContent());

        $getJson = $this->getJson($this->loginUrl(), $this->ajaxHeaders());
        $this->assertNotServerError($getJson, 'GET JSON гость');
        $getJson->assertOk();
        $this->assertNotSame('', trim((string) $getJson->getContent()));

        $postHtml = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => 'full-access@example.test',
            'password' => 'x',
        ]);
        $this->assertNotServerError($postHtml, 'POST HTML гость');
        $this->assertNotEmptyOk($postHtml, 'POST HTML гость');

        $postJson = $this->from($this->loginUrl())->postJson($this->loginUrl(), [
            'email' => 'full-access@example.test',
            'password' => 'x',
        ], $this->ajaxHeaders());
        $this->assertNotServerError($postJson, 'POST JSON гость');
        $this->assertNotSame(200, $postJson->getStatusCode());

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->loginUrl(), [], $this->ajaxHeaders());
            $this->assertDeniedWithoutServerError($json, $method.' JSON гость');

            $html = $this->call($method, $this->loginUrl());
            $this->assertDeniedWithoutServerError($html, $method.' HTML гость');
        }
    }

    public function test_authenticated_http_matrix_on_login_never_returns_500(): void
    {
        $this->asAdmin();

        foreach (['GET', 'POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->actingAs($this->user)
                ->json($method, $this->loginUrl(), [
                    'email' => (string) $this->user->email,
                    'password' => 'password',
                ], $this->ajaxHeaders());
            $this->assertNotServerError($json, $method.' JSON залогиненный');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON залогиненный не 200');

            $html = $this->actingAs($this->user)->call($method, $this->loginUrl(), [
                'email' => (string) $this->user->email,
                'password' => 'password',
            ]);
            $this->assertNotServerError($html, $method.' HTML залогиненный');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML залогиненный не 200');
        }
    }

    public function test_guest_json_get_login_is_html_form_not_empty_payload(): void
    {
        $this->actingAsGuest();

        $response = $this->getJson($this->loginUrl());
        $this->assertNotServerError($response, 'getJson /login гость');
        $response->assertOk();
        $this->assertStringContainsString('id="remember"', $response->getContent());
        $this->assertStringContainsString('name="email"', $response->getContent());
        $this->assertStringNotContainsString('"user":', $response->getContent());
    }

    public function test_logout_http_matrix_never_returns_500(): void
    {
        $this->asAdmin();
        $html = $this->actingAs($this->user)->post(route('logout'));
        $this->assertNotServerError($html, 'POST /logout HTML');
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertGuest();

        $this->asAdmin();
        $json = $this->actingAs($this->user)->postJson(route('logout'), [], $this->ajaxHeaders());
        $this->assertNotServerError($json, 'POST /logout JSON');
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue(
            $json->status() === 204 || $json->isRedirect(),
            'JSON logout: 204 или redirect, получено '.$json->getStatusCode()
        );
        Auth::logout();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $asGuest = $this->json($method, route('logout'), [], $this->ajaxHeaders());
            $this->assertNotServerError($asGuest, $method.' /logout JSON гость');
            $this->assertNotSame(200, $asGuest->getStatusCode());
        }
    }
}
