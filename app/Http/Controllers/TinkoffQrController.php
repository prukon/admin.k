<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\PaymentSystem;
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

        // Ограничение банка для QR (СБП): сумма от 1 000 коп. (10 ₽) до 100 000 000 коп.
        if ($amountCents < 1000 || $amountCents > 100000000) {
            return back()->withErrors(['tinkoff' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.']);
        }

        $payment = $svc->initPayment($partnerId, $amountCents, $method);
        if (!$payment->tinkoff_payment_id) {
            return back()->withErrors(['tinkoff' => 'Не удалось инициализировать оплату']);
        }
        return redirect()->route('tinkoff.qr', $payment->tinkoff_payment_id);
    }

    // 2) Страница с QR
    public function show($paymentId)
    {
        $tp = TinkoffPayment::where('tinkoff_payment_id', (string) $paymentId)->first();
        $orderId = $tp?->order_id;

        return view('tinkoff.qr', [
            'paymentId' => $paymentId,
            'successUrl' => $orderId ? url('/payments/tinkoff/' . $orderId . '/success') : url('/payment/success'),
            'stateUrl' => url('/tinkoff/qr/' . $paymentId . '/state'),
            'qrUrl' => url('/tinkoff/qr/' . $paymentId . '/json'),
        ]);
    }

    // 3) AJAX — получить картинку QR
    public function getQr($paymentId)
    {
        $tp = TinkoffPayment::where('tinkoff_payment_id', (string) $paymentId)->first();
        if (!$tp) {
            return response()->json(['Success' => false, 'Message' => 'Payment not found'], 404);
        }

        $cfg = $this->resolvePaymentConfig((int) $tp->partner_id);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $paymentId,
            // 'DataType'  => 'PAYLOAD', // обычно не нужно, банк возвращает base64 PNG в Data
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetQr', $payload);
        return response()->json($res);
    }

    // 4) AJAX — получить состояние платежа (GetState) для QR-страницы
    public function state($paymentId)
    {
        $tp = TinkoffPayment::where('tinkoff_payment_id', (string) $paymentId)->first();
        if (!$tp) {
            return response()->json(['Success' => false, 'Message' => 'Payment not found'], 404);
        }

        $cfg = $this->resolvePaymentConfig((int) $tp->partner_id);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $paymentId,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);
        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetState', $payload);

        return response()->json($res);
    }

    private function resolvePaymentConfig(int $partnerId): array
    {
        $ps = PaymentSystem::where('partner_id', $partnerId)->where('name', 'tbank')->first();
        if ($ps && $ps->is_connected) {
            $s = $ps->settings;
            $isTest = (bool) $ps->test_mode;

            return [
                'terminal_key' => (string) ($s['terminal_key'] ?? ''),
                'password'     => (string) ($s['token_password'] ?? ''),
                'base_url'     => $isTest ? 'https://rest-api-test.tinkoff.ru' : 'https://securepay.tinkoff.ru',
            ];
        }

        $cfg = Config::get('tinkoff.payment');
        return [
            'terminal_key' => (string) ($cfg['terminal_key'] ?? ''),
            'password'     => (string) ($cfg['password'] ?? ''),
            'base_url'     => (string) ($cfg['base_url'] ?? 'https://securepay.tinkoff.ru'),
        ];
    }
}
