<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Notification;

/**
 * UX /password/reset: ошибки под email; disabled только в setTimeout;
 * native POST без AJAX; первый GET не disabled.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PasswordResetUxFeatureTest extends PasswordResetTestCase
{
    public function test_guest_form_has_submit_guard_ids_and_is_native_post(): void
    {
        $this->actingAsGuest();

        $html = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertFirstPaintSubmitIsEnabled($html);
        $this->assertStringContainsString('id="forgot-password-form"', $html);
        $this->assertStringContainsString('id="forgot-password-submit"', $html);
        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('Отправить пароль на почту', $html);
        $this->assertSubmitGuardDoesNotCancelNativePost($this->forgotPasswordInlineScriptFromHtml($html));
    }

    public function test_unknown_email_error_renders_under_email_field(): void
    {
        $this->actingAsGuest();

        $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => 'ghost-reset-ux@example.test',
        ])->assertRedirect($this->passwordRequestUrl());

        $html = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertFirstPaintSubmitIsEnabled($html);
        $this->assertStringContainsString('Мы не нашли пользователя с таким email адресом.', $html);
        $this->assertStringContainsString('is-invalid', $html);

        $emailPos = strpos($html, 'id="email"');
        $submitPos = strpos($html, 'id="forgot-password-submit"');
        $this->assertNotFalse($emailPos);
        $this->assertNotFalse($submitPos);
        $emailBlock = substr($html, $emailPos, $submitPos - $emailPos);
        $this->assertStringContainsString('is-invalid', $emailBlock);
        $this->assertStringContainsString('Мы не нашли пользователя с таким email адресом.', $emailBlock);
    }

    public function test_success_status_renders_as_alert_not_under_email(): void
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
        $this->assertStringContainsString($this->sentStatusMessage(), $html);
        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringNotContainsString('is-invalid', $html);
    }

    public function test_unique_constraint_follow_redirect_shows_success_alert_not_throttle(): void
    {
        $this->actingAsGuest();
        Notification::fake();
        $this->mockBrokerThrowsUniqueConstraint();

        $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => 'race-ux@example.test',
        ])->assertRedirect($this->passwordRequestUrl())
            ->assertSessionHas('status', $this->sentStatusMessage());

        $html = $this->get($this->passwordRequestUrl())->assertOk()->getContent();
        $this->assertFirstPaintSubmitIsEnabled($html);
        $this->assertStringContainsString($this->sentStatusMessage(), $html);
        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringNotContainsString('is-invalid', $html);
        $this->assertStringNotContainsString('Пожалуйста, подождите перед повторной попыткой.', $html);
    }

    public function test_blade_submit_guard_does_not_cancel_native_post_and_is_not_ajax(): void
    {
        $source = $this->forgotPasswordBladeSource();
        $this->assertStringContainsString('method="POST"', $source);
        $this->assertStringContainsString("route('password.email')", $source);
        $this->assertStringContainsString('id="forgot-password-form"', $source);
        $this->assertSubmitGuardDoesNotCancelNativePost($source);
        $this->assertSame(1, substr_count($source, 'id="forgot-password-form"'));
        $this->assertSame(1, substr_count($source, 'id="forgot-password-submit"'));
        $this->assertStringNotContainsString('@can', $source);
    }
}
