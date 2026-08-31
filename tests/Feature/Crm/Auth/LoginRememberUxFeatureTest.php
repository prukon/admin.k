<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * UX /login: «Запомнить меня» с первого GET включена; после old input — как в POST;
 * ошибки под полями; порядок email → password → remember.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LoginRememberUxFeatureTest extends SessionAuthTestCase
{
    public function test_guest_login_form_has_remember_checked_on_first_get(): void
    {
        $this->actingAsGuest();

        $html = $this->get($this->loginUrl())->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="remember"[^>]*\bchecked\b/', $html);
        $this->assertStringContainsString('name="remember"', $html);
        $this->assertStringContainsString('Запомнить меня', $html);
    }

    public function test_failed_login_without_remember_keeps_checkbox_off(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        Auth::logout();

        $response = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => $email,
            'password' => 'wrong-password-remember-off',
        ]);
        $this->assertNotServerError($response, 'POST без remember');
        $response->assertRedirect($this->loginUrl());
        $response->assertSessionHasErrors('password');

        $html = $this->get($this->loginUrl())->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="remember"[^>]*\bchecked\b/', $html);
        $this->assertStringContainsString('Неправильный пароль.', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('value="'.e($email).'"', $html);
    }

    public function test_failed_login_with_remember_keeps_checkbox_on(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        Auth::logout();

        $response = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => $email,
            'password' => 'wrong-password-remember-on',
            'remember' => 'on',
        ]);
        $this->assertNotServerError($response, 'POST с remember');
        $response->assertRedirect($this->loginUrl());

        $html = $this->get($this->loginUrl())->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="remember"[^>]*\bchecked\b/', $html);
    }

    public function test_unknown_email_error_renders_under_email_not_password(): void
    {
        $this->actingAsGuest();

        $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => 'ghost-ux@example.test',
            'password' => 'secret',
        ])->assertRedirect($this->loginUrl());

        $html = $this->get($this->loginUrl())->assertOk()->getContent();
        $this->assertStringContainsString('Такой email не найден.', $html);
        $this->assertStringNotContainsString('Неправильный пароль.', $html);

        $emailPos = strpos($html, 'id="email"');
        $passwordPos = strpos($html, 'id="password"');
        $this->assertNotFalse($emailPos);
        $this->assertNotFalse($passwordPos);
        $emailBlock = substr($html, $emailPos, $passwordPos - $emailPos);
        $this->assertStringContainsString('is-invalid', $emailBlock);
        $this->assertStringContainsString('Такой email не найден.', $emailBlock);
    }

    public function test_login_fields_render_in_email_password_remember_order(): void
    {
        $this->actingAsGuest();

        $html = $this->get($this->loginUrl())->assertOk()->getContent();
        $emailPos = strpos($html, 'id="email"');
        $passwordPos = strpos($html, 'id="password"');
        $rememberPos = strpos($html, 'id="remember"');
        $this->assertNotFalse($emailPos);
        $this->assertNotFalse($passwordPos);
        $this->assertNotFalse($rememberPos);
        $this->assertLessThan($passwordPos, $emailPos);
        $this->assertLessThan($rememberPos, $passwordPos);
    }

    public function test_login_blade_does_not_force_remember_from_old_without_old_input(): void
    {
        $source = (string) file_get_contents(resource_path('views/auth/login.blade.php'));
        $this->assertStringContainsString(
            "session()->hasOldInput() ? (old('remember') ? 'checked' : '') : 'checked'",
            $source
        );
        $this->assertStringNotContainsString("old('remember', true)", $source);
        $this->assertStringNotContainsString("old('remember') ? 'checked' : 'checked'", $source);
        $this->assertStringContainsString('method="POST"', $source);
        $this->assertStringContainsString("route('login')", $source);
        $this->assertStringNotContainsString('preventDefault', $source);
        $this->assertStringNotContainsString('$.ajax', $source);
        $this->assertStringNotContainsString('openLogin', $source);
    }

    public function test_opening_login_again_as_guest_after_logout_restores_default_checked(): void
    {
        $this->asAdmin();
        $this->post(route('logout'));
        $this->assertGuest();

        $html = $this->get($this->loginUrl())->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="remember"[^>]*\bchecked\b/', $html);
    }
}
