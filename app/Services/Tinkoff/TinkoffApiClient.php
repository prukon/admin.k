<?php

namespace App\Services\Tinkoff;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TinkoffApiClient
{
    public static function post(string $baseUrl, string $path, array $payload, array $cert = []): array
    {
        $req = Http::timeout(30)->retry(2, 500);

        if ($cert) {
            $req = $req->withOptions([
                'cert'    => $cert['cert'] ?? null,
                'ssl_key' => $cert['key'] ?? null,
                'verify'  => $cert['ca'] ?? true,
            ]);
        }

        $url = rtrim($baseUrl, '/') . $path;
        Log::channel('tinkoff')->info('[POST] '.$url, ['payload' => $payload]);

        $resp = $req->post($url, $payload);
        $json = $resp->json() ?? ['http_status' => $resp->status(), 'body' => $resp->body()];

        Log::channel('tinkoff')->info('[RESP] '.$url, ['json' => $json]);
        return $json;
    }
}
