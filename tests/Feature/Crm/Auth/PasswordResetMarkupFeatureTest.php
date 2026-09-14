<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Notification;

/**
 * P1: разметка /password/reset — первый GET, повтор после ошибки и успеха,
 * порядок email → submit, кнопка не disabled; layouts.app без сайдбара кабинета.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PasswordResetMarkupFeatureTest extends PasswordResetTestCase
{
    public function test_first_open_shows_enabled_submit_and_email_before_button(): void
    {
        $this->actingAsGuest();

        $html = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertFirstPaintSubmitIsEnabled($html);
        $blade = $this->forgotPasswordBladeSource();
        $this->assertStringContainsString("extends('layouts.app')", $blade);
        $this->assertStringNotContainsString('@can', $blade);
        $this->assertSubmitGuardDoesNotCancelNativePost($blade);
        $this->assertSubmitGuardDoesNotCancelNativePost($this->forgotPasswordInlineScriptFromHtml($html));
        $this->assertStringNotContainsString('main-sidebar', $html);
        $this->assertStringNotContainsString('js-ops-monitors', $html);
        $this->assertStringContainsString('noindex, nofollow', $html);

        $emailPos = strpos($html, 'id="email"');
        $submitPos = strpos($html, 'id="forgot-password-submit"');
        $this->assertNotFalse($emailPos);
        $this->assertNotFalse($submitPos);
        $this->assertLessThan($submitPos, $emailPos);
        $this->assertStringContainsString('autocomplete="email"', $html);
        $this->assertMatchesRegularExpression('/id="email"[^>]*\brequired\b/', $html);
    }

    public function test_unknown_email_rerender_keeps_typed_value_and_does_not_disable_controls(): void
    {
        $this->actingAsGuest();
        $typed = 'ghost-markup@example.test';

        $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => $typed,
        ])->assertRedirect($this->passwordRequestUrl());

        $html = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertFirstPaintSubmitIsEnabled($html);
        $this->assertStringContainsString('value="'.$typed.'"', $html);
        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('Мы не нашли пользователя с таким email адресом.', $html);
    }

    public function test_success_rerender_shows_status_alert_and_enabled_submit(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->actingAsGuest();
        Notification::fake();

        $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => $email,
        ])->assertRedirect($this->passwordRequestUrl());

        $html = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertFirstPaintSubmitIsEnabled($html);
        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringContainsString($this->sentStatusMessage(), $html);
        $this->assertStringNotContainsString('is-invalid', $html);
    }

    public function test_unique_constraint_follow_redirect_shows_success_not_field_error(): void
    {
        $this->actingAsGuest();
        Notification::fake();
        $this->mockBrokerThrowsUniqueConstraint();

        $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => 'race-markup@example.test',
        ])->assertRedirect($this->passwordRequestUrl())
            ->assertSessionHas('status', $this->sentStatusMessage());

        $html = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertFirstPaintSubmitIsEnabled($html);
        $this->assertStringContainsString($this->sentStatusMessage(), $html);
        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringNotContainsString('is-invalid', $html);
        $this->assertStringNotContainsString('Пожалуйста, подождите перед повторной попыткой.', $html);
    }

    public function test_email_field_is_never_disabled_on_error_or_success(): void
    {
        $this->actingAsGuest();

        $first = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertTrue((bool) preg_match('/<input[^>]*id="email"[^>]*>/', $first, $email));
        $this->assertStringNotContainsString('disabled', $email[0]);

        $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => '',
        ]);
        $afterError = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertTrue((bool) preg_match('/<input[^>]*id="email"[^>]*>/', $afterError, $emailAfter));
        $this->assertStringNotContainsString('disabled', $emailAfter[0]);
    }
}
