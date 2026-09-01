<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Partner;
use App\Notifications\PartnerSelfRegisteredNotification;
use Illuminate\Support\Facades\Notification;

/**
 * P1: JSON / X-Requested-With контракт POST /partner/register —
 * 422 по полям при ошибке; успех не пустой 200; закрытый флаг — 403.
 *
 * Форма нативная (не AJAX-submit): успешный JSON всё равно 302 в кабинет.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PartnerSelfRegistrationAjaxContractFeatureTest extends PartnerSelfRegistrationTestCase
{
    public function test_ajax_empty_fields_return_422_errors_on_each_field(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $response = $this->from($this->registerUrl())->postJson($this->registerStoreUrl(), [
            'school_title' => '',
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
            'recaptcha_token' => '',
        ], $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX пустые поля');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['school_title', 'name', 'email', 'password', 'recaptcha_token'])
            ->assertJsonPath('errors.school_title.0', 'Укажите название школы.')
            ->assertJsonPath('errors.name.0', 'Укажите ваше имя.')
            ->assertJsonPath('errors.email.0', 'Укажите email.')
            ->assertJsonPath('errors.password.0', 'Укажите пароль.')
            ->assertJsonPath('errors.recaptcha_token.0', 'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.');
        $this->assertIsArray($response->json('errors'));
        $this->assertArrayNotHasKey('password_confirmation', $response->json('errors') ?? []);
        $this->assertGuest();
    }

    public function test_ajax_password_mismatch_returns_password_error_not_email_error(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $response = $this->from($this->registerUrl())->postJson(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload([
                'password' => 'password123',
                'password_confirmation' => 'mismatch-password',
            ]),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'AJAX пароли не совпадают');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Пароли не совпадают.');
        $this->assertArrayNotHasKey('email', $response->json('errors') ?? []);
        $this->assertArrayNotHasKey('school_title', $response->json('errors') ?? []);
    }

    public function test_ajax_duplicate_email_returns_422_on_email_only(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->user->forceFill(['email' => 'ajax-taken@example.test'])->save();

        $response = $this->from($this->registerUrl())->postJson(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'ajax-taken@example.test']),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'AJAX занятый email');
        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Этот email уже используется.');
        $this->assertArrayNotHasKey('password', $response->json('errors') ?? []);
    }

    public function test_ajax_successful_registration_is_not_empty_200(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        Notification::fake();

        $response = $this->from($this->registerUrl())->postJson(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'ajax-ok@example.test']),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'AJAX успешная регистрация');
        $this->assertNotSame(200, $response->getStatusCode(), 'AJAX успех не пустой 200');
        $this->assertTrue(
            $response->isRedirect(),
            'AJAX успех: 302 в кабинет (нативная форма), получено '.$response->getStatusCode()
        );
        $response->assertRedirect('/cabinet');
        $user = $this->assertCreatedPartnerAndAdmin(
            'ajax-ok@example.test',
            'Футбольная школа Тест',
            'Иван Админов'
        );
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, PartnerSelfRegisteredNotification::class);
    }

    public function test_ajax_already_authenticated_post_is_redirect_not_500(): void
    {
        $this->asAdmin();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        $partnersBefore = Partner::query()->count();

        $response = $this->actingAs($this->user)
            ->postJson($this->registerStoreUrl(), $this->validRegistrationPayload(), $this->ajaxHeaders());

        $this->assertNotServerError($response, 'AJAX POST уже залогинен');
        $this->assertNotEmptyOk($response, 'AJAX POST уже залогинен');
        $response->assertRedirect('/cabinet');
        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_ajax_closed_flag_returns_403_json_not_empty_200(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();

        $response = $this->from($this->registerUrl())->postJson(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'ajax-closed@example.test']),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'AJAX закрыто');
        $this->assertNotEmptyOk($response, 'AJAX закрыто');
        $response->assertForbidden();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertDatabaseMissing('users', ['email' => 'ajax-closed@example.test']);
        $this->assertGuest();
    }

    public function test_ajax_recaptcha_failure_returns_422_on_recaptcha_token(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaLowScore();

        $response = $this->from($this->registerUrl())->postJson(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'ajax-recaptcha@example.test']),
            $this->ajaxHeaders()
        );

        $this->assertNotServerError($response, 'AJAX recaptcha');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recaptcha_token'])
            ->assertJsonPath('errors.recaptcha_token.0', 'Проверка на спам не пройдена.');
        $this->assertDatabaseMissing('users', ['email' => 'ajax-recaptcha@example.test']);
    }
}
