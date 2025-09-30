<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TinkoffPayment;
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

    public function create(Request $r, \App\Services\Tinkoff\TinkoffPaymentsService $svc)
    {
        $partnerId = (int) $r->input('partner_id');
        // outSum прилетает в рублях (может быть строкой) — приводим к копейкам
        $outSumRub = (float) str_replace(',', '.', $r->input('outSum', 0));
        $amountCents = (int) round($outSumRub * 100);

        $method = $r->input('method'); // card/sbp/tpay или null
        $period = $r->input('paymentDate'); // напр. "сентябрь 2025"
        $userName = $r->input('userName'); // для описания (опционально)

        // если хочешь, передай описание в сервис (можно сохранять как payload, не в API)
        // сейчас описание жёстко "Оплата абонплаты" внутри сервиса — ок для MVP

        $payment = $svc->initPayment($partnerId, $amountCents, $method);


        \Log::channel('tinkoff')->debug('[REDIRECT TO BANK]', [
            'payment_url' => $payment->payment_url,
            'user_agent'  => request()->userAgent(),
            'ip'          => request()->ip(),
        ]);


        if ($payment->payment_url) {
            return redirect()->away($payment->payment_url);
        }
        return back()->withErrors(['tinkoff' => 'Не удалось инициализировать оплату Тинькофф']);
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
