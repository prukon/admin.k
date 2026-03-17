<?php

namespace App\Services\BlogAi;

use App\Services\BlogAi\Exceptions\OpenAiRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiClient
{
    private function headers(): array
    {
        $apiKey = (string) (config('services.openai.api_key') ?: env('OPENAI_API_KEY'));
        if ($apiKey === '') {
            throw new OpenAiRequestException('Не настроен ключ OpenAI (OPENAI_API_KEY).');
        }

        $org = (string) ((config('services.openai.organization') ?? '') ?: env('OPENAI_ORG_ID', ''));

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($org !== '') {
            $headers['OpenAI-Organization'] = $org;
        }
        return $headers;
    }

    public function responses(array $payload): array
    {
        $baseUrl = rtrim((string) (config('services.openai.base_url') ?: env('OPENAI_BASE_URL', 'https://api.openai.com/v1')), '/');
        $headers = $this->headers();

        $resp = null;
        $lastConn = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $resp = Http::withHeaders($headers)
                    ->timeout(80)
                    ->post($baseUrl . '/responses', $payload);
                $lastConn = null;
                break;
            } catch (ConnectionException $e) {
                $lastConn = $e;
                // небольшая экспоненциальная пауза: 250мс, 500мс
                if ($attempt < 3) {
                    usleep(250_000 * $attempt);
                }
            }
        }
        if ($lastConn || !$resp) {
            throw new OpenAiRequestException('Не удалось подключиться к OpenAI. Попробуйте позже.');
        }

        if ($resp->failed()) {
            $json = $resp->json();
            $errMsg = data_get($json, 'error.message') ?: $resp->body();
            $errType = data_get($json, 'error.type');

            if ($resp->status() === 401) {
                throw new OpenAiRequestException('Неверный ключ OpenAI или нет доступа к API.', 401, $errType);
            }
            if ($resp->status() === 429) {
                throw new OpenAiRequestException('Превышены лимиты OpenAI. Попробуйте позже.', 429, $errType);
            }

            throw new OpenAiRequestException('Ошибка OpenAI: ' . (string) $errMsg, $resp->status(), $errType);
        }

        return (array) $resp->json();
    }

    public function imagesGenerations(array $payload): array
    {
        $baseUrl = rtrim((string) (config('services.openai.base_url') ?: env('OPENAI_BASE_URL', 'https://api.openai.com/v1')), '/');
        $headers = $this->headers();

        $resp = null;
        $lastConn = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $resp = Http::withHeaders($headers)
                    ->timeout(80)
                    ->post($baseUrl . '/images/generations', $payload);
                $lastConn = null;
                break;
            } catch (ConnectionException $e) {
                $lastConn = $e;
                if ($attempt < 3) {
                    usleep(250_000 * $attempt);
                }
            }
        }
        if ($lastConn || !$resp) {
            throw new OpenAiRequestException('Не удалось подключиться к OpenAI (изображения). Попробуйте позже.');
        }

        if ($resp->failed()) {
            $json = $resp->json();
            $errMsg = data_get($json, 'error.message') ?: $resp->body();
            $errType = data_get($json, 'error.type');

            if ($resp->status() === 401) {
                throw new OpenAiRequestException('Неверный ключ OpenAI или нет доступа к API (изображения).', 401, $errType);
            }
            if ($resp->status() === 429) {
                throw new OpenAiRequestException('Превышены лимиты OpenAI (изображения). Попробуйте позже.', 429, $errType);
            }

            throw new OpenAiRequestException('Ошибка OpenAI (изображения): ' . (string) $errMsg, $resp->status(), $errType);
        }

        return (array) $resp->json();
    }
}

