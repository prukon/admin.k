<?php

namespace App\Jobs;

use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TinkoffRunScheduledPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TinkoffPayoutsService $svc): void
    {
        $toRun = TinkoffPayout::whereNull('tinkoff_payout_payment_id')
            ->whereNotNull('when_to_run')
            ->where('when_to_run', '<=', now())
            ->orderBy('when_to_run')
            ->limit(100)
            ->get();

        foreach ($toRun as $p) {
            $svc->runPayout($p);
        }
    }
}
