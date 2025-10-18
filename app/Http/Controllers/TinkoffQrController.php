<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Services\Tinkoff\TinkoffPaymentsService;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffSignature;
use App\Models\TinkoffPayment;

class TinkoffQrController extends Controller
{
    // 1) Инициализация платежа и редирект на QR-страницу
    public function init(Request $r, TinkoffPaymentsService $svc)
    {
        $partnerId  = (int) $r->input('partner_id');
        $outSumRub  = (float) str_replace(',', '.', $r->input('outSum', 0));
        $amountCents= (int) round($outSumRub * 100);
        $method     = 'sbp'; // для аналитики комиссий у нас

        $payment = $svc->initPayment($partnerId, $amountCents, $method);
        if (!$payment->tinkoff_payment_id) {
            return back()->withErrors(['tinkoff' => 'Не удалось инициализировать оплату']);
        }
        return redirect()->route('tinkoff.qr', $payment->tinkoff_payment_id);
    }

    // 2) Страница с QR
    public function show($paymentId)
    {
        return view('tinkoff.qr', ['paymentId' => $paymentId]);
    }

    // 3) AJAX — получить картинку QR
    public function getQr($paymentId)
    {
        $cfg = Config::get('tinkoff.payment');
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $paymentId,
            // 'DataType'  => 'PAYLOAD', // обычно не нужно, банк возвращает base64 PNG в Data
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetQr', $payload);
        return response()->json($res);
    }
}
