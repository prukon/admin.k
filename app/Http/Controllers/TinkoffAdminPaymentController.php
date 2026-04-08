<?php

namespace App\Http\Controllers;

use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Окно возврата (часы из БД/config, то же значение что и задержка автовыплаты)
        $refundUntil = null;
        if ($payment->confirmed_at) {
            $delayHours = \App\Models\Setting::getTinkoffPayoutAutoDelayHours();
            $refundUntil = $payment->confirmed_at->clone()->addHours($delayHours);
        }

        // История статусов (оплата + выплаты) из лог-таблиц
        $historyEvents = [];
        $payouts = collect();

        if (Schema::hasTable('tinkoff_payment_status_logs')) {
            $payLogs = DB::table('tinkoff_payment_status_logs')
                ->where('partner_id', (int) $payment->partner_id)
                ->where('tinkoff_payment_id', (int) $payment->id)
                ->orderBy('created_at')
                ->get();

            foreach ($payLogs as $l) {
                $payload = null;
                if (!is_null($l->payload)) {
                    $payload = is_string($l->payload) ? json_decode($l->payload, true) : $l->payload;
                }

                $historyEvents[] = [
                    'at' => $l->created_at ? Carbon::parse($l->created_at) : null,
                    'kind' => 'payment',
                    'source' => (string) ($l->event_source ?? 'webhook'),
                    'from_status' => $l->from_status !== null ? (string) $l->from_status : null,
                    'to_status' => $l->to_status !== null ? (string) $l->to_status : null,
                    'bank_status' => $l->bank_status !== null ? (string) $l->bank_status : null,
                    'bank_payment_id' => $l->bank_payment_id !== null ? (string) $l->bank_payment_id : null,
                    'order_id' => $l->order_id !== null ? (string) $l->order_id : null,
                    'payload' => $payload,
                ];
            }
        }

        $payouts = collect();
        if (!empty($payment->deal_id)) {
            $payouts = TinkoffPayout::query()
                ->where('partner_id', (int) $payment->partner_id)
                ->where('deal_id', (string) $payment->deal_id)
                ->orderBy('id')
                ->get();

            $payoutIds = $payouts->pluck('id')->filter()->values()->all();
            if (!empty($payoutIds) && Schema::hasTable('tinkoff_payout_status_logs')) {
                $payoutLogs = DB::table('tinkoff_payout_status_logs')
                    ->whereIn('payout_id', $payoutIds)
                    ->orderBy('created_at')
                    ->get();

                foreach ($payoutLogs as $l) {
                    $payload = null;
                    if (!is_null($l->payload)) {
                        $payload = is_string($l->payload) ? json_decode($l->payload, true) : $l->payload;
                    }

                    $historyEvents[] = [
                        'at' => $l->created_at ? Carbon::parse($l->created_at) : null,
                        'kind' => 'payout',
                        'source' => 'poll',
                        'from_status' => $l->from_status !== null ? (string) $l->from_status : null,
                        'to_status' => $l->to_status !== null ? (string) $l->to_status : null,
                        'bank_status' => null,
                        'bank_payment_id' => null,
                        'order_id' => null,
                        'payout_id' => (int) ($l->payout_id ?? 0),
                        'payload' => $payload,
                    ];
                }
            }
        }

        usort($historyEvents, static function (array $a, array $b): int {
            $atA = $a['at'] instanceof Carbon ? $a['at']->toIso8601String() : '';
            $atB = $b['at'] instanceof Carbon ? $b['at']->toIso8601String() : '';
            return strcmp($atA, $atB);
        });

        $showPayoutActions = !empty($payment->deal_id)
            && ($payouts->isEmpty() || (string) $payouts->last()->status === 'REJECTED');

        $hasCompletedPayout = $payouts->contains(fn (TinkoffPayout $p) => (string) $p->status === 'COMPLETED');

        return view('tinkoff.payments.show', compact('payment', 'breakdown', 'refundUntil', 'historyEvents', 'payouts', 'showPayoutActions', 'hasCompletedPayout'));
    }
}
