<?php

namespace App\Services\Signatures\Providers;

use App\Models\Contract;
use App\Models\ContractSignRequest;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PodpislonProvider implements SignatureProvider
{
protected string $baseUrl;
protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.podpislon.base_url'), '/');
        $this->apiKey  = (string) config('services.podpislon.key');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept'        => 'application/json',
        ];
    }

    public function send(Contract $contract, ContractSignRequest $request): array
    {
        // 1) Если документа ещё нет у провайдера — создаём
        if (!$contract->provider_doc_id) {
            $filePath = Storage::path($contract->source_pdf_path);

            $resp = Http::withHeaders($this->headers())
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($this->baseUrl.'/documents', [
                    // TODO: заменить на реальные поля API Подпислона
                    'title'      => 'Договор #'.$contract->id,
                    'sha256'     => $contract->source_sha256,
                ]);

            if (!$resp->ok()) {
                throw new \RuntimeException('Ошибка создания документа у провайдера: '.$resp->body());
            }

            $data = $resp->json();
            $contract->provider_doc_id = $data['document_id'] ?? $data['id'] ?? null;
            $contract->save();
        }

        // 2) Отправляем на подпись (SMS/OTP)
        $resp2 = Http::withHeaders($this->headers())->post($this->baseUrl.'/documents/'.$contract->provider_doc_id.'/send', [
            // TODO: заменить на реальные поля API Подпислона
            'signer' => [
                'name'  => $request->signer_name,
                'phone' => $request->signer_phone,
            ],
            'ttl_hours' => $request->ttl_hours ?? 72,
        ]);

        if (!$resp2->ok()) {
            throw new \RuntimeException('Ошибка отправки на подпись: '.$resp2->body());
        }

        $data2 = $resp2->json();
        $providerRequestId = $data2['request_id'] ?? Str::uuid()->toString();

        $request->provider_request_id = $providerRequestId;
        $request->status = 'sent';
        $request->save();

        return [
            'provider_doc_id'     => $contract->provider_doc_id,
            'provider_request_id' => $providerRequestId,
            'raw'                 => $data2,
        ];
    }

    public function revoke(Contract $contract): void
    {
        if (!$contract->provider_doc_id) {
            return;
        }
        $resp = Http::withHeaders($this->headers())
            ->post($this->baseUrl.'/documents/'.$contract->provider_doc_id.'/revoke');

        if (!$resp->ok()) {
            throw new \RuntimeException('Ошибка отзыва подписи: '.$resp->body());
        }
    }

    public function getStatus(Contract $contract): array
    {
        if (!$contract->provider_doc_id) {
            return ['status' => 'draft'];
        }

        $resp = Http::withHeaders($this->headers())
            ->get($this->baseUrl.'/documents/'.$contract->provider_doc_id.'/status');

        if (!$resp->ok()) {
            throw new \RuntimeException('Ошибка запроса статуса: '.$resp->body());
        }

        return $resp->json(); // ожидаем например: ['status' => 'sent|opened|signed|expired|failed']
    }

    public function downloadSigned(Contract $contract): array
    {
        if (!$contract->provider_doc_id) {
            throw new \RuntimeException('Нет provider_doc_id');
        }

        $resp = Http::withHeaders($this->headers())
            ->get($this->baseUrl.'/documents/'.$contract->provider_doc_id.'/download-signed');

        if (!$resp->ok()) {
            throw new \RuntimeException('Ошибка скачивания подписанного: '.$resp->body());
        }

        // Предположим, что провайдер отдаёт бинарный PDF.
        $filename = 'contract-'.$contract->id.'-signed.pdf';
        return ['filename' => $filename, 'content' => $resp->body()];
    }
}
