<?php

namespace Tests\Feature;

use App\Mail\NewContactSubmission;
use App\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactSendTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Успешная отправка заявки с валидными данными и пройденной reCAPTCHA.
     */
    public function test_contact_send_creates_submission_when_data_and_recaptcha_are_valid(): void
    {
        Mail::fake();

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $payload = [
            'name'            => 'Иван',
            'email'           => 'ivan@example.com',
            'phone'           => '+7 999 123-45-67',
            'website'         => 'example.com',
            'message'         => 'Хочу подключить сервис',
            'recaptcha_token' => 'fake-token',
        ];

        $response = $this->postJson('/contact/send', $payload);

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Заявка отправлена!',
            ])
            ->assertJsonStructure(['id']);

        $this->assertDatabaseHas('contact_submissions', [
            'name'  => 'Иван',
            'phone' => '+7 999 123-45-67',
        ]);

        Mail::assertSent(NewContactSubmission::class, 1);
    } 

    /**
     * Ошибка валидации, когда не указан телефон (обязательное поле).
     */
    public function test_contact_send_fails_validation_when_phone_is_missing(): void
    {
        Mail::fake();

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.9,
            ], 200),
        ]);

        $payload = [
            'name'            => 'Иван',
            // 'phone' отсутствует
            'recaptcha_token' => 'fake-token',
        ];

        $response = $this->postJson('/contact/send', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseCount('contact_submissions', 0);
    }

    /**
     * reCAPTCHA не пройдена (низкий score / неуспех).
     */
    public function test_contact_send_fails_when_recaptcha_score_is_too_low(): void
    {
        Mail::fake();

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score'   => 0.1, // ниже порога
            ], 200),
        ]);

        $payload = [
            'name'            => 'Иван',
            'phone'           => '+7 999 123-45-67',
            'recaptcha_token' => 'fake-token',
        ];

        $response = $this->postJson('/contact/send', $payload);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Проверка на спам не пройдена.',
            ]);

        $this->assertDatabaseCount('contact_submissions', 0);
    }

    /**
     * Ошибка, если не передан recaptcha_token вообще.
     */
    public function test_contact_send_fails_when_recaptcha_token_is_missing(): void
    {
        Mail::fake();
        Http::fake(); // чтобы не улетело никуда, хотя не дойдёт до этого места

        $payload = [
            'name'  => 'Иван',
            'phone' => '+7 999 123-45-67',
            // 'recaptcha_token' отсутствует
        ];

        $response = $this->postJson('/contact/send', $payload);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.',
            ]);

        $this->assertDatabaseCount('contact_submissions', 0);
    }
}
