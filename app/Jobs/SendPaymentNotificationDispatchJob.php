<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PaymentNotificationDispatch;
use App\Services\PaymentNotifications\PaymentNotificationSendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendPaymentNotificationDispatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $dispatchId,
    ) {
    }

    public function handle(PaymentNotificationSendService $service): void
    {
        $dispatch = PaymentNotificationDispatch::query()->find($this->dispatchId);
        if ($dispatch === null) {
            return;
        }

        $service->sendDispatch($dispatch);
    }

    public function failed(?Throwable $e): void
    {
        $dispatch = PaymentNotificationDispatch::query()->find($this->dispatchId);
        if ($dispatch === null) {
            return;
        }

        if ($dispatch->status === PaymentNotificationDispatch::STATUS_SENT) {
            return;
        }

        $dispatch->update([
            'status' => PaymentNotificationDispatch::STATUS_FAILED,
            'error_message' => mb_substr($e?->getMessage() ?? 'unknown', 0, 2000),
        ]);

        Log::warning('[SendPaymentNotificationDispatchJob] failed', [
            'dispatch_id' => $this->dispatchId,
            'error' => $e?->getMessage(),
        ]);
    }
}
