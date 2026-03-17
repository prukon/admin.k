<?php

namespace App\Jobs;

use App\Models\FiscalReceipt;
use App\Services\CloudKassir\CloudKassirReceiptBuilder;
use App\Services\CloudKassir\CloudKassirService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendCloudKassirReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(public int $fiscalReceiptId)
    {
    }

    public function handle(
        CloudKassirService $cloudKassirService,
        CloudKassirReceiptBuilder $builder
    ): void {
        // Отладка логов: пишем в файл напрямую — если появится, воркер пишет в storage/logs и путь верный
        $debugPath = storage_path('logs/cloudkassir_debug.txt');
        @file_put_contents($debugPath, date('Y-m-d H:i:s') . " job start receipt_id={$this->fiscalReceiptId} path=" . $debugPath . "\n", FILE_APPEND);

        /** @var FiscalReceipt|null $receipt */
        $receipt = FiscalReceipt::query()
            ->with(['partner', 'payable', 'paymentIntent'])
            ->find($this->fiscalReceiptId);

        if (!$receipt) {
            return;
        }

        // Если уже успешно обработан — ничего не делаем.
        if ($receipt->status === FiscalReceipt::STATUS_PROCESSED) {
            return;
        }

        // Если уже был отправлен и есть внешний id, повторно не шлём без отдельного сценария.
        if ($receipt->external_id && $receipt->status === FiscalReceipt::STATUS_QUEUED) {
            return;
        }

        $payload = $builder->build($receipt);
        $requestId = $receipt->idempotency_key ?: ('fiscal_receipt_' . $receipt->id);

        $result = $cloudKassirService->createReceipt($payload, $requestId);
        $body = $result['body'] ?? [];

        DB::transaction(function () use ($receipt, $payload, $result, $body) {
            $fresh = FiscalReceipt::lockForUpdate()->find($receipt->id);
            if (!$fresh) {
                return;
            }

            $fresh->request_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $fresh->response_payload = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $success = (bool) ($body['Success'] ?? false);
            $message = (string) ($body['Message'] ?? '');
            $model = is_array($body['Model'] ?? null) ? $body['Model'] : [];

            if ($success && $message === 'Queued') {
                $fresh->status = FiscalReceipt::STATUS_QUEUED;
                $fresh->queued_at = now();
                $fresh->external_id = isset($model['Id']) ? (string) $model['Id'] : $fresh->external_id;
                $fresh->receipt_url = isset($model['ReceiptLocalUrl']) ? (string) $model['ReceiptLocalUrl'] : $fresh->receipt_url;
                $fresh->error_code = isset($model['ErrorCode']) ? (int) $model['ErrorCode'] : null;
                $fresh->warning_message = isset($body['Warning']) ? (string) $body['Warning'] : null;
                $fresh->error_message = null;
                $fresh->failed_at = null;
            } else {
                $fresh->status = FiscalReceipt::STATUS_ERROR;
                $fresh->failed_at = now();
                $fresh->error_code = isset($model['ErrorCode']) ? (int) $model['ErrorCode'] : null;
                $fresh->error_message = $message !== '' ? $message : 'CloudKassir request failed';
            }

            $fresh->save();
        });
    }

    public function failed(Throwable $e): void
    {
        $receipt = FiscalReceipt::find($this->fiscalReceiptId);
        if (!$receipt) {
            return;
        }

        $receipt->status = FiscalReceipt::STATUS_ERROR;
        $receipt->failed_at = now();
        $receipt->error_message = $e->getMessage();
        $receipt->save();
    }
}