<?php

namespace App\Jobs;

use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Refund;
use App\Models\UserPrice;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TinkoffProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $refundId)
    {
    }

    public function handle(): void
    {
        DB::transaction(function () {
            /** @var Refund|null $refund */
            $refund = Refund::whereKey($this->refundId)->lockForUpdate()->first();
            if (!$refund) {
                return;
            }

            if ((string) $refund->provider !== 'tbank') {
                return;
            }

            if ((string) $refund->status !== 'pending') {
                return;
            }

            $paymentIntentId = (int) ($refund->meta['payment_intent_id'] ?? 0);
            if ($paymentIntentId <= 0) {
                $this->failRefund($refund, 'payment_intent_id_missing', []);
                return;
            }

            /** @var PaymentIntent|null $intent */
            $intent = PaymentIntent::find($paymentIntentId);
            if (!$intent) {
                $this->failRefund($refund, 'payment_intent_not_found', ['payment_intent_id' => $paymentIntentId]);
                return;
            }

            // Настройки T-Bank (eacq) партнёра
            $ps = PaymentSystem::where('partner_id', (int) $refund->partner_id)
                ->where('name', 'tbank')
                ->first();
            if (!$ps || !$ps->is_connected) {
                $this->failRefund($refund, 'tbank_not_configured', []);
                return;
            }

            $settings = $ps->settings;
            $terminalKey = (string) ($settings['terminal_key'] ?? '');
            $password = (string) ($settings['token_password'] ?? '');
            if ($terminalKey === '' || $password === '') {
                $this->failRefund($refund, 'tbank_keys_missing', []);
                return;
            }

            $baseUrl = (bool) $ps->test_mode ? 'https://rest-api-test.tinkoff.ru' : 'https://securepay.tinkoff.ru';

            $paymentId = (int) ($refund->meta['tbank_payment_id'] ?? 0);
            if ($paymentId <= 0) {
                // fallback: из intent
                $paymentId = (int) ($intent->tbank_payment_id ?: $intent->provider_inv_id);
            }
            if ($paymentId <= 0) {
                $this->failRefund($refund, 'tbank_payment_id_missing', []);
                return;
            }

            $amountRub = (float) $refund->amount;
            $amountCents = (int) round($amountRub * 100);
            if ($amountCents <= 0) {
                $this->failRefund($refund, 'invalid_amount', ['amount' => $amountRub]);
                return;
            }

            // Cancel в T-Bank используется и как возврат для подтвержденных платежей.
            $payload = [
                'TerminalKey' => $terminalKey,
                'PaymentId' => $paymentId,
                // Для полного возврата передаём сумму платежа (в копейках) — безопаснее, чем полагаться на дефолты.
                'Amount' => $amountCents,
            ];
            $payload['Token'] = TinkoffSignature::makeToken($payload, $password);

            $resp = TinkoffApiClient::post($baseUrl, '/v2/Cancel', $payload);

            $refund->meta = array_merge($refund->meta, [
                'cancel_request' => $payload,
                'cancel_response' => $resp,
            ]);
            $refund->provider_refund_id = (string) $paymentId;
            $refund->save();

            if (empty($resp['Success'])) {
                $this->failRefund($refund, 'tbank_cancel_failed', $resp);
                return;
            }

            // У банка возврат может быть асинхронным, но для MVP фиксируем success по Success=true.
            $this->succeedRefund($refund);
        });
    }

    private function succeedRefund(Refund $refund): void
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
            Log::warning('T-Bank refund succeeded but month is invalid', [
                'refund_id' => $refund->id,
                'month' => $month,
                'payable_id' => $payable->id,
            ]);
        }
    }

    private function failRefund(Refund $refund, string $reason, array $details): void
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

