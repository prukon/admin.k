<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleRecaptchaVerifier
{
    /**
     * @param  string|null  $token  recaptcha_token с фронта
     * @param  string|null  $expectedAction  если задано, сверяется с ответом Google
     *
     * @throws ValidationException
     */
    public function verifyOrFail(?string $token, string $remoteIp, ?string $expectedAction = null): void
    {
        if (!$token) {
            throw ValidationException::withMessages([
                'recaptcha_token' => ['Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.'],
            ]);
        }

        try {
            $response = Http::asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret'   => config('services.recaptcha.secret'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]
            );

            $result = $response->json();
            $minScore = (float) config('services.recaptcha.min_score', 0.5);

            if (
                empty($result['success'])
                || ($result['score'] ?? 0) < $minScore
            ) {
                throw ValidationException::withMessages([
                    'recaptcha_token' => ['Проверка на спам не пройдена.'],
                ]);
            }

            if ($expectedAction !== null && ($result['action'] ?? null) !== $expectedAction) {
                throw ValidationException::withMessages([
                    'recaptcha_token' => ['Проверка на спам не пройдена.'],
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'recaptcha_token' => ['Ошибка проверки защиты от спама. Попробуйте позже.'],
            ]);
        }
    }
}
