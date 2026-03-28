<?php

namespace App\Jobs;

use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class TinkoffRunScheduledPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TinkoffPayoutsService $svc): void
    {
        $toRun = TinkoffPayout::whereNull('tinkoff_payout_payment_id')
            ->whereNotNull('when_to_run')
            ->where('when_to_run', '<=', now())
            ->where('status', 'INITIATED')
            ->whereNull('completed_at')
            ->orderBy('when_to_run')
            ->limit(100)
            ->pluck('id');

        foreach ($toRun as $payoutId) {
            DB::transaction(function () use ($svc, $payoutId) {
                $payout = TinkoffPayout::query()
                    ->whereKey((int) $payoutId)
                    ->lockForUpdate()
                    ->first();
                if (!$payout) {
                    return;
                }
                if (!empty($payout->tinkoff_payout_payment_id)
                    || (string) $payout->status !== 'INITIATED'
                    || $payout->completed_at !== null
                    || $payout->when_to_run === null
                    || $payout->when_to_run->gt(now())) {
                    return;
                }
                $svc->runPayout($payout);
            });
        }
    }
}
