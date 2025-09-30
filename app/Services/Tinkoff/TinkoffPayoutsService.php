<?php

namespace App\Services\Tinkoff;

use App\Models\Partner;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use Illuminate\Support\Facades\Config;

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

        if ($delayUntil && $delayUntil > now()) {
            return $payout; // отложенная
        }

        return $this->runPayout($payout);
    }

    public function runPayout(TinkoffPayout $payout): TinkoffPayout
    {
        $partner = Partner::findOrFail($payout->partner_id);
        $cfg = Config::get('tinkoff.e2c');

        // e2c/v2/Init
        $init = [
            'TerminalKey'  => $cfg['terminal_key'],
            'PartnerId'    => $partner->tinkoff_partner_id,
            'DealId'       => $payout->deal_id,
            'Amount'       => $payout->amount,
            'FinalPayout'  => $payout->is_final ? 'true' : 'false',
        ];
        $init['Token'] = TinkoffSignature::makeToken($init, $cfg['password']);
        $resInit = TinkoffApiClient::post($cfg['base_url'], '/e2c/v2/Init', $init);
        $payout->payload_init = $resInit;
        $payout->tinkoff_payout_payment_id = $resInit['PaymentId'] ?? null;
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
        $cfg = Config::get('tinkoff.e2c');
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
                \DB::table('tinkoff_payout_status_logs')->insert([
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

    // Формула: сумма − банковская комиссия − моя комиссия (мат. округление до копейки)
    protected function calcNetAmountForPayout(TinkoffPayment $payment): int
    {
        $gross = $payment->amount; // копейки

        // Банк за прием (2.49% мин 3.49р)
        $acqCfg = Config::get('tinkoff.tariffs.acquiring');
        $bankAcceptFee = max(
            round($gross * ($acqCfg['percent']/100)),
            (int) round($acqCfg['min_fixed'] * 100)
        );

        // Банк за выплату (юрик по умолчанию 0.1%) — можно ветвить по типу получателя/методу, если нужно
        $payoutTariff = Config::get('tinkoff.tariffs.payouts.jur');
        $bankPayoutFee = max(
            round($gross * ($payoutTariff['percent']/100)),
            (int) round($payoutTariff['min_fixed'] * 100)
        );

        // Твоя комиссия по правилам (пример: ищем самое конкретное правило)
        $myFee = $this->calcMyCommission($payment->partner_id, $payment->method, $gross);

        $net = $gross - $bankAcceptFee - $bankPayoutFee - $myFee;
        return max(0, (int)$net);
    }

    protected function calcMyCommission(int $partnerId, ?string $method, int $amountCents): int
    {
        $q = \App\Models\TinkoffCommissionRule::query()
            ->where('is_enabled', true)
            ->orderByRaw('partner_id is null, method is null'); // приоритет: конкретный партнер/метод

        $rules = $q->get();
        $chosen = $rules->first(function($r) use ($partnerId, $method) {
            return ($r->partner_id === null || $r->partner_id == $partnerId)
                && ($r->method === null || $r->method === $method);
        });

        if (!$chosen) return 0;

        $fee = round($amountCents * ($chosen->percent/100));
        $min = (int) round($chosen->min_fixed * 100);

        return max($fee, $min);
    }
}
