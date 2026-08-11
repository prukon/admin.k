<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PaymentNotifications\PaymentNotificationDispatchService;
use App\Services\PaymentNotifications\PaymentNotificationTriggerResolver;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ежедневный запуск правил уведомлений об оплате (10:00 Europe/Moscow).
 */
final class DispatchPaymentNotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string|null  $dateYmd  Для тестов/ручного запуска; null → сегодня по Europe/Moscow.
     */
    public function __construct(
        public ?string $dateYmd = null,
    ) {
    }

    public function handle(PaymentNotificationDispatchService $service): void
    {
        $date = $this->dateYmd !== null
            ? CarbonImmutable::parse($this->dateYmd, PaymentNotificationTriggerResolver::TIMEZONE)->startOfDay()
            : CarbonImmutable::now(PaymentNotificationTriggerResolver::TIMEZONE)->startOfDay();

        $stats = $service->dispatchForDate($date);

        Log::info('[DispatchPaymentNotificationsJob] done', [
            'date' => $date->toDateString(),
            'stats' => $stats,
        ]);
    }
}
