<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentIntent;
use App\Services\Tinkoff\TbankAcquiringTerminalConfig;
use App\Services\Tinkoff\TbankTerminalConfig;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffSignature;
use App\Models\TinkoffPayment;

class TinkoffQrController extends Controller
{
    // 1) Инициализация платежа и редирект на QR-страницу
    // public function init(QrInitRequest $r, TinkoffPaymentsService $svc)
    // {
    //     // Безопасность: partner_id только из текущего партнёра (не из запроса — иначе подмена на чужого)
    //     $partnerId  = (int) app('current_partner')->id;
    //     $outSumRub  = (float) str_replace(',', '.', $r->input('outSum', 0));
    //     $amountCents= (int) round($outSumRub * 100);
    //     $method     = 'sbp'; // для аналитики комиссий у нас

    //     // Ограничение банка для QR (СБП): сумма от 1 000 коп. (10 ₽) до 100 000 000 коп.
    //     if ($amountCents < 1000 || $amountCents > 100000000) {
    //         return back()->withErrors(['tinkoff' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.']);
    //     }

    //     $payment = $svc->initPayment($partnerId, $amountCents, $method);
    //     if (!$payment->tinkoff_payment_id) {
    //         return back()->withErrors(['tinkoff' => 'Не удалось инициализировать оплату']);
    //     }
    //     return redirect()->route('tinkoff.qr', $payment->tinkoff_payment_id);
    // }

    // 2) Страница с QR
    public function show(Request $request, $paymentId)
    {
        $tp = $this->ownedPaymentOrAbort($paymentId);
        $orderId = $tp->order_id;

        $intent = PaymentIntent::query()
            ->where('partner_id', (int) $tp->partner_id)
            ->where('tbank_payment_id', (int) $paymentId)
            ->first();

        $backOutSum = null;
        $backPaymentDate = null;
        $backFormatedPaymentDate = null;
        if ($intent) {
            $backOutSum = \App\Support\Money::fromCents((int) ($intent->out_sum_cents ?? 0));
            $pd = (string) $intent->payment_date;
            if ($pd !== '' && $pd !== 'Клубный взнос' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd)) {
                $backFormatedPaymentDate = $pd;
                $backPaymentDate = self::russianMonthYearFromYmFirst($pd);
            }
        }

        $amountKop = (int) $tp->amount;
        $amountRubFormatted = number_format($amountKop / 100, 2, '.', '');
        $isAcquiring = $this->isAcquiring($tp);
        $successUrl = $this->successUrlFor($tp, $orderId);
        $scope = $this->scopeFor($tp);

        return view('tinkoff.qr', [
            'paymentId' => $paymentId,
            'amountRubFormatted' => $amountRubFormatted,
            'successUrl' => $successUrl,
            'stateUrl' => url('/tinkoff/qr/' . $paymentId . '/state'),
            'qrUrl' => url('/tinkoff/qr/' . $paymentId . '/json'),
            'nspkPayloadUrl' => url('/tinkoff/qr/' . $paymentId . '/payload'),
            'isMobileClient' => self::isLikelyMobileUserAgent($request->userAgent()),
            'backOutSum' => $backOutSum,
            'backPaymentDate' => $backPaymentDate,
            'backFormatedPaymentDate' => $backFormatedPaymentDate,
            'canReturnToPaymentChoice' => $intent !== null && ! $isAcquiring,
            'acquiringBackUrl' => $scope === 'partner_wallet_topup'
                ? url('/partner-wallet')
                : ($scope === 'partner_service_payment' ? url('/partner-payment/recharge') : null),
            'acquiringBackLabel' => $scope === 'partner_wallet_topup'
                ? 'К кошельку'
                : ($scope === 'partner_service_payment' ? 'К оплате сервиса' : null),
        ]);
    }

    /**
     * Грубая эвристика для первичной разметки «мобильный СБП».
     * Окончательный выбор также делается в браузере по ширине экрана (см. tinkoff/qr.blade.php).
     */
    private static function isLikelyMobileUserAgent(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        return (bool) preg_match(
            '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile\b|CriOS|FxiOS/i',
            $userAgent
        );
    }

    /** Обратное к формату «Месяц Год» для POST на страницу выбора способа оплаты. */
    private static function russianMonthYearFromYmFirst(string $ymd): string
    {
        $months = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
        ];
        try {
            $d = \Carbon\Carbon::createFromFormat('Y-m-d', $ymd);
        } catch (\Throwable) {
            return '';
        }
        $m = (int) $d->month;

        return ($months[$m] ?? '') . ' ' . $d->year;
    }

    // 3) AJAX — получить картинку QR (GetQr DataType=IMAGE)
    public function getQr($paymentId)
    {
        return $this->getQrByDataType($paymentId, 'IMAGE');
    }

    /**
     * AJAX — ссылка НСПК для оплаты с того же телефона (GetQr DataType=PAYLOAD).
     * Ответ банка отдаём как есть; в Data обычно URL вида https://qr.nspk.ru/…
     */
    public function getQrPayload($paymentId)
    {
        return $this->getQrByDataType($paymentId, 'PAYLOAD');
    }

    private function getQrByDataType(string $paymentId, string $dataType)
    {
        $tp = $this->ownedPaymentOrAbort($paymentId, json: true);

        $cfg = $this->resolvePaymentConfig($tp);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $paymentId,
            // IMAGE — SVG QR в Data. PAYLOAD — ссылка qr.nspk.ru (страница НСПК), для мобильного СБП.
            'DataType'    => $dataType,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetQr', $payload);

        return response()->json($res);
    }

    // 4) AJAX — получить состояние платежа (GetState) для QR-страницы
    public function state($paymentId)
    {
        $tp = $this->ownedPaymentOrAbort($paymentId, json: true);

        $cfg = $this->resolvePaymentConfig($tp);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId'   => $paymentId,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);
        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetState', $payload);

        return response()->json($res);
    }

    /**
     * @return array{terminal_key: string, password: string, base_url: string}
     */
    private function resolvePaymentConfig(TinkoffPayment $tp): array
    {
        if ($this->isAcquiring($tp)) {
            return TbankAcquiringTerminalConfig::paymentConfig();
        }

        return TbankTerminalConfig::paymentConfig();
    }

    private function isAcquiring(TinkoffPayment $tp): bool
    {
        return (string) ($tp->channel ?? TinkoffPayment::CHANNEL_MULTISPLIT) === TinkoffPayment::CHANNEL_ACQUIRING;
    }

    private function ownedPaymentOrAbort(string $paymentId, bool $json = false): TinkoffPayment
    {
        $tp = TinkoffPayment::where('tinkoff_payment_id', (string) $paymentId)->first();
        if (! $tp || (int) $tp->partner_id !== (int) app('current_partner')->id) {
            if ($json) {
                abort(response()->json(['Success' => false, 'Message' => 'Payment not found'], 404));
            }
            abort(404);
        }

        $user = auth()->user();
        if ($this->isAcquiring($tp)) {
            if (! $user || (! $user->can('partnerWallet.view') && ! $user->can('servicePayments.view'))) {
                abort(403);
            }

            return $tp;
        }

        if (! $user || ! $user->can('payment.method.tbankSBP')) {
            abort(403);
        }

        return $tp;
    }

    private function successUrlFor(TinkoffPayment $tp, ?string $orderId): string
    {
        $fromPayload = is_array($tp->payload) ? (string) ($tp->payload['success_url'] ?? '') : '';
        if ($fromPayload !== '') {
            return $fromPayload;
        }

        return $orderId ? url('/payments/tinkoff/' . $orderId . '/success') : url('/payment/success');
    }

    private function scopeFor(TinkoffPayment $tp): string
    {
        $init = is_array($tp->payload) ? ($tp->payload['init_data'] ?? []) : [];
        if (is_array($init) && isset($init['scope'])) {
            return (string) $init['scope'];
        }

        return '';
    }
}
