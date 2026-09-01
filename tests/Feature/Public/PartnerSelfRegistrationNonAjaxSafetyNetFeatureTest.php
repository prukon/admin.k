<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Partner;
use App\Models\User;
use App\Notifications\PartnerSelfRegisteredNotification;
use Illuminate\Support\Facades\Notification;

/**
 * P1: native POST /partner/register без X-Requested-With — 302 в кабинет и запись в БД,
 * либо 302 назад с errors по полям; не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PartnerSelfRegistrationNonAjaxSafetyNetFeatureTest extends PartnerSelfRegistrationTestCase
{
    public function test_native_post_creates_partner_logs_in_and_redirects_to_cabinet(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        Notification::fake();

        $payload = $this->validRegistrationPayload([
            'email' => 'native-ok@example.test',
        ]);

        $response = $this->from($this->registerUrl())->post($this->registerStoreUrl(), $payload);
        $this->assertNotServerError($response, 'native POST успех');
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect('/cabinet');

        $user = $this->assertCreatedPartnerAndAdmin(
            'native-ok@example.test',
            'Футбольная школа Тест',
            'Иван Админов'
        );
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, PartnerSelfRegisteredNotification::class);
    }

    public function test_native_empty_fields_redirect_with_errors_on_each_field_not_empty_200(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $partnersBefore = Partner::query()->count();

        $response = $this->from($this->registerUrl())->post($this->registerStoreUrl(), [
            'school_title' => '',
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
            'recaptcha_token' => '',
        ]);
        $this->assertNotServerError($response, 'native пустые поля');
        $this->assertNotEmptyOk($response, 'native пустые поля');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors([
            'school_title',
            'name',
            'email',
            'password',
            'recaptcha_token',
        ]);
        $this->assertSame('Укажите название школы.', session('errors')->first('school_title'));
        $this->assertSame('Укажите ваше имя.', session('errors')->first('name'));
        $this->assertSame('Укажите email.', session('errors')->first('email'));
        $this->assertSame('Укажите пароль.', session('errors')->first('password'));
        $this->assertSame(
            'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.',
            session('errors')->first('recaptcha_token')
        );
        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertGuest();
    }

    public function test_native_password_mismatch_puts_error_on_password_field(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $response = $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'password' => 'password123',
            'password_confirmation' => 'other-password',
        ]));
        $this->assertNotServerError($response, 'native пароли не совпадают');
        $this->assertNotEmptyOk($response, 'native пароли не совпадают');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors(['password']);
        $this->assertSame('Пароли не совпадают.', session('errors')->first('password'));
        $this->assertFalse(session('errors')->has('school_title'));
        $this->assertGuest();
    }

    public function test_native_duplicate_user_email_puts_error_on_email_and_does_not_create_partner(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        $this->user->forceFill(['email' => 'taken-user@example.test'])->save();
        $partnersBefore = Partner::query()->count();

        $response = $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'email' => 'taken-user@example.test',
        ]));
        $this->assertNotServerError($response, 'native занятый email пользователя');
        $this->assertNotEmptyOk($response, 'native занятый email пользователя');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors(['email']);
        $this->assertSame('Этот email уже используется.', session('errors')->first('email'));
        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertGuest();
    }

    public function test_native_duplicate_partner_email_puts_error_on_email(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        $this->partner->forceFill(['email' => 'taken-partner@example.test'])->save();
        $usersBefore = User::query()->count();

        $response = $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'email' => 'taken-partner@example.test',
        ]));
        $this->assertNotServerError($response, 'native занятый email партнёра');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors(['email']);
        $this->assertSame('Этот email уже используется.', session('errors')->first('email'));
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertGuest();
    }

    public function test_native_invalid_email_puts_error_on_email_not_name(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $response = $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'email' => 'not-an-email',
        ]));
        $this->assertNotServerError($response, 'native некорректный email');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors(['email']);
        $this->assertSame('Введите корректный email.', session('errors')->first('email'));
        $this->assertFalse(session('errors')->has('name'));
    }

    public function test_native_short_password_puts_error_on_password(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();

        $response = $this->from($this->registerUrl())->post($this->registerStoreUrl(), $this->validRegistrationPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));
        $this->assertNotServerError($response, 'native короткий пароль');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors(['password']);
        $this->assertSame('Пароль должен быть не короче 8 символов.', session('errors')->first('password'));
    }

    public function test_native_recaptcha_score_failure_redirects_back_with_token_error(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaLowScore();
        $partnersBefore = Partner::query()->count();

        $response = $this->from($this->registerUrl())->post(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'low-score@example.test'])
        );
        $this->assertNotServerError($response, 'native recaptcha score');
        $this->assertNotEmptyOk($response, 'native recaptcha score');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors(['recaptcha_token']);
        $this->assertSame('Проверка на спам не пройдена.', session('errors')->first('recaptcha_token'));
        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertGuest();
    }

    public function test_native_recaptcha_wrong_action_does_not_create_partner(): void
    {
        $this->actingAsGuest();
        $this->enablePartnerSelfRegistration();
        $this->fakeRecaptchaWrongAction();
        $partnersBefore = Partner::query()->count();

        $response = $this->from($this->registerUrl())->post(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'wrong-action@example.test'])
        );
        $this->assertNotServerError($response, 'native recaptcha action');
        $response->assertRedirect($this->registerUrl());
        $response->assertSessionHasErrors(['recaptcha_token']);
        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertGuest();
    }

    public function test_native_post_when_closed_is_403_and_creates_nothing(): void
    {
        $this->actingAsGuest();
        $this->disablePartnerSelfRegistration();
        $this->fakeRecaptchaSuccess();
        $partnersBefore = Partner::query()->count();

        $response = $this->from($this->registerUrl())->post(
            $this->registerStoreUrl(),
            $this->validRegistrationPayload(['email' => 'closed-native@example.test'])
        );
        $this->assertNotServerError($response, 'native POST закрыто');
        $this->assertNotEmptyOk($response, 'native POST закрыто');
        $response->assertForbidden();
        $this->assertSame($partnersBefore, Partner::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'closed-native@example.test']);
    }
}
