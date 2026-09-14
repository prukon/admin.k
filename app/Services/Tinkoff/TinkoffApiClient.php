<?php

namespace App\Services\Tinkoff;

use App\Support\OpsMonitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TinkoffApiClient
{
    public static function get(string $url, array $query = [], array $headers = [], array $cert = []): array
    {
        $req = self::pendingRequest($headers, $cert);

        Log::channel('tinkoff')->info('[GET] '.$url, ['query' => $query]);
        try {
            $resp = $req->get($url, $query);
        } catch (Throwable $e) {
            OpsMonitor::recordGatewayFail(OpsMonitor::GATEWAY_TINKOFF, $e->getMessage());
            throw $e;
        }

        return self::finishResponse($url, $resp);
    }

    public static function post(string $baseUrl, string $path, array $payload, array $cert = []): array
    {
        $req = self::pendingRequest([], $cert);

        $url = rtrim($baseUrl, '/') . $path;
        Log::channel('tinkoff')->info('[POST] '.$url, ['payload' => $payload]);

        try {
            $resp = $req->post($url, $payload);
        } catch (Throwable $e) {
            OpsMonitor::recordGatewayFail(OpsMonitor::GATEWAY_TINKOFF, $e->getMessage());
            throw $e;
        }

        return self::finishResponse($url, $resp);
    }

    /**
     * Retry только обрыв связи и 5xx. 4xx (в т.ч. 404 CloseSpDeal) возвращаем как JSON,
     * без RequestException: вызывающий код уже ждёт Success=false / http_status.
     */
    private static function pendingRequest(array $headers = [], array $cert = []): PendingRequest
    {
        $req = Http::timeout(30)->retry(
            2,
            500,
            static function ($exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }
                if ($exception instanceof RequestException) {
                    return (bool) $exception->response?->serverError();
                }

                return false;
            },
            throw: false
        );

        if ($cert) {
            $req = $req->withOptions([
                'cert'    => $cert['cert'] ?? null,
                'ssl_key' => $cert['key'] ?? null,
                'verify'  => $cert['ca'] ?? true,
            ]);
        }

        if ($headers !== []) {
            $req = $req->withHeaders($headers);
        }

        return $req;
    }

    private static function finishResponse(string $url, Response $resp): array
    {
        $json = $resp->json();
        if (! is_array($json)) {
            $json = ['body' => $resp->body()];
        }
        $json['http_status'] = $resp->status();
        if (! $resp->successful() && ! array_key_exists('Success', $json)) {
            $json['Success'] = false;
        }

        Log::channel('tinkoff')->info('[RESP] '.$url, ['json' => $json]);
        if ($resp->successful()) {
            OpsMonitor::recordGatewayOk(OpsMonitor::GATEWAY_TINKOFF);
        } else {
            OpsMonitor::recordGatewayFail(OpsMonitor::GATEWAY_TINKOFF, 'HTTP '.$resp->status());
        }

        return $json;
    }
}
