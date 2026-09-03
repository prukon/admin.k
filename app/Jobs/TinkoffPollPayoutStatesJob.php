<?php

namespace App\Jobs;

use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TinkoffPollPayoutStatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TinkoffPayoutsService $svc): void
    {
        // Статусы, которые могут перейти в финальные при polling (GetState).
        // Без банковского PaymentId GetState даёт ErrorCode 201 — такие строки шлёт
        // TinkoffRunScheduledPayoutsJob, не эта джоба.
        $list = TinkoffPayout::whereIn('status', [
            'INITIATED',
            'NEW',
            'AUTHORIZING',
            'CHECKING',
            'CREDIT_CHECKING',
            'COMPLETING',
        ])
            ->whereNotNull('tinkoff_payout_payment_id')
            ->where('tinkoff_payout_payment_id', '!=', '')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        foreach ($list as $p) {
            $svc->pollState($p);
        }
    }
}
