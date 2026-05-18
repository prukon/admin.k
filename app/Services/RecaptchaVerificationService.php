<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecaptchaVerificationService
{
    /**
     * @return array{ok: bool, message: string, status: int}
     */
    public function verifyRequest(Request $request, string $tokenField = 'recaptcha_token'): array
    {
        $token = $request->input($tokenField);

        if (!$token) {
            return [
                'ok'      => false,
                'message' => 'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.',
                'status'  => 422,
            ];
        }

        try {
            $response = Http::asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret'   => config('services.recaptcha.secret'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]
            );

            $result = $response->json();
            $minScore = (float) config('services.recaptcha.min_score', 0.5);

            if (
                empty($result['success']) ||
                ($result['score'] ?? 0) < $minScore
            ) {
                return [
                    'ok'      => false,
                    'message' => 'Проверка на спам не пройдена.',
                    'status'  => 422,
                ];
            }
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok'      => false,
                'message' => 'Ошибка проверки защиты от спама. Попробуйте позже.',
                'status'  => 500,
            ];
        }

        return [
            'ok'      => true,
            'message' => '',
            'status'  => 200,
        ];
    }
}
