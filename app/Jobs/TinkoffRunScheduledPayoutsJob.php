<?php

namespace App\Jobs;

use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;

class TinkoffRunScheduledPayoutsJob extends Job
{
    public function handle(TinkoffPayoutsService $svc): void
    {
        $toRun = TinkoffPayout::whereNull('tinkoff_payout_payment_id')
            ->whereNotNull('when_to_run')
            ->where('when_to_run', '<=', now())->get();

        foreach ($toRun as $p) {
            $svc->runPayout($p);
        }
    }
}
