<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * P1: JSON / X-Requested-With контракт POST /login —
 * 422 по полям при пустых значениях; неуспешный пароль/email не 500 и не пустой 200;
 * успешный AJAX — 204 (Laravel wantsJson), не 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LoginRememberAjaxContractFeatureTest extends SessionAuthTestCase
{
    public function test_ajax_empty_fields_return_422_errors_on_email_and_password(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->loginUrl())->postJson($this->loginUrl(), [
            'email' => '',
            'password' => '',
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX пустые поля');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('errors.email.0', 'Поле электронная почта обязательно для заполнения.')
            ->assertJsonPath('errors.password.0', 'Поле пароль обязательно для заполнения.');
        $this->assertIsArray($response->json('errors'));
        $this->assertArrayNotHasKey('remember', $response->json('errors') ?? []);
    }

    public function test_ajax_unknown_email_returns_field_error_not_server_error(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->loginUrl())->postJson($this->loginUrl(), [
            'email' => 'nobody-login@example.test',
            'password' => 'any-password',
        ], $this->ajaxHeaders());

        $this->assertFailedLoginFieldError($response, 'email', 'Такой email не найден.');
        if ($response->status() === 422) {
            $this->assertArrayNotHasKey('password', $response->json('errors') ?? []);
        } else {
            $this->assertFalse(session('errors')->has('password'));
        }
    }

    public function test_ajax_wrong_password_returns_password_error_not_email_error(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        Auth::logout();

        $response = $this->from($this->loginUrl())->postJson($this->loginUrl(), [
            'email' => $email,
            'password' => 'ajax-wrong-password',
        ], $this->ajaxHeaders());

        $this->assertFailedLoginFieldError($response, 'password', 'Неправильный пароль.');
        if ($response->status() === 422) {
            $this->assertArrayNotHasKey('email', $response->json('errors') ?? []);
        } else {
            $this->assertFalse(session('errors')->has('email'));
        }
    }

    public function test_ajax_successful_login_is_not_empty_200(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->user->password = 'ajax-login-secret';
        $this->user->save();
        Auth::logout();

        $response = $this->from($this->loginUrl())->postJson($this->loginUrl(), [
            'email' => $email,
            'password' => 'ajax-login-secret',
            'remember' => true,
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX успешный вход');
        $this->assertNotSame(200, $response->getStatusCode(), 'AJAX успешный вход не пустой 200');
        $this->assertTrue(
            $response->status() === 204 || $response->isRedirect(),
            'AJAX успешный вход: 204 или 302, получено '.$response->getStatusCode()
        );
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_ajax_already_authenticated_post_is_redirect_not_500(): void
    {
        $this->asAdmin();

        $response = $this->actingAs($this->user)
            ->postJson($this->loginUrl(), [
                'email' => (string) $this->user->email,
                'password' => 'password',
            ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX POST /login уже залогинен');
        $this->assertNotEmptyOk($response, 'AJAX POST /login уже залогинен');
        $response->assertRedirect('/cabinet');
    }
}
