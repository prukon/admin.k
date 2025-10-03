<?php

namespace App\Services\Tinkoff;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;

class SmRegisterClient
{
    /** Базовая заготовка HTTP-клиента с mTLS */
    protected function base(): PendingRequest
    {
        $options = [
            'timeout'     => 20,
            'http_errors' => false,
            'verify'      => true,
            'cert'        => env('TCS_MTLS_CERT'),
            'ssl_key'     => env('TCS_MTLS_KEY'),
        ];

        if (!is_string($options['cert']) || !file_exists($options['cert'])) {
            throw new \RuntimeException('Файл сертификата не найден: '.var_export($options['cert'], true));
        }
        if (!is_string($options['ssl_key']) || !file_exists($options['ssl_key'])) {
            throw new \RuntimeException('Файл приватного ключа не найден: '.var_export($options['ssl_key'], true));
        }

        return Http::withOptions($options)
            ->baseUrl('https://acqapi.tinkoff.ru')
            ->acceptJson();
    }

    /** Получение и кэш OAuth-токена */
    protected function getAccessToken(): string
    {
        return Cache::remember('tcs_sm_oauth_token', 55 * 60, function () {
            $resp = $this->base()
                ->asForm()
                ->withHeaders(['Authorization' => 'Basic cGFydG5lcjpwYXJ0bmVy']) // partner:partner
                ->post('/oauth/token', [
                    'grant_type' => 'password',
                    'username'   => env('TINKOFF_OAUTH_LOGIN'),
                    'password'   => env('TINKOFF_OAUTH_PASSWORD'),
                ]);

            if (!$resp->ok()) {
                throw new \RuntimeException('OAuth error: ' . $resp->status() . ' ' . $resp->body());
            }
            $token = (string) data_get($resp->json(), 'access_token');
            if (!$token) {
                throw new \RuntimeException('OAuth empty token: ' . $resp->body());
            }
            return $token;
        });
    }

    /** POST /sm-register/register — регистрация точки (как было) */
    public function register(array $payload): array
    {
        Log::channel('tinkoff')->info('[sm-register][register][payload] '.json_encode($payload, JSON_UNESCAPED_UNICODE));

        $token = $this->getAccessToken();

        $r = $this->base()
            ->withToken($token)
            ->post('/sm-register/register', $payload);

        Log::channel('tinkoff')->info('[sm-register][register][resp] status='.$r->status().' body='.$r->body());

        if ($r->status() === 422) {
            throw new \InvalidArgumentException('Validation: ' . $r->body());
        }
        if (!$r->successful()) {
            throw new \RuntimeException('sm-register/register failed: ' . $r->status() . ' ' . $r->body());
        }
        return $r->json();
    }

    /** PATCH /sm-register/register/{shopCode} — обновление реквизитов */
    public function patch(string $shopCode, array $payload): array
    {
        $shopCode = trim($shopCode);
        $token    = $this->getAccessToken();

        Log::channel('tinkoff')->info("[sm-register][patch][request] shopCode={$shopCode} payload=".json_encode($payload, JSON_UNESCAPED_UNICODE));

        $r = $this->base()
            ->withToken($token)
            ->asJson() // Content-Type: application/json — требование доки для PATCH
            ->patch("/sm-register/register/{$shopCode}", $payload);

        Log::channel('tinkoff')->info('[sm-register][patch][resp] status='.$r->status().' body='.$r->body());

        if (!$r->successful()) {
            throw new \RuntimeException('sm-register PATCH failed: ' . $r->status() . ' ' . $r->body());
        }
        return $r->json();
    }

    /** GET /sm-register/register/shop/{shopCode} — получение информации/статуса точки */
    public function getStatus(string $shopCode): array
    {
        $shopCode = trim($shopCode);
        $token    = $this->getAccessToken();

        Log::channel('tinkoff')->info("[sm-register][status][request] shopCode={$shopCode}");

        $r = $this->base()
            ->withToken($token)
            ->get("/sm-register/register/shop/{$shopCode}");

        Log::channel('tinkoff')->info('[sm-register][status][resp] status='.$r->status().' body='.$r->body());

        if (!$r->successful()) {
            throw new \RuntimeException('sm-register status failed: ' . $r->status() . ' ' . $r->body());
        }
        return $r->json();
    }
}
