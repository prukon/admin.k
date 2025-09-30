<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;

class TinkoffPayoutController extends Controller
{
    public function payNow($deal, TinkoffPayoutsService $svc)
    {
        $payment = TinkoffPayment::where('deal_id', $deal)->firstOrFail();
        $payout = $svc->createAndRunPayout($payment, true, null);
        return back()->with('status', 'Выплата запущена');
    }

    public function delay($deal, Request $r, TinkoffPayoutsService $svc)
    {
        $ts = new \DateTimeImmutable($r->input('run_at')); // YYYY-mm-dd HH:ii
        $payment = TinkoffPayment::where('deal_id', $deal)->firstOrFail();
        $svc->createAndRunPayout($payment, true, $ts);
        return back()->with('status', 'Выплата отложена');
    }
}
