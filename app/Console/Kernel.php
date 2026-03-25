<?php

namespace App\Console;

use App\Support\SchedulerHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


//use App\Models\Payout;
//use App\Models\Deal;
//use App\Services\Tinkoff\TinkoffA2cService;
//use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // Метка для админки: cron вызывает schedule:run → это событие должно срабатывать каждую минуту.
        $schedule->call([SchedulerHeartbeat::class, 'touch'])->everyMinute();

        // T-Bank payouts: интервал из БД (settings) или config
        $payoutIntervalMinutes = \App\Models\Setting::getTinkoffPayoutScheduledIntervalMinutes();
        $payoutCron = '*/' . max(1, (int) $payoutIntervalMinutes) . ' * * * *';

        $schedule->job(new \App\Jobs\TinkoffRunScheduledPayoutsJob)
            ->timezone('Europe/Riga')
            ->cron($payoutCron);

        $schedule->job(new \App\Jobs\TinkoffPollPayoutStatesJob)
            ->timezone('Europe/Riga')
            ->cron($payoutCron);


    }


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
