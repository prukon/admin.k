<?php

namespace App\Jobs;

use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Refund;
use App\Models\UserPrice;
use App\Services\Robokassa\RobokassaRefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RobokassaProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $refundId)
    {
    }

    public function handle(RobokassaRefundService $svc): void
    {
        DB::transaction(function () use ($svc) {
            /** @var Refund|null $refund */
            $refund = Refund::whereKey($this->refundId)->lockForUpdate()->first();
            if (!$refund) {
                return;
            }

            if ((string) $refund->provider !== 'robokassa') {
                return;
            }

            if ((string) $refund->status !== 'pending') {
                return;
            }

            $invId = (int) ($refund->meta['inv_id'] ?? 0);
            if ($invId <= 0) {
                $this->fail($refund, 'inv_id_missing', []);
                return;
            }

            // Настройки Robokassa партнёра
            $ps = PaymentSystem::where('partner_id', (int) $refund->partner_id)
                ->where('name', 'robokassa')
                ->first();
            if (!$ps) {
                $this->fail($refund, 'robokassa_not_configured', []);
                return;
            }

            // 1) OpKey
            try {
                $opKey = $svc->fetchOpKey($ps, $invId);
            } catch (\Throwable $e) {
                Log::warning('Robokassa refund: fetchOpKey failed', [
                    'refund_id' => $refund->id,
                    'inv_id' => $invId,
                    'msg' => $e->getMessage(),
                ]);
                $this->fail($refund, 'opkey_fetch_failed', ['exception' => $e->getMessage()]);
                return;
            }

            $amount = (float) $refund->amount;
            try {
                $create = $svc->createFullRefund($ps, $opKey, $amount);
            } catch (\Throwable $e) {
                $this->fail($refund, 'refund_create_exception', ['exception' => $e->getMessage()]);
                return;
            }

            $refund->meta = array_merge($refund->meta, [
                'op_key' => $opKey,
                'create_response' => $create,
            ]);
            $refund->save();

            if (empty($create['ok'])) {
                $this->fail($refund, 'refund_create_failed', $create);
                return;
            }

            $requestId = (string) ($create['requestId'] ?? '');
            if ($requestId === '') {
                $this->fail($refund, 'refund_request_id_missing', $create);
                return;
            }

            $refund->provider_refund_id = $requestId;
            $refund->save();

            // 2) State
            $state = $svc->getRefundState($requestId);
            $refund->meta = array_merge($refund->meta, [
                'state_response' => $state,
            ]);
            $refund->save();

            $label = (string) ($state['label'] ?? '');
            if ($label === 'finished') {
                $this->succeed($refund);
                return;
            }
            if ($label === 'canceled') {
                $this->fail($refund, 'refund_canceled', $state);
                return;
            }

            // processing/unknown: оставляем pending (можно будет добавить отдельный поллинг/ретраи).
        });
    }

    private function succeed(Refund $refund): void
    {
        $refund->status = 'succeeded';
        $refund->processed_at = now();
        $refund->save();

        /** @var Payable|null $payable */
        $payable = Payable::find((int) $refund->payable_id);
        if (!$payable) {
            return;
        }

        $payable->status = 'refunded';
        $payable->save();

        if ((string) $payable->type !== 'monthly_fee') {
            return;
        }

        $month = $payable->month?->format('Y-m-d')
            ?: ($payable->meta['month'] ?? null);

        // fallback: из intent (на случай старых данных)
        if (!is_string($month) || $month === '') {
            $intentId = (int) ($refund->meta['payment_intent_id'] ?? 0);
            if ($intentId > 0) {
                $intent = PaymentIntent::find($intentId);
                if ($intent && !empty($intent->payment_date)) {
                    $month = (string) $intent->payment_date;
                }
            }
        }

        if (is_string($month) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $month) && strtotime($month)) {
            UserPrice::updateOrCreate(
                [
                    'user_id' => (int) $refund->user_id,
                    'new_month' => $month,
                ],
                [
                    'is_paid' => 0,
                ]
            );
        } else {
            Log::warning('Refund succeeded but month is invalid', [
                'refund_id' => $refund->id,
                'month' => $month,
                'payable_id' => $payable->id,
            ]);
        }
    }

    private function fail(Refund $refund, string $reason, array $details): void
    {
        $refund->status = 'failed';
        $refund->processed_at = now();
        $refund->meta = array_merge($refund->meta, [
            'failed_reason' => $reason,
            'failed_details' => $details,
        ]);
        $refund->save();
    }
}

