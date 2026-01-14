<?php

namespace App\Http\Controllers\Admin\Report;

use App\Http\Controllers\Controller;
use App\Jobs\RobokassaProcessRefundJob;
use App\Jobs\TinkoffProcessRefundJob;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\TinkoffPayout;
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

            // Определяем провайдера по полям T-Bank в payments (deal_id/payment_id/payment_status).
            $provider = (!empty($lockedPayment->deal_id) || !empty($lockedPayment->payment_id) || !empty($lockedPayment->payment_status))
                ? 'tbank'
                : 'robokassa';

            if ($provider === 'tbank') {
                // 1) запретить возврат, если была выплата партнёру
                $dealId = (string) ($lockedPayment->deal_id ?? '');
                if ($dealId !== '') {
                    $payout = TinkoffPayout::query()
                        ->where('partner_id', (int) $partnerId)
                        ->where('deal_id', $dealId)
                        ->orderByDesc('id')
                        ->first();
                    if ($payout && (string) $payout->status !== 'REJECTED') {
                        return ['error' => true, 'message' => 'payout_exists'];
                    }
                }

                $paymentIdStr = (is_string($lockedPayment->payment_id) || is_numeric($lockedPayment->payment_id))
                    ? (string) $lockedPayment->payment_id
                    : ((is_string($lockedPayment->payment_number) || is_numeric($lockedPayment->payment_number)) ? (string) $lockedPayment->payment_number : '');
                if ($paymentIdStr === '' || !ctype_digit($paymentIdStr)) {
                    return ['error' => true, 'message' => 'tbank_payment_id_missing'];
                }
                $paymentId = (int) $paymentIdStr;

                $intent = PaymentIntent::query()
                    ->where('provider', 'tbank')
                    ->where('partner_id', (int) $partnerId)
                    ->where(function ($q) use ($paymentId) {
                        $q->where('provider_inv_id', $paymentId)
                          ->orWhere('tbank_payment_id', $paymentId);
                    })
                    ->first();
                if (!$intent) {
                    return ['error' => true, 'message' => 'payment_intent_not_found'];
                }
                if ((string) $intent->status !== 'paid') {
                    return ['error' => true, 'message' => 'payment_not_paid'];
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
                    'provider' => 'tbank',
                    'meta' => [
                        'tbank_payment_id' => $paymentId,
                        'deal_id' => $dealId,
                        'payment_intent_id' => (int) $intent->id,
                        'actor_id' => $actorId,
                        'comment' => $comment,
                        'idempotency_key' => 'tbank:full:' . (int) $lockedPayment->id,
                        'ip' => (string) request()->ip(),
                        'ua' => (string) request()->userAgent(),
                    ],
                ]);

                TinkoffProcessRefundJob::dispatch((int) $refund->id);
                $refund->refresh();

                return [
                    'refund_id' => $refund->id,
                    'status' => (string) $refund->status,
                    'message' => 'refund_created',
                ];
            }

            // Robokassa (как было)
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
            $message = (string) ($result['message'] ?? 'refund_failed');
            $httpMessage = match ($message) {
                'payout_exists' => 'Возврат запрещён: по этому платежу уже есть выплата партнёру.',
                'tbank_payment_id_missing' => 'Не найден PaymentId T-Bank для этого платежа.',
                'payment_intent_not_found' => 'Не найдены данные платежа (payment_intent).',
                'payment_not_paid' => 'Платёж не в статусе paid.',
                default => $message,
            };

            return response()->json(['message' => $httpMessage] + $result, 422);
        }

        return response()->json($result);
    }
}

