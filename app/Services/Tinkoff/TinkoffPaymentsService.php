<?php

namespace App\Services\Tinkoff;

use App\Models\TinkoffPayment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;

class TinkoffPaymentsService
{
    public function initPayment(int $partnerId, int $amountCents, string $method = null): TinkoffPayment
    {
        $orderId = Str::uuid()->toString();

        $payment = TinkoffPayment::create([
            'order_id' => $orderId,
            'partner_id' => $partnerId,
            'amount' => $amountCents,
            'method' => $method,
            'status' => 'NEW',
        ]);

        $cfg = Config::get('tinkoff.payment');

        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'Amount' => $amountCents,
            'OrderId' => $orderId,
//            'Description'          => 'Оплата абонплаты',
            'SuccessURL' => $this->asciiUrl(str_replace('{order}', $orderId, $cfg['success_url'])),
            'FailURL' => $this->asciiUrl(str_replace('{order}', $orderId, $cfg['fail_url'])),
            'NotificationURL' => $this->asciiUrl($cfg['notify_url']),
            // открываем сделку NN
            'CreateDealWithType' => 'NN',

            'Description' => 'Subscription payment', // ASCII, без кириллицы
            'PayType' => 'O',                    // одностадийная явно, согласно EACQ, п.2.3 Init
            'Language' => 'ru',                   // язык формы

        ];

        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/Init', $payload);

        if (!empty($res['Success'])) {
            $payment->update([
                'status' => 'FORM',
                'tinkoff_payment_id' => $res['PaymentId'] ?? null,
                'payment_url' => $res['PaymentURL'] ?? null,
                'payload' => $res,
            ]);
        } else {
            $payment->update(['payload' => $res]);
        }

        return $payment;
    }

    private function asciiUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return $url;
        $asciiHost = idn_to_ascii($parts['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (!$asciiHost) return $url;
        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return $scheme . '://' . $asciiHost . $path . $query;
    }


    // Вебхук по оплатам
//    public function handleWebhook(array $data): void
    public function handleWebhook(array $data, bool $skipSignature = false): void
    {

        \Log::channel('tinkoff')->debug('[WEBHOOK VERIFY DEBUG]', [
            'sorted_fields' => (function ($arr) {
                unset($arr['Token']);
                $arr['Password'] = config('tinkoff.payment.password');
                ksort($arr, SORT_STRING);
                // нормализуем bool как в сигнатуре
                $normalized = [];
                foreach ($arr as $k => $v) {
                    if (is_array($v) || is_object($v)) continue;
                    if (is_bool($v)) $normalized[$k] = $v ? 'true' : 'false';
                    elseif (is_null($v)) continue;
                    else                  $normalized[$k] = (string)$v;
                }
                return $normalized;
            })($data),
            'received_token' => $data['Token'] ?? null,
            'expected_token' => \App\Services\Tinkoff\TinkoffSignature::makeToken($data, config('tinkoff.payment.password')),
        ]);


        if (!$skipSignature) {
            $ok = \App\Services\Tinkoff\TinkoffSignature::verify($data, config('tinkoff.payment.password'));
            if (!$ok) {
                throw new \RuntimeException('Invalid Tinkoff signature');
            }
        }


        $cfg = Config::get('tinkoff.payment');
        if (!TinkoffSignature::verify($data, $cfg['password'])) {
            throw new \RuntimeException('Invalid Tinkoff signature');
        }

        $orderId = $data['OrderId'] ?? null;
        if (!$orderId) return;

        $payment = TinkoffPayment::where('order_id', $orderId)->first();
        if (!$payment) return;

        // В нотификациях прилетает SpAccumulationId — берем как deal_id
        if (isset($data['SpAccumulationId']) && !$payment->deal_id) {
            $payment->deal_id = (string)$data['SpAccumulationId'];
        }

        $status = $data['Status'] ?? null;
        if ($status === 'CONFIRMED') {
            $payment->status = 'CONFIRMED';
            $payment->confirmed_at = now();
        } elseif ($status === 'CANCELED' || $status === 'REJECTED') {
            $payment->status = 'CANCELED';
            $payment->canceled_at = now();
        }

        // сохраним последний пэйлоад
        $pl = $payment->payload ?? [];
        $pl['last_webhook'] = $data;
        $payment->payload = $pl;

        $payment->save();

        // ...после $payment->save();

        if ($payment->status === 'CONFIRMED' && $payment->deal_id) {
            // 1) Обновим назначение платежа (details) перед выплатой
            try {
                $partner = $payment->partner;
                if (!empty($partner->tinkoff_partner_id)) {
                    $details = \App\Helpers\TinkoffDetailsHelper::makeDetailsForPeriod($payment);
                    app(\App\Services\Tinkoff\TinkoffSmRegisterService::class)
                        ->patchPartner($partner->tinkoff_partner_id, [
                            'bankAccount' => ['details' => $details],
                        ]);
                }
            } catch (\Throwable $e) {
                \Log::channel('tinkoff')->error('[sm-register PATCH failed] ' . $e->getMessage());
            }

            // 2) Стартуем автоваыплату (FinalPayout = true)
            try {
                app(\App\Services\Tinkoff\TinkoffPayoutsService::class)
                    ->createAndRunPayout($payment, true, null);
                \Log::channel('tinkoff')->info('[auto-payout started]', [
                    'deal_id' => $payment->deal_id, 'payment_id' => $payment->id
                ]);
            } catch (\Throwable $e) {
                \Log::channel('tinkoff')->error('[auto-payout failed] ' . $e->getMessage());
            }
        }

    }
}
