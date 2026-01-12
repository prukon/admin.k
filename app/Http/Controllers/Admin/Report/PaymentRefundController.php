<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use App\Jobs\RobokassaProcessRefundJob;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentRefundController extends Controller
{
    public function store(Request $request, Payment $payment)
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $partnerId = (int) app('current_partner')->id;
        if ((int) $payment->partner_id !== $partnerId) {
            abort(403);
        }

        $comment = (string) $request->input('comment', '');
        $actorId = (int) $request->user()->id;

        $result = DB::transaction(function () use ($payment, $partnerId, $comment, $actorId) {
            // Идемпотентность/защита от двойного клика: лочим строку платежа.
            $lockedPayment = Payment::whereKey((int) $payment->id)->lockForUpdate()->first();
            if (!$lockedPayment) {
                abort(404);
            }

            $existing = Refund::where('payment_id', (int) $lockedPayment->id)
                ->orderByDesc('id')
                ->first();
            if ($existing && in_array((string) $existing->status, ['pending', 'succeeded'], true)) {
                return [
                    'refund_id' => $existing->id,
                    'status' => (string) $existing->status,
                    'message' => 'refund_already_exists',
                ];
            }

            $invIdStr = (is_string($lockedPayment->payment_number) || is_numeric($lockedPayment->payment_number))
                ? (string) $lockedPayment->payment_number
                : '';
            if ($invIdStr === '' || !ctype_digit($invIdStr)) {
                return ['error' => true, 'message' => 'payment_number_missing'];
            }
            $invId = (int) $invIdStr;

            $intent = PaymentIntent::where('provider', 'robokassa')
                ->where('partner_id', $partnerId)
                ->where('provider_inv_id', $invId)
                ->first();
            if (!$intent) {
                return ['error' => true, 'message' => 'payment_intent_not_found'];
            }
            if ((string) $intent->status !== 'paid') {
                return ['error' => true, 'message' => 'payment_not_paid'];
            }

            $paidAt = $intent->paid_at ? Carbon::parse($intent->paid_at) : null;
            if (!$paidAt && !empty($lockedPayment->operation_date)) {
                $paidAt = Carbon::parse($lockedPayment->operation_date);
            }
            if (!$paidAt) {
                return ['error' => true, 'message' => 'paid_at_missing'];
            }
            if ($paidAt->copy()->addDays(7)->lt(now())) {
                return ['error' => true, 'message' => 'refund_time_limit_exceeded'];
            }

            $amount = (float) $lockedPayment->summ;
            if ($amount <= 0) {
                return ['error' => true, 'message' => 'invalid_amount'];
            }

            $refund = Refund::create([
                'partner_id' => $partnerId,
                'user_id' => (int) ($intent->user_id ?? $lockedPayment->user_id),
                'payable_id' => (int) $intent->payable_id,
                'payment_id' => (int) $lockedPayment->id,
                'amount' => $amount,
                'currency' => 'RUB',
                'status' => 'pending',
                'provider' => 'robokassa',
                'meta' => [
                    'inv_id' => $invId,
                    'payment_intent_id' => (int) $intent->id,
                    'paid_at' => $paidAt->toIso8601String(),
                    'actor_id' => $actorId,
                    'comment' => $comment,
                    'idempotency_key' => 'robokassa:full:' . (int) $lockedPayment->id,
                    'ip' => (string) request()->ip(),
                    'ua' => (string) request()->userAgent(),
                ],
            ]);

            RobokassaProcessRefundJob::dispatch((int) $refund->id);
            $refund->refresh();

            return [
                'refund_id' => $refund->id,
                'status' => (string) $refund->status,
                'message' => 'refund_created',
            ];
        });

        if (!empty($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}

