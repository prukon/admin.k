<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DispatchPaymentNotificationsJob;
use App\Services\PaymentNotifications\PaymentNotificationDispatchService;
use App\Services\PaymentNotifications\PaymentNotificationTriggerResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class DispatchPaymentNotificationsCommand extends Command
{
    protected $signature = 'payment-notifications:dispatch
                            {--date= : Дата срабатывания YYYY-MM-DD (Europe/Moscow), по умолчанию сегодня}
                            {--sync : Выполнить синхронно без постановки корневого job в очередь}';

    protected $description = 'Запуск правил email-уведомлений об оплате абонементов';

    public function handle(PaymentNotificationDispatchService $service): int
    {
        $dateOpt = $this->option('date');
        $date = is_string($dateOpt) && $dateOpt !== ''
            ? CarbonImmutable::parse($dateOpt, PaymentNotificationTriggerResolver::TIMEZONE)->startOfDay()
            : CarbonImmutable::now(PaymentNotificationTriggerResolver::TIMEZONE)->startOfDay();

        if ($this->option('sync')) {
            $stats = $service->dispatchForDate($date);
            $this->info('Синхронный прогон за '.$date->toDateString());
            $this->line(json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');

            return self::SUCCESS;
        }

        DispatchPaymentNotificationsJob::dispatch($date->toDateString());
        $this->info('Job поставлен в очередь на дату '.$date->toDateString());

        return self::SUCCESS;
    }
}
