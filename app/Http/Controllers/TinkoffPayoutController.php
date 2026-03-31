<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use App\Http\Requests\Tinkoff\PayoutDelayRequest;
use App\Models\User;

class TinkoffPayoutController extends Controller
{
    public function payNow($deal, TinkoffPayoutsService $svc)
    {
        $payment = TinkoffPayment::where('deal_id', $deal)->firstOrFail();
        // Для не-superadmin запрещаем управлять выплатами чужого партнёра
        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');
        if (!$isSuperadmin && (int) $payment->partner_id !== (int) app('current_partner')->id) {
            abort(404);
        }
        $actorId = (int) auth()->id();
        $payout = $svc->createAndRunPayout($payment, true, null, 'manual', $actorId ?: null);
        return back()->with('status', 'Выплата запущена');
    }

    public function delay($deal, PayoutDelayRequest $r, TinkoffPayoutsService $svc)
    {
        $ts = new \DateTimeImmutable($r->input('run_at')); // YYYY-mm-dd HH:ii
        $payment = TinkoffPayment::where('deal_id', $deal)->firstOrFail();
        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');
        if (!$isSuperadmin && (int) $payment->partner_id !== (int) app('current_partner')->id) {
            abort(404);
        }
        $actorId = (int) auth()->id();
        $svc->createAndRunPayout($payment, true, $ts, 'delayed', $actorId ?: null);
        return back()->with('status', 'Выплата отложена');
    }
}
