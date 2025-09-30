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


        // Europe/Riga 03:30 — добиваем статусы и выполняем отложенные
        $schedule->job(new \App\Jobs\TinkoffRunScheduledPayoutsJob)
            ->timezone('Europe/Riga')->dailyAt('03:30');

        $schedule->job(new \App\Jobs\TinkoffPollPayoutStatesJob)
            ->timezone('Europe/Riga')->dailyAt('03:40');


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
