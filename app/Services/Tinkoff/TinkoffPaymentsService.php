<?php

namespace App\Services\Tinkoff;

use App\Models\PaymentIntent;
use App\Models\Payable;
use App\Models\PaymentSystem;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\MyLog;
use App\Models\TinkoffPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;

class TinkoffPaymentsService
{
    public function initPayment(int $partnerId, int $amountCents, string $method = null, array $data = []): TinkoffPayment
    {
        $orderId = Str::uuid()->toString();

        $payment = TinkoffPayment::create([
            'order_id' => $orderId,
            'partner_id' => $partnerId,
            'amount' => $amountCents,
            'method' => $method,
            'status' => 'NEW',
        ]);

        $cfg = $this->resolvePaymentConfig($partnerId);

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

        if (!empty($data)) {
            // Важно: в документации банк возвращает это в уведомлении в параметре Data
            $payload['DATA'] = $data;
        }

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


    /**
     * Webhook по оплатам (T‑Bank eacq).
     * Важно:
     * - подтверждаем подпись по ключу партнёра (из payment_systems), поэтому сначала ищем order_id → partner_id.
     * - идемпотентность бизнес-эффекта обеспечиваем через payment_intents(provider, provider_inv_id) + транзакцию.
     */
    public function handleWebhook(array $data, bool $skipSignature = false): void
    {
        $orderId = $data['OrderId'] ?? null;
        if (!$orderId) return;

        $payment = TinkoffPayment::where('order_id', $orderId)->first();
        if (!$payment) return;

        $cfg = $this->resolvePaymentConfig((int) $payment->partner_id);
        if (!$skipSignature) {
            if (!TinkoffSignature::verify($data, $cfg['password'])) {
                throw new \RuntimeException('Invalid T-Bank signature');
            }
        }

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

        // --- Связка с доменным воркфлоу (payables/payment_intents/payments/users_prices) ---
        try {
            $this->applyDomainEffects($payment, $data);
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[domain-effects failed] '.$e->getMessage(), [
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);
            // не падаем: webhook должен быть максимально "мягким", чтобы не блокировать выплаты/повторы
        }

        // Автовыплата партнёру (управляется чекбоксом в /admin/settings/tbank-commissions)
        if ($payment->status === 'CONFIRMED' && $payment->deal_id) {
            try {
                $ps = PaymentSystem::where('partner_id', (int) $payment->partner_id)->where('name', 'tbank')->first();
                $enabled = $ps ? (bool) ($ps->settings['auto_payout_enabled'] ?? false) : false;
                if (!$enabled) {
                    return;
                }
            } catch (\Throwable $e) {
                Log::channel('tinkoff')->error('[auto-payout flag read failed] ' . $e->getMessage());
                return;
            }

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
                Log::channel('tinkoff')->error('[sm-register PATCH failed] ' . $e->getMessage());
            }

            // 2) Стартуем автоваыплату (FinalPayout = true)
            try {
                app(\App\Services\Tinkoff\TinkoffPayoutsService::class)
                    ->createAndRunPayout($payment, true, null);
                Log::channel('tinkoff')->info('[auto-payout started]', [
                    'deal_id' => $payment->deal_id, 'payment_id' => $payment->id
                ]);
            } catch (\Throwable $e) {
                Log::channel('tinkoff')->error('[auto-payout failed] ' . $e->getMessage());
            }
        }

    }

    protected function resolvePaymentConfig(int $partnerId): array
    {
        // 1) Пытаемся взять ключи из БД (payment_systems) для текущего партнёра
        $ps = PaymentSystem::where('partner_id', $partnerId)->where('name', 'tbank')->first();
        if ($ps && $ps->is_connected) {
            $s = $ps->settings;
            $isTest = (bool) $ps->test_mode;

            return [
                'terminal_key' => (string) ($s['terminal_key'] ?? ''),
                'password'     => (string) ($s['token_password'] ?? ''),
                'base_url'     => $isTest ? 'https://rest-api-test.tinkoff.ru' : 'https://securepay.tinkoff.ru',
                'success_url'  => url('/payments/tinkoff/{order}/success'),
                'fail_url'     => url('/payments/tinkoff/{order}/fail'),
                'notify_url'   => url('/webhooks/tinkoff/payments'),
            ];
        }

        // 2) fallback (исторически могло быть в .env/config)
        $cfg = Config::get('tinkoff.payment');
        return [
            'terminal_key' => (string) ($cfg['terminal_key'] ?? ''),
            'password'     => (string) ($cfg['password'] ?? ''),
            'base_url'     => (string) ($cfg['base_url'] ?? 'https://securepay.tinkoff.ru'),
            'success_url'  => (string) ($cfg['success_url'] ?? url('/payments/tinkoff/{order}/success')),
            'fail_url'     => (string) ($cfg['fail_url'] ?? url('/payments/tinkoff/{order}/fail')),
            'notify_url'   => (string) ($cfg['notify_url'] ?? url('/webhooks/tinkoff/payments')),
        ];
    }

    protected function applyDomainEffects(TinkoffPayment $payment, array $webhook): void
    {
        $status = (string) ($webhook['Status'] ?? '');
        $paymentId = $webhook['PaymentId'] ?? null;
        $orderId = $webhook['OrderId'] ?? null;

        if (!$paymentId || !$orderId) {
            return;
        }

        // Нас интересуют только финальные статусы
        $isSuccess = ($status === 'CONFIRMED');
        $isFail = in_array($status, ['CANCELED', 'REJECTED', 'DEADLINE_EXPIRED'], true);
        if (!$isSuccess && !$isFail) {
            return;
        }

        // Ищем intent
        $intent = PaymentIntent::query()
            ->where('provider', 'tbank')
            ->where(function ($q) use ($paymentId, $orderId) {
                $q->where('provider_inv_id', (int) $paymentId)
                  ->orWhere('tbank_payment_id', (int) $paymentId)
                  ->orWhere('tbank_order_id', (string) $orderId);
            })
            ->first();

        if (!$intent) {
            return;
        }

        // Если уже paid — ничего не делаем (идемпотентность)
        if ((string) $intent->status === 'paid') {
            return;
        }

        $intentId = (int) $intent->id;
        DB::transaction(function () use ($intentId, $payment, $webhook, $isSuccess, $isFail) {
            /** @var PaymentIntent $locked */
            $locked = PaymentIntent::whereKey($intentId)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }

            if ((string) $locked->status === 'paid') {
                return;
            }

            // синхронизируем внешние идентификаторы
            $pid = (int) ($webhook['PaymentId'] ?? 0);
            if ($pid) {
                $locked->provider_inv_id = $pid;
                $locked->tbank_payment_id = $pid;
            }
            $oid = (string) ($webhook['OrderId'] ?? '');
            if ($oid !== '') {
                $locked->tbank_order_id = $oid;
            }

            // Сохраняем последний статус провайдера для отладки
            $meta = [];
            if (!empty($locked->meta)) {
                $meta = json_decode((string) $locked->meta, true);
                if (!is_array($meta)) $meta = [];
            }
            $meta['tbank'] = array_merge(($meta['tbank'] ?? []), [
                'last_status' => (string) ($webhook['Status'] ?? null),
                'last_webhook' => $webhook,
            ]);
            $locked->meta = json_encode($meta, JSON_UNESCAPED_UNICODE);

            $payable = $locked->payable;
            if (!$payable) {
                return;
            }

            if ($isSuccess) {
                // users_prices только для monthly_fee
                if ((string) $payable->type === 'monthly_fee') {
                    $month = $payable->month ? $payable->month->format('Y-m-d') : ($payable->meta['month'] ?? null);
                    if (is_string($month) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $month) && strtotime($month)) {
                        UserPrice::updateOrCreate(
                            ['user_id' => (int) $locked->user_id, 'new_month' => $month],
                            ['is_paid' => 1]
                        );
                    }
                }

                $user = User::find((int) $locked->user_id);
                $teamName = $user?->team?->title ?? 'Без команды';
                $currentDateTime = now()->format('Y-m-d H:i:s');

                Payment::updateOrCreate(
                    [
                        'payment_number' => (string) ($webhook['PaymentId'] ?? ''),
                        'partner_id'     => (int) $locked->partner_id,
                    ],
                    [
                        'user_id'         => (int) $locked->user_id,
                        'user_name'       => ($user?->full_name ?: trim(($user->lastname ?? '').' '.($user->name ?? ''))) ?: 'Неизвестно',
                        'team_title'      => $teamName,
                        'operation_date'  => $currentDateTime,
                        'payment_month'   => (string) ($payable->type === 'monthly_fee' ? ($payable->month?->format('Y-m-d') ?? '') : 'Клубный взнос'),
                        'summ'            => (string) $locked->out_sum,
                        'deal_id'         => $payment->deal_id ?: null,
                        'payment_id'      => (string) ($webhook['PaymentId'] ?? null),
                        'payment_status'  => (string) ($webhook['Status'] ?? null),
                    ]
                );

                MyLog::create([
                    'type'        => 5,
                    'action'      => 50,
                    'author_id'   => (int) $locked->user_id,
                    'partner_id'  => (int) $locked->partner_id,
                    'description' => "TBank payment CONFIRMED. PaymentId: ".($webhook['PaymentId'] ?? '').". OrderId: ".($webhook['OrderId'] ?? '').".",
                    'created_at'  => now(),
                ]);

                $locked->status = 'paid';
                $locked->paid_at = now();
                $locked->save();

                $payable->status = 'paid';
                $payable->paid_at = now();
                $payable->save();
            } elseif ($isFail) {
                $locked->status = 'failed';
                $locked->save();
            }
        });
    }
}
