<?php

namespace App\Console;

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

        // T-Bank payouts:
        // - запускаем отложенные выплаты
        // - часто добиваем статусы, т.к. e2c не присылает webhook-уведомления
        $schedule->job(new \App\Jobs\TinkoffRunScheduledPayoutsJob)
            ->timezone('Europe/Riga')
            ->everyTenMinutes();

        $schedule->job(new \App\Jobs\TinkoffPollPayoutStatesJob)
            ->timezone('Europe/Riga')
            ->everyTenMinutes();


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
