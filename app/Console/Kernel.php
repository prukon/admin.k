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
