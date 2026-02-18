<?php

namespace App\Http\Controllers;

use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffPayoutsService;

class TinkoffAdminPaymentController extends Controller
{

    public function index(\Illuminate\Http\Request $r)
    {
        $q = \App\Models\TinkoffPayment::query()->with('partner');

        if ($r->filled('status')) {
            $q->where('status', $r->string('status'));
        }
        if ($r->filled('partner_id')) {
            $q->where('partner_id', (int)$r->partner_id);
        }
        if ($r->filled('from')) {
            $q->where('created_at', '>=', $r->date('from'));
        }
        if ($r->filled('to')) {
            $q->where('created_at', '<', $r->date('to')->addDay());
        }

        $payments = $q->latest()->paginate(30)->appends($r->query());
        $partners = \App\Models\Partner::orderBy('title')->get(['id','title']);

        return view('tinkoff.payments.index', compact('payments','partners'));
    }



    public function show($id, TinkoffPayoutsService $svc)
    {
        $payment = TinkoffPayment::with(['partner','payout'])->findOrFail($id);

        // Калькуляция (поступило/банк/платформа/партнёру)
        $breakdown = $svc->breakdownForPayment($payment);

        // Окно возврата 48ч (если CONFIRMED)
        $refundUntil = null;
        if ($payment->confirmed_at) {
            $refundUntil = $payment->confirmed_at->clone()->addHours(48);
        }

        return view('tinkoff.payments.show', compact('payment','breakdown','refundUntil'));
    }
}
