<?php

namespace App\Services\Tinkoff;

use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TinkoffPayoutsService
{
    /**
     * Раскладка по комиссиям для UI/диагностики.
     *
     * Важно: комиссии считаем так же, как для net-выплаты:
     * gross - bank acquiring fee - bank payout fee - platform fee.
     */
    public function breakdownForPayment(TinkoffPayment $payment): array
    {
        $gross = (int) $payment->amount; // копейки
        $rule = $this->pickCommissionRule((int) $payment->partner_id, $payment->method);

        $bankAcceptFee = $this->calcFeeCents(
            $gross,
            (float) ($rule->acquiring_percent ?? 2.49),
            (float) ($rule->acquiring_min_fixed ?? 3.49)
        );

        $bankPayoutFee = $this->calcFeeCents(
            $gross,
            (float) ($rule->payout_percent ?? 0.10),
            (float) ($rule->payout_min_fixed ?? 0.00)
        );

        $platformFee = $this->calcFeeCents(
            $gross,
            (float) ($rule->platform_percent ?? $rule->percent ?? 2.00),
            (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
        );

        $net = max(0, $gross - $bankAcceptFee - $bankPayoutFee - $platformFee);

        return [
            'gross' => $gross,
            'bankAccept' => $bankAcceptFee,
            'bankPayout' => $bankPayoutFee,
            'platformFee' => $platformFee,
            'net' => $net,
            'rule' => $rule,
        ];
    }

    public function closeSpDeal(string $dealId, int $partnerId): array
    {
        $dealId = trim($dealId);
        if ($dealId === '') {
            throw new \InvalidArgumentException('DealId is empty');
        }

        $cfg = $this->resolveE2cConfig($partnerId);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'DealId'      => $dealId,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        return TinkoffApiClient::post($cfg['base_url'], '/e2c/v2/CloseSpDeal', $payload);
    }

    public function createAndRunPayout(
        TinkoffPayment $payment,
        bool $isFinal = true,
        ?\DateTimeInterface $delayUntil = null,
        string $source = 'manual',
        ?int $initiatedByUserId = null
    ): TinkoffPayout
    {
        return DB::transaction(function () use ($payment, $isFinal, $delayUntil, $source, $initiatedByUserId) {
            // Идемпотентность: не создаём дубль выплаты по одному payment_id.
            $existing = TinkoffPayout::query()
                ->where('payment_id', (int) $payment->id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            // Автовыплата из вебхука: не более одной строки на платёж — повторные CONFIRMED не плодят выплаты
            // и не делают «вторую попытку» после REJECTED (повтор только вручную).
            if ($source === 'auto' && $existing) {
                Log::channel('tinkoff')->info('[auto-payout skipped: payout already exists]', [
                    'payment_id' => (int) $payment->id,
                    'existing_payout_id' => (int) $existing->id,
                    'existing_status' => (string) $existing->status,
                ]);

                return $existing;
            }

            if ($existing && (string) $existing->status !== 'REJECTED') {
                // Ручной override: если была запланирована выплата, а сейчас жмём "выплатить сейчас" —
                // запускаем её немедленно и фиксируем инициатора.
                $canRunNow = empty($existing->tinkoff_payout_payment_id)
                    && $existing->when_to_run
                    && $existing->when_to_run->gt(now())
                    && $delayUntil === null;

                if ($canRunNow) {
                    $existing->when_to_run = null;
                    $existing->source = $source ?: ($existing->source ?? 'manual');
                    if ($initiatedByUserId) {
                        $existing->initiated_by_user_id = $initiatedByUserId;
                    }
                    $existing->save();
                    return $this->runPayout($existing);
                }

                // Ручная перенастройка: если есть выплата, но пользователь жмёт "отложить" —
                // обновляем when_to_run (только если она ещё не ушла в банк).
                $canReschedule = empty($existing->tinkoff_payout_payment_id)
                    && $delayUntil
                    && ($delayUntil > now());

                if ($canReschedule) {
                    $existing->when_to_run = $delayUntil;
                    $existing->source = $source ?: ($existing->source ?? 'delayed');
                    if ($initiatedByUserId) {
                        $existing->initiated_by_user_id = $initiatedByUserId;
                    }
                    $existing->save();
                }

                return $existing;
            }

            $partner = Partner::findOrFail($payment->partner_id);
            if (!$partner->tinkoff_partner_id || !$payment->deal_id) {
                throw new \RuntimeException('Missing PartnerId or DealId');
            }

            $breakdown = $this->breakdownForPayment($payment);
            $netAmount = (int) ($breakdown['net'] ?? 0);
            $payerUserId = $this->resolvePayerUserId($payment);

            $payout = TinkoffPayout::create([
                'payment_id' => (int) $payment->id,
                'partner_id' => (int) $partner->id,
                'deal_id'    => (string) $payment->deal_id,

                // amount (как было) — сумма к выплате (net, копейки)
                'amount'     => $netAmount,
                'is_final'   => (bool) $isFinal,
                'status'     => 'INITIATED',
                'when_to_run'=> $delayUntil,

                // audit
                'source'              => $source,
                'initiated_by_user_id'=> $initiatedByUserId,
                'payer_user_id'       => $payerUserId,

                // snapshot breakdown
                'gross_amount'   => (int) ($breakdown['gross'] ?? 0),
                'bank_accept_fee'=> (int) ($breakdown['bankAccept'] ?? 0),
                'bank_payout_fee'=> (int) ($breakdown['bankPayout'] ?? 0),
                'platform_fee'   => (int) ($breakdown['platformFee'] ?? 0),
                'net_amount'     => $netAmount,
            ]);

            // Если сумма к выплате 0 — не дергаем банк, фиксируем как "отклонено".
            // Это нормально для тестовых/минимальных сумм, когда комиссии >= суммы.
            if ((int) $netAmount <= 0) {
                $payout->status = 'REJECTED';
                $payout->completed_at = now();
                $payout->payload_state = array_merge($payout->payload_state ?? [], [
                    'rejected_reason' => 'net_amount_zero',
                ]);
                $payout->save();
                return $payout;
            }

            if ($delayUntil && $delayUntil > now()) {
                return $payout; // отложенная
            }

            // Запуск в банк сразу
            return $this->runPayout($payout);
        });
    }

    public function runPayout(TinkoffPayout $payout): TinkoffPayout
    {
        $partner = Partner::findOrFail($payout->partner_id);
        $cfg = $this->resolveE2cConfig($partner->id);

        // e2c/v2/Init
        $init = [
            'TerminalKey'  => $cfg['terminal_key'],
            'PartnerId'    => $partner->tinkoff_partner_id,
            'DealId'       => $payout->deal_id,
            // Важно: банк требует OrderId (иначе ErrorCode=9999 "Поле OrderId не должно быть пустым")
            // Используем стабильный id для идемпотентности ретраев.
            'OrderId'      => 'payout-' . (int) $payout->id,
            'Amount'       => $payout->amount,
            'FinalPayout'  => $payout->is_final ? 'true' : 'false',
        ];
        $init['Token'] = TinkoffSignature::makeToken($init, $cfg['password']);
        $resInit = TinkoffApiClient::post($cfg['base_url'], '/e2c/v2/Init', $init);
        $payout->payload_init = $resInit;
        $payout->tinkoff_payout_payment_id = $resInit['PaymentId'] ?? null;
        if (empty($resInit['Success'])) {
            // Init не прошел — считаем выплату отклоненной (чтобы она не блокировала возвраты)
            $payout->status = 'REJECTED';
            $payout->completed_at = now();
        }
        $payout->save();

        // e2c/v2/Payment
        if (!empty($resInit['Success'])) {
            $paymentReq = [
                'TerminalKey' => $cfg['terminal_key'],
                'PaymentId'   => $payout->tinkoff_payout_payment_id,
            ];
            $paymentReq['Token'] = TinkoffSignature::makeToken($paymentReq, $cfg['password']);
            $resPay = TinkoffApiClient::post($cfg['base_url'], '/e2c/v2/Payment', $paymentReq);
            $payout->payload_payment = $resPay;
            $payout->status = $resPay['Status'] ?? 'CREDIT_CHECKING';
            $payout->save();

            // После Payment часто возвращается промежуточный статус.
            // Делаем мягкий polling GetState (без ожиданий), чтобы быстрее получить финальный статус.
            // Не ломает текущий воркфлоу: если банк ещё не обновил статус — останемся в промежуточном,
            // а планировщик добьёт позже.
            if (!in_array((string) $payout->status, ['COMPLETED', 'REJECTED'], true)) {
                try {
                    $this->pollState($payout);
                } catch (\Throwable $e) {
                    // не валим процесс выплаты из-за проблем polling
                }
            }
        }

        return $payout;
    }

    public function pollState(TinkoffPayout $payout): TinkoffPayout
    {
        $cfg = $this->resolveE2cConfig((int) $payout->partner_id);
        $req = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $payout->tinkoff_payout_payment_id,
        ];
        $req['Token'] = TinkoffSignature::makeToken($req, $cfg['password']);

        $res = TinkoffApiClient::post($cfg['base_url'], '/e2c/v2/GetState', $req);
        $payout->payload_state = $res;

        if (!empty($res['Status'])) {
            $old = $payout->status;
            $payout->status = $res['Status'];
            // Финальные статусы выплаты: деньги зачислены / отклонено.
            // CHECKED — успешный финал этапа Init (проверки пройдены), но НЕ зачисление денег.
            if (in_array($payout->status, ['COMPLETED', 'REJECTED'], true)) {
                $payout->completed_at = now();
            }
            $payout->save();

            if ($old !== $payout->status) {
                // Логи статусов — диагностическая таблица. Если миграция ещё не применена, не валим выплаты.
                if (Schema::hasTable('tinkoff_payout_status_logs')) {
                    DB::table('tinkoff_payout_status_logs')->insert([
                        'payout_id'   => $payout->id,
                        'from_status' => $old,
                        'to_status'   => $payout->status,
                        'payload'     => json_encode($res, JSON_UNESCAPED_UNICODE),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }

        return $payout;
    }

    protected function resolvePayerUserId(TinkoffPayment $payment): ?int
    {
        // 1) Надёжная связка: PaymentIntent.tbank_order_id == TinkoffPayment.order_id
        $uid = PaymentIntent::query()
            ->where('provider', 'tbank')
            ->where('partner_id', (int) $payment->partner_id)
            ->where('tbank_order_id', (string) $payment->order_id)
            ->value('user_id');
        if (!empty($uid)) {
            return (int) $uid;
        }

        // 2) Fallback: user_id в Data/DATA вебхука (если уже есть)
        $pl = $payment->payload ?? [];
        $wh = $pl['last_webhook'] ?? null;
        if (!is_array($wh)) {
            return null;
        }

        $data = $wh['Data'] ?? ($wh['DATA'] ?? null);
        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if (is_array($data) && !empty($data['user_id']) && ctype_digit((string) $data['user_id'])) {
            return (int) $data['user_id'];
        }

        return null;
    }

    protected function resolveE2cConfig(int $partnerId): array
    {
        $ps = PaymentSystem::where('partner_id', $partnerId)->where('name', 'tbank')->first();
        if ($ps && $ps->is_connected) {
            $s = $ps->settings;
            $isTest = (bool) $ps->test_mode;
            return [
                'terminal_key' => (string) ($s['e2c_terminal_key'] ?? ''),
                'password'     => (string) ($s['e2c_token_password'] ?? ''),
                'base_url'     => $isTest ? 'https://rest-api-test.tinkoff.ru' : 'https://securepay.tinkoff.ru',
            ];
        }

        // fallback
        $cfg = Config::get('tinkoff.e2c');
        return [
            'terminal_key' => (string) ($cfg['terminal_key'] ?? ''),
            'password'     => (string) ($cfg['password'] ?? ''),
            'base_url'     => (string) ($cfg['base_url'] ?? 'https://securepay.tinkoff.ru'),
        ];
    }

    // Формула: сумма − банковская комиссия − моя комиссия (мат. округление до копейки)
    protected function calcNetAmountForPayout(TinkoffPayment $payment): int
    {
        $gross = $payment->amount; // копейки

        // Все 3 комиссии удерживаем с партнёра (по правилам из БД):
        // - банк эквайринг (2.49% мин 3.49р по умолчанию)
        // - банк выплата (0.10% по умолчанию)
        // - платформа (настраивается)
        $rule = $this->pickCommissionRule($payment->partner_id, $payment->method);

        $bankAcceptFee = $this->calcFeeCents(
            $gross,
            (float) ($rule->acquiring_percent ?? 2.49),
            (float) ($rule->acquiring_min_fixed ?? 3.49)
        );

        $bankPayoutFee = $this->calcFeeCents(
            $gross,
            (float) ($rule->payout_percent ?? 0.10),
            (float) ($rule->payout_min_fixed ?? 0.00)
        );

        $platformFee = $this->calcFeeCents(
            $gross,
            // Приоритет: правило из БД; если не задано — дефолт платформы 2%
            (float) ($rule->platform_percent ?? $rule->percent ?? 2.00),
            (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00)
        );

        $net = $gross - $bankAcceptFee - $bankPayoutFee - $platformFee;
        return max(0, (int)$net);
    }

    protected function pickCommissionRule(int $partnerId, ?string $method): \App\Models\TinkoffCommissionRule
    {
        $rules = \App\Models\TinkoffCommissionRule::query()
            ->where('is_enabled', true)
            ->orderByRaw('partner_id is null, method is null') // приоритет: конкретный партнер/метод
            ->get();

        $chosen = $rules->first(function ($r) use ($partnerId, $method) {
            return ($r->partner_id === null || (int) $r->partner_id === (int) $partnerId)
                && ($r->method === null || (string) $r->method === (string) $method);
        });

        // если правил нет вообще — используем "пустое" правило с дефолтами
        return $chosen ?: new \App\Models\TinkoffCommissionRule([
            'acquiring_percent'   => 2.49,
            'acquiring_min_fixed' => 3.49,
            'payout_percent'      => 0.10,
            'payout_min_fixed'    => 0.00,
            'platform_percent'    => 2.00,
            'platform_min_fixed'  => 0.00,
        ]);
    }

    protected function calcFeeCents(int $amountCents, float $percent, float $minFixedRub): int
    {
        $fee = (int) round($amountCents * ($percent / 100));
        $min = (int) round($minFixedRub * 100);
        return max($fee, $min);
    }
}
