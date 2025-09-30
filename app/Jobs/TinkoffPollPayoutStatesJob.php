<?php

namespace App\Jobs;

use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;

class TinkoffPollPayoutStatesJob extends Job
{
    public function handle(TinkoffPayoutsService $svc): void
    {
        $list = TinkoffPayout::whereIn('status', ['INITIATED','CREDIT_CHECKING'])->get();
        foreach ($list as $p) {
            $svc->pollState($p);
        }
    }
}
