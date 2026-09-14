<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

/**
 * P1: JSON / X-Requested-With контракт POST /password/email —
 * 422 под email при пустом/некорректном; 1062 → JSON 200 как успех, не 500;
 * неизвестный email — 422 errors.email.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PasswordResetAjaxContractFeatureTest extends PasswordResetTestCase
{
    public function test_ajax_empty_email_returns_422_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => '',
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX пустой email');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Укажите email.');
        $this->assertIsArray($response->json('errors'));
    }

    public function test_ajax_missing_email_field_returns_422_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->postJson(
            $this->passwordEmailUrl(),
            [],
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'AJAX без поля email');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Укажите email.');
    }

    public function test_ajax_invalid_email_returns_422_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => 'not-an-email',
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX некорректный email');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Введите корректный email.');
    }

    public function test_ajax_too_long_email_returns_422_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => $this->tooLongEmail(),
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX слишком длинный email');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        $this->assertNotSame('', (string) $response->json('errors.email.0'));
    }

    public function test_ajax_unknown_email_returns_422_under_email(): void
    {
        $this->actingAsGuest();

        $response = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => 'nobody-reset@example.test',
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX неизвестный email');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Мы не нашли пользователя с таким email адресом.');
        $this->assertIsArray($response->json('errors'));
    }

    public function test_ajax_unique_constraint_returns_success_json_not_500(): void
    {
        $this->actingAsGuest();
        Notification::fake();
        $before = $this->opsErrorCount();
        $this->mockBrokerThrowsUniqueConstraint();

        $response = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => 'race-reset@example.test',
        ], $this->ajaxHeaders());

        $this->assertUniqueConstraintDoesNotBumpOpsMonitor($response, $before);
        $response->assertOk()
            ->assertJsonPath('message', $this->sentStatusMessage());
        $this->assertArrayNotHasKey('errors', $response->json() ?? []);
        $this->assertStringNotContainsString('подождите', (string) $response->json('message'));
        Notification::assertNothingSent();
    }

    public function test_ajax_successful_reset_link_is_json_200_with_status_message(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->actingAsGuest();
        Notification::fake();

        $response = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => $email,
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX успех');
        $response->assertOk()
            ->assertJsonPath('message', $this->sentStatusMessage());
        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    }

    public function test_ajax_second_post_within_throttle_returns_422_under_email(): void
    {
        $this->asAdmin();
        $email = (string) $this->user->email;
        $this->actingAsGuest();
        Notification::fake();

        $first = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => $email,
        ], $this->ajaxHeaders());
        $this->assertNotServerError($first, 'AJAX первый POST');
        $first->assertOk();

        Notification::fake();

        $second = $this->from($this->passwordRequestUrl())->postJson($this->passwordEmailUrl(), [
            'email' => $email,
        ], $this->ajaxHeaders());
        $this->assertNotServerError($second, 'AJAX throttle');
        $second->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Пожалуйста, подождите перед повторной попыткой.');
        Notification::assertNothingSent();
    }
}
