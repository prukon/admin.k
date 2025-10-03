<?php

namespace App\Services\Tinkoff;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;

class SmRegisterClient
{

    protected function base(): \Illuminate\Http\Client\PendingRequest
    {
        $options = [
            'timeout'     => 20,
            'http_errors' => false,
            'verify'      => true,
            'cert'        => env('TCS_MTLS_CERT'), // СТРОКА (путь к .pem серта)
            'ssl_key'     => env('TCS_MTLS_KEY'),  // СТРОКА (путь к .key)
            // Временный дебаг (по желанию):
            // 'debug'     => fopen(storage_path('logs/guzzle-debug.log'), 'a'),
        ];

        if (!is_string($options['cert']) || !file_exists($options['cert'])) {
            throw new \RuntimeException('Файл сертификата не найден: '.var_export($options['cert'], true));
        }
        if (!is_string($options['ssl_key']) || !file_exists($options['ssl_key'])) {
            throw new \RuntimeException('Файл приватного ключа не найден: '.var_export($options['ssl_key'], true));
        }

        return \Illuminate\Support\Facades\Http::withOptions($options)
            ->baseUrl('https://acqapi.tinkoff.ru')
            ->acceptJson();
    }


    protected function getAccessToken(): string
    {
        return Cache::remember('tcs_sm_oauth_token', 55 * 60, function () {
            $resp = $this->base()
                ->asForm()
                ->withHeaders(['Authorization' => 'Basic cGFydG5lcjpwYXJ0bmVy'])// partner:partner
                ->post('/oauth/token', [
                    'grant_type' => 'password',
                    'username' => env('TINKOFF_OAUTH_LOGIN'),
                    'password' => env('TINKOFF_OAUTH_PASSWORD'),
                ]);

            if (!$resp->ok()) {
                throw new \RuntimeException('OAuth error: ' . $resp->status() . ' ' . $resp->body());
            }
            $token = (string)data_get($resp->json(), 'access_token');
            if (!$token) {
                throw new \RuntimeException('OAuth empty token: ' . $resp->body());
            }
            return $token;
        });
    }

    public function register(array $payload): array
    {

        \Log::channel('tinkoff')->info('[sm-register][request] '.json_encode($payload, JSON_UNESCAPED_UNICODE));

        $token = $this->getAccessToken();

        $r = $this->base()
            ->withToken($token)
            ->post('/sm-register/register', $payload);

        if ($r->status() === 422) {
            // ошибки валидации от банка
            throw new \InvalidArgumentException('Validation: ' . $r->body());
        }
        if (!$r->successful()) {
            throw new \RuntimeException('sm-register/register failed: ' . $r->status() . ' ' . $r->body());
        }
        return $r->json();
    }

    public function patch(string $partnerId, array $payload): array
    {
        $token = $this->getAccessToken();

        $r = $this->base()
            ->withToken($token)
            ->patch("/sm-register/$partnerId", $payload);

        if (!$r->successful()) {
            throw new \RuntimeException('sm-register PATCH failed: ' . $r->status() . ' ' . $r->body());
        }
        return $r->json();
    }

    public function getStatus(string $partnerId): array
    {
        $token = $this->getAccessToken();

        $r = $this->base()
            ->withToken($token)
            ->get("/sm-register/$partnerId/status");

        if (!$r->successful()) {
            throw new \RuntimeException('sm-register status failed: ' . $r->status() . ' ' . $r->body());
        }
        return $r->json();
    }
}