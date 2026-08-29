<?php

namespace App\Services\CloudKassir;

use App\Support\OpsMonitor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CloudKassirService
{
    protected string $baseUrl;
    protected string $publicId;
    protected string $apiSecret;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.cloudkassir.base_url', 'https://api.cloudpayments.ru'), '/');
        $this->publicId = (string) config('services.cloudkassir.public_id', '');
        $this->apiSecret = (string) config('services.cloudkassir.api_secret', '');
        $this->timeout = (int) config('services.cloudkassir.timeout', 30);

        if ($this->publicId === '' || $this->apiSecret === '') {
            throw new RuntimeException('CloudKassir credentials are not configured.');
        }
    }

    public function createReceipt(array $payload, ?string $requestId = null): array
    {
        return $this->post('/kkt/receipt', $payload, $requestId);
    }

    public function getReceiptStatus(string $externalId, ?string $requestId = null): array
    {
        return $this->post('/kkt/receipt/status/get', [
            'Id' => $externalId,
        ], $requestId);
    }

    public function getReceipt(string $externalId, ?string $requestId = null): array
    {
        return $this->post('/kkt/receipt/get', [
            'Id' => $externalId,
        ], $requestId);
    }

    protected function post(string $path, array $payload, ?string $requestId = null): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($requestId) {
            $headers['X-Request-ID'] = $requestId;
        }

        // Лог тела запроса и ответа (для передачи менеджеру CloudKassir при необходимости)
        // Дублируем в default-канал: если cloudkassir.log недоступен по правам, данные будут в laravel.log
        $requestLog = ['path' => $path, 'request_body' => $payload];
        Log::channel('cloudkassir')->info('CloudKassir request', $requestLog);
        Log::info('CloudKassir request', $requestLog);

        /** @var Response $response */
        try {
            $response = Http::withBasicAuth($this->publicId, $this->apiSecret)
                ->withHeaders($headers)
                ->timeout($this->timeout)
                ->asJson()
                ->post($this->baseUrl . $path, $payload);
        } catch (Throwable $e) {
            OpsMonitor::recordGatewayFail(OpsMonitor::GATEWAY_CLOUDKASSIR, $e->getMessage());
            throw $e;
        }

        $json = $response->json();

        // Лог тела ответа (для передачи менеджеру CloudKassir при необходимости)
        $responseLog = [
            'path' => $path,
            'http_status' => $response->status(),
            'response_body' => is_array($json) ? $json : ['_raw' => $response->body()],
        ];
        Log::channel('cloudkassir')->info('CloudKassir response', $responseLog);
        Log::info('CloudKassir response', $responseLog);

        if (!is_array($json)) {
            OpsMonitor::recordGatewayFail(
                OpsMonitor::GATEWAY_CLOUDKASSIR,
                'CloudKassir returned non-JSON response. HTTP '.$response->status()
            );
            throw new RuntimeException(
                'CloudKassir returned non-JSON response. HTTP ' . $response->status()
            );
        }

        if ($response->successful()) {
            OpsMonitor::recordGatewayOk(OpsMonitor::GATEWAY_CLOUDKASSIR);
        } else {
            OpsMonitor::recordGatewayFail(OpsMonitor::GATEWAY_CLOUDKASSIR, 'HTTP '.$response->status());
        }

        return [
            'http_status' => $response->status(),
            'ok' => $response->successful(),
            'body' => $json,
        ];
    }
}