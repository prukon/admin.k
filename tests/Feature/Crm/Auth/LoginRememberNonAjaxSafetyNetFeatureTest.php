<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * P1: native POST /login без X-Requested-With — 302, сессия создана или errors по полям,
 * не пустой 200. Remember-cookie только если поле ушло.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LoginRememberNonAjaxSafetyNetFeatureTest extends SessionAuthTestCase
{
    public function test_native_post_creates_session_and_redirects_to_cabinet(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->user->password = 'native-login-secret';
        $this->user->save();
        Auth::logout();

        $response = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => $email,
            'password' => 'native-login-secret',
        ]);
        $this->assertNotServerError($response, 'native POST успех');
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect('/cabinet');
        $this->assertAuthenticatedAs($this->user);
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    }

    public function test_native_post_with_remember_queues_recaller_cookie(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->user->password = 'remember-login-secret';
        $this->user->save();
        Auth::logout();

        $response = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => $email,
            'password' => 'remember-login-secret',
            'remember' => 'on',
        ]);
        $this->assertNotServerError($response, 'native POST remember');
        $this->assertNotSame(419, $response->getStatusCode());
        $response->assertRedirect('/cabinet');
        $this->assertAuthenticatedAs($this->user);
        $response->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_native_failed_login_redirects_with_field_errors_not_empty_200(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        Auth::logout();

        $unknown = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => 'missing-native@example.test',
            'password' => 'x',
        ]);
        $this->assertFailedLoginFieldError($unknown, 'email', 'Такой email не найден.');
        $unknown->assertRedirect($this->loginUrl());

        $wrong = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => $email,
            'password' => 'wrong-native-password',
        ]);
        $this->assertFailedLoginFieldError($wrong, 'password', 'Неправильный пароль.');
        $wrong->assertRedirect($this->loginUrl());
        $this->assertGuest();
    }

    public function test_native_empty_fields_redirect_with_email_and_password_errors(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => '',
            'password' => '',
        ]);
        $this->assertNotServerError($response, 'native пустые поля');
        $this->assertNotEmptyOk($response, 'native пустые поля');
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertSame(
            'Поле электронная почта обязательно для заполнения.',
            session('errors')->first('email')
        );
        $this->assertSame(
            'Поле пароль обязательно для заполнения.',
            session('errors')->first('password')
        );
    }
}
