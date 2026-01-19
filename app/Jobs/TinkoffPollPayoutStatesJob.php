<?php

namespace App\Jobs;

use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;

class TinkoffPollPayoutStatesJob extends Job
{
    public function handle(TinkoffPayoutsService $svc): void
    {
        // Статусы, которые могут перейти в финальные при polling (GetState).
        $list = TinkoffPayout::whereIn('status', [
            'INITIATED',
            'NEW',
            'AUTHORIZING',
            'CHECKING',
            'CREDIT_CHECKING',
            'COMPLETING',
        ])->get();
        foreach ($list as $p) {
            $svc->pollState($p);
        }
    }
}
