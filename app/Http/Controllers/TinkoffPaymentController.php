<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentIntent;
use App\Models\Payable;
use App\Models\PaymentSystem;
use App\Services\Tinkoff\TinkoffPaymentsService;

class TinkoffPaymentController extends Controller
{
    public function create2(Request $r, TinkoffPaymentsService $svc)
    {
        // TODO: получить partner_id/amount/method из твоей формы
        $partnerId = (int)$r->input('partner_id');
        $amount = (int)$r->input('amount'); // копейки
        $method = $r->input('method'); // card/sbp/tpay
        $payment = $svc->initPayment($partnerId, $amount, $method);

        if ($payment->payment_url) {
            return redirect()->away($payment->payment_url);
        }
        return back()->with('error', 'Не удалось инициализировать оплату');
    }

    public function create(Request $r, TinkoffPaymentsService $svc)
    {
        $partnerId = (int) app('current_partner')->id;

        // Показываем метод оплаты только если он реально настроен
        $ps = PaymentSystem::where('partner_id', $partnerId)->where('name', 'tbank')->first();
        if (!$ps || !$ps->is_connected) {
            return back()->withErrors(['tinkoff' => 'Оплата T‑Bank не подключена для текущего партнёра']);
        }
        if (empty(app('current_partner')->tinkoff_partner_id)) {
            return back()->withErrors(['tinkoff' => 'Партнёр не зарегистрирован в T‑Bank (нет ShopCode)']);
        }

        // outSum прилетает в рублях (может быть строкой) — приводим к формату "12.34" и к копейкам
        $outSumRaw = (string) $r->input('outSum', '0');
        $outSum = $this->normalizeOutSum($outSumRaw);
        if ($outSum === null) {
            return back()->withErrors(['tinkoff' => 'Некорректная сумма']);
        }
        $amountCents = (int) round(((float) $outSum) * 100);

        $user = $r->user();
        $userId = (int) $user->id;
        $userName = (string) ($user->name ?? '');

        $paymentDate = $r->filled('formatedPaymentDate')
            ? (string) $r->input('formatedPaymentDate')   // YYYY-MM-01
            : 'Клубный взнос';

        $type = $r->filled('formatedPaymentDate') ? 'monthly_fee' : 'club_fee';
        $month = null;
        $payableMeta = [];
        if ($type === 'monthly_fee') {
            $month = $paymentDate;
            $payableMeta['month'] = $paymentDate;
        }

        $payable = Payable::create([
            'partner_id' => $partnerId,
            'user_id'    => $userId,
            'type'       => $type,
            'amount'     => $outSum,
            'currency'   => 'RUB',
            'status'     => 'pending',
            'month'      => $month,
            'meta'       => $payableMeta,
        ]);

        $intent = PaymentIntent::create([
            'partner_id'   => $partnerId,
            'user_id'      => $userId,
            'payable_id'   => $payable->id,
            'provider'     => 'tbank',
            'status'       => 'pending',
            'out_sum'      => $outSum,
            'payment_date' => $paymentDate,
            'meta'         => json_encode([
                'user_name' => $userName,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        // One-stage (PayType=O) + DATA.month для трассировки
        $payment = $svc->initPayment($partnerId, $amountCents, $r->input('method') ?: 'card', [
            'month' => $month ?: null,
            'payable_id' => (string) $payable->id,
            'payment_intent_id' => (string) $intent->id,
            'user_id' => (string) $userId,
        ]);

        if (!$payment->payment_url || empty($payment->tinkoff_payment_id)) {
            return back()->withErrors(['tinkoff' => 'Не удалось инициализировать оплату T‑Bank']);
        }

        // Связываем intent с T‑Bank
        $intent->tbank_order_id = (string) $payment->order_id;
        $intent->tbank_payment_id = (int) $payment->tinkoff_payment_id;
        $intent->provider_inv_id = (int) $payment->tinkoff_payment_id;
        $intent->save();

        return redirect()->away($payment->payment_url);
    }

    /**
     * Нормализуем сумму (рубли) до формата 0.00, округляя до копейки.
     */
    private function normalizeOutSum(string $value): ?string
    {
        $v = trim(str_replace(',', '.', $value));
        if ($v === '') return null;
        if (!preg_match('/^\d+(\.\d{1,6})?$/', $v)) return null;

        $a = $v;
        $b = '';
        if (str_contains($v, '.')) {
            [$a, $b] = explode('.', $v, 2);
        }

        $a = ltrim($a, '0');
        if ($a === '') $a = '0';

        $b = str_pad($b, 6, '0');
        $cents = (int) substr($b, 0, 2);
        $third = (int) substr($b, 2, 1);

        if ($third >= 5) {
            $cents++;
            if ($cents >= 100) {
                $cents = 0;
                $a = (string) ((int) $a + 1);
            }
        }

        return $a . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }



    public function success($order)
    {
        // TODO: UX «успешно»
        return view('tinkoff.success', ['order' => $order]);
    }

    public function fail($order)
    {
        // TODO: UX «ошибка»
        return view('tinkoff.fail', ['order' => $order]);
    }
}
