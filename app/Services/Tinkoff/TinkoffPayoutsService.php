<?php

namespace App\Services\Tinkoff;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TinkoffPayoutsService
{
    public function createAndRunPayout(TinkoffPayment $payment, bool $isFinal = true, ?\DateTimeInterface $delayUntil = null): TinkoffPayout
    {
        $partner = Partner::findOrFail($payment->partner_id);
        if (!$partner->tinkoff_partner_id || !$payment->deal_id) {
            throw new \RuntimeException('Missing PartnerId or DealId');
        }

        // Расчет суммы к выплате: сумма − банковская комиссия − моя комиссия
        $netAmount = $this->calcNetAmountForPayout($payment);

        $payout = TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $partner->id,
            'deal_id'    => $payment->deal_id,
            'amount'     => $netAmount,
            'is_final'   => $isFinal,
            'status'     => $delayUntil ? 'INITIATED' : 'INITIATED',
            'when_to_run'=> $delayUntil,
        ]);

        // Если сумма к выплате 0 — не дергаем банк, фиксируем как "отклонено".
        // Это нормально для тестовых/минимальных сумм, когда комиссии >= суммы.
        if ((int) $netAmount <= 0) {
            $payout->status = 'REJECTED';
            $payout->completed_at = now();
            $payout->save();
            return $payout;
        }

        if ($delayUntil && $delayUntil > now()) {
            return $payout; // отложенная
        }

        return $this->runPayout($payout);
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
            if (in_array($payout->status, ['COMPLETED','REJECTED','CHECKED'])) {
                $payout->completed_at = now();
            }
            $payout->save();

            if ($old !== $payout->status) {
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

        return $payout;
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
            (float) ($rule->platform_percent ?? $rule->percent ?? 0.00),
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
            'platform_percent'    => 0.00,
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
