<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

/**
 * P1: native POST /password/email без X-Requested-With — 302 + status или errors.email,
 * не пустой 200. 1062 → тот же успех, не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PasswordResetNonAjaxSafetyNetFeatureTest extends PasswordResetTestCase
{
    public function test_native_post_sends_reset_link_and_redirects_with_status(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->actingAsGuest();
        Notification::fake();

        $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => $email,
        ]);
        $this->assertNotServerError($response, 'native POST успех');
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect($this->passwordRequestUrl());
        $response->assertSessionHas('status', $this->sentStatusMessage());
        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
        $this->assertGuest();
    }

    public function test_native_unique_constraint_redirects_with_status_not_500(): void
    {
        $this->actingAsGuest();
        Notification::fake();
        $before = $this->opsErrorCount();
        $this->mockBrokerThrowsUniqueConstraint();

        $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => 'race-native@example.test',
        ]);
        $this->assertUniqueConstraintDoesNotBumpOpsMonitor($response, $before);
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect($this->passwordRequestUrl());
        $response->assertSessionHas('status', $this->sentStatusMessage());
        $response->assertSessionMissing('errors');
        Notification::assertNothingSent();
    }

    public function test_native_empty_email_redirects_with_error_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => '',
        ]);
        $this->assertNotServerError($response, 'native пустой email');
        $this->assertNotEmptyOk($response, 'native пустой email');
        $response->assertRedirect($this->passwordRequestUrl());
        $response->assertSessionHasErrors('email');
        $this->assertSame('Укажите email.', session('errors')->first('email'));
    }

    public function test_native_invalid_email_redirects_with_error_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => 'not-an-email',
        ]);
        $this->assertNotServerError($response, 'native некорректный email');
        $this->assertNotEmptyOk($response, 'native некорректный email');
        $response->assertRedirect($this->passwordRequestUrl());
        $response->assertSessionHasErrors('email');
        $this->assertSame('Введите корректный email.', session('errors')->first('email'));
    }

    public function test_native_too_long_email_redirects_with_error_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => $this->tooLongEmail(),
        ]);
        $this->assertNotServerError($response, 'native слишком длинный email');
        $this->assertNotEmptyOk($response, 'native слишком длинный email');
        $response->assertRedirect($this->passwordRequestUrl());
        $response->assertSessionHasErrors('email');
        $this->assertNotSame('', (string) session('errors')->first('email'));
    }

    public function test_native_unknown_email_redirects_with_error_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => 'missing-native-reset@example.test',
        ]);
        $this->assertNotServerError($response, 'native неизвестный email');
        $this->assertNotEmptyOk($response, 'native неизвестный email');
        $response->assertRedirect($this->passwordRequestUrl());
        $response->assertSessionHasErrors('email');
        $this->assertSame(
            'Мы не нашли пользователя с таким email адресом.',
            session('errors')->first('email')
        );
    }

    public function test_native_second_post_within_throttle_is_not_500(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->actingAsGuest();
        Notification::fake();

        $first = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => $email,
        ]);
        $this->assertNotServerError($first, 'native первый POST');
        $first->assertSessionHas('status');
        Notification::assertSentTo($this->user, ResetPasswordNotification::class);

        Notification::fake();

        $second = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => $email,
        ]);
        $this->assertNotServerError($second, 'native throttle');
        $this->assertNotEmptyOk($second, 'native throttle');
        $second->assertRedirect($this->passwordRequestUrl());
        $second->assertSessionHasErrors('email');
        $this->assertSame(
            'Пожалуйста, подождите перед повторной попыткой.',
            session('errors')->first('email')
        );
        Notification::assertNothingSent();
    }
}
