<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Notification;

/**
 * HTTP-матрица GET/POST/PATCH/PUT/DELETE /password/reset и POST /password/email.
 * Ни один метод не 500; мутации кроме осмысленного POST не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PasswordResetFullAccessFeatureTest extends PasswordResetTestCase
{
    public function test_guest_http_matrix_on_password_request_never_returns_500(): void
    {
        $this->actingAsGuest();

        $getHtml = $this->get($this->passwordRequestUrl());
        $this->assertNotServerError($getHtml, 'GET HTML гость');
        $getHtml->assertOk();
        $this->assertStringContainsString('name="email"', $getHtml->getContent());

        $getJson = $this->getJson($this->passwordRequestUrl(), $this->ajaxHeaders());
        $this->assertNotServerError($getJson, 'GET JSON гость');
        $getJson->assertOk();
        $this->assertNotSame('', trim((string) $getJson->getContent()));

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->passwordRequestUrl(), [], $this->ajaxHeaders());
            $this->assertDeniedWithoutServerError($json, $method.' JSON гость /password/reset');

            $html = $this->call($method, $this->passwordRequestUrl());
            $this->assertDeniedWithoutServerError($html, $method.' HTML гость /password/reset');
        }
    }

    public function test_guest_http_matrix_on_password_email_never_returns_500(): void
    {
        $this->actingAsGuest();

        $postHtml = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => 'full-access-reset@example.test',
        ]);
        $this->assertNotServerError($postHtml, 'POST HTML гость');
        $this->assertNotEmptyOk($postHtml, 'POST HTML гость');

        $postJson = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => 'full-access-reset@example.test',
        ], $this->ajaxHeaders());
        $this->assertNotServerError($postJson, 'POST JSON гость');
        $this->assertNotSame(200, $postJson->getStatusCode());

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->passwordEmailUrl(), [
                'email' => 'full-access-reset@example.test',
            ], $this->ajaxHeaders());
            $this->assertDeniedWithoutServerError($json, $method.' JSON гость /password/email');

            $html = $this->call($method, $this->passwordEmailUrl(), [
                'email' => 'full-access-reset@example.test',
            ]);
            $this->assertDeniedWithoutServerError($html, $method.' HTML гость /password/email');
        }
    }

    public function test_authenticated_http_matrix_on_password_email_never_returns_500(): void
    {
        $this->asAdmin();

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->actingAs($this->user)
                ->json($method, $this->passwordEmailUrl(), [
                    'email' => (string) $this->user->email,
                ], $this->ajaxHeaders());
            $this->assertNotServerError($json, $method.' JSON залогиненный');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON залогиненный не 200');

            $html = $this->actingAs($this->user)->call($method, $this->passwordEmailUrl(), [
                'email' => (string) $this->user->email,
            ]);
            $this->assertNotServerError($html, $method.' HTML залогиненный');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML залогиненный не 200');
        }
    }

    public function test_signed_in_user_can_submit_reset_link_without_being_forbidden(): void
    {
        $this->asAdmin();
        Notification::fake();

        $html = $this->actingAs($this->user)->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => (string) $this->user->email,
        ]);
        $this->assertNotServerError($html, 'POST HTML залогиненный');
        $this->assertNotSame(403, $html->getStatusCode());
        $html->assertRedirect($this->passwordRequestUrl());
        $html->assertSessionHas('status', $this->sentStatusMessage());

        Notification::fake();

        $json = $this->actingAs($this->user)->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => (string) $this->user->email,
        ], $this->ajaxHeaders());
        $this->assertNotServerError($json, 'POST JSON залогиненный');
        $this->assertNotSame(403, $json->getStatusCode());
        $this->assertTrue(
            $json->status() === 200 || $json->status() === 422,
            'залогиненный JSON POST: 200 или throttle 422, получено '.$json->getStatusCode()
        );
        if ($json->status() === 200) {
            $json->assertJsonPath('message', $this->sentStatusMessage());
        } else {
            $json->assertJsonValidationErrors(['email']);
        }
    }

    public function test_guest_json_get_reset_form_is_html_not_empty_payload(): void
    {
        $this->actingAsGuest();

        $response = $this->getJson($this->passwordRequestUrl());
        $this->assertNotServerError($response, 'getJson /password/reset гость');
        $response->assertOk();
        $this->assertStringContainsString('id="forgot-password-form"', $response->getContent());
        $this->assertStringContainsString('name="email"', $response->getContent());
        $this->assertStringNotContainsString('"user":', $response->getContent());
    }
}
