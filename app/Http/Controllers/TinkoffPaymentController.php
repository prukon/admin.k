<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tinkoff\CreatePaymentRequest;
use App\Http\Requests\Tinkoff\CreateSbpPaymentRequest;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\UserPeriodPrice;
use App\Services\Payments\PaymentIntentClientContext;
use App\Services\Payments\UserPriceMonthlyFeePaymentResolver;
use App\Services\Tinkoff\TinkoffPaymentsService;
use App\Support\Payments\PaymentOutSumNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TinkoffPaymentController extends Controller
{
    public function create2(Request $r, TinkoffPaymentsService $svc)
    {
        // TODO: получить partner_id/amount/method из твоей формы
        $partnerId = (int) $r->input('partner_id');
        $amount = (int) $r->input('amount'); // копейки
        $method = $r->input('method'); // card/sbp/tpay
        $payment = $svc->initPayment($partnerId, $amount, $method);

        if ($payment->payment_url) {
            return redirect()->away($payment->payment_url);
        }

        return back()->with('error', 'Не удалось инициализировать оплату');
    }

    public function create(CreatePaymentRequest $r, TinkoffPaymentsService $svc)
    {
        $partnerId = (int) app('current_partner')->id;

        // Показываем метод оплаты только если он реально настроен
        $ps = PaymentSystem::where('partner_id', $partnerId)->where('name', 'tbank')->first();
        if (! $ps || ! $ps->is_connected) {
            return back()->withErrors(['tinkoff' => 'Оплата T‑Bank не подключена для текущего партнёра']);
        }
        if (empty(app('current_partner')->tinkoff_partner_id)) {
            return back()->withErrors(['tinkoff' => 'Партнёр не зарегистрирован в T‑Bank (нет ShopCode)']);
        }

        $user = $r->user();
        $userId = (int) $user->id;
        $userName = (string) ($user->name ?? '');

        $paymentKind = (string) $r->input('payment_kind', '');
        $userPeriodPriceId = $r->filled('abonement_id') ? (int) $r->input('abonement_id') : null;

        $rawFmt = $r->input('formatedPaymentDate');
        $hasMonthly = $r->filled('formatedPaymentDate')
            && is_string($rawFmt)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFmt);

        if ($paymentKind === 'abonement') {
            $upp = null;
            if ($userPeriodPriceId !== null && $userPeriodPriceId > 0) {
                $upp = UserPeriodPrice::query()
                    ->whereKey($userPeriodPriceId)
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->first();
            }
            if (!$upp) {
                return back()->withErrors(['tinkoff' => 'Абонемент не найден']);
            }
            if ((bool) $upp->effective_is_paid) {
                return back()->withErrors(['tinkoff' => 'Абонемент уже оплачен']);
            }

            $outSum = number_format((float) $upp->amount, 2, '.', '');
            $paymentDate = (string) $r->input('paymentDate', 'Абонемент');
            $hasMonthly = false;
        } elseif ($hasMonthly) {
            $resolved = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
                $userId,
                (int) $partnerId,
                $rawFmt
            );
            $outSum = $resolved['out_sum'];
            $paymentDate = $resolved['month_first_day'];
        } else {
            $outSumRaw = (string) $r->input('outSum', '0');
            $outSum = PaymentOutSumNormalizer::normalize($outSumRaw);
            if ($outSum === null) {
                return back()->withErrors(['tinkoff' => 'Некорректная сумма']);
            }
            $paymentDate = 'Клубный взнос';
        }

        $amountCents = (int) round(((float) $outSum) * 100);

        $type = $paymentKind === 'abonement'
            ? 'abonement_fee_period'
            : ($hasMonthly ? 'monthly_fee' : 'club_fee');
        $month = null;
        $payableMeta = [];
        if ($type === 'monthly_fee') {
            $month = $paymentDate;
            $payableMeta['month'] = $paymentDate;
        } elseif ($type === 'abonement_fee_period') {
            $payableMeta['user_period_price_id'] = $userPeriodPriceId;
        }

        $payable = Payable::create([
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'type' => $type,
            'amount' => $outSum,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $month,
            'meta' => $payableMeta,
        ]);

        $bankMethod = (string) ($r->input('method') ?: 'card');
        $intentPaymentMethod = match ($bankMethod) {
            'sbp' => 'sbp_qr',
            'tpay' => 'tpay',
            default => 'card',
        };

        $intent = PaymentIntent::create(array_merge([
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'payment_method' => $intentPaymentMethod,
            'status' => 'pending',
            'out_sum' => $outSum,
            'payment_date' => $paymentDate,
            'meta' => json_encode([
                'user_name' => $userName,
            ], JSON_UNESCAPED_UNICODE),
        ], PaymentIntentClientContext::fromRequest($r)));

        // One-stage (PayType=O) + DATA.month для трассировки
        $payment = $svc->initPayment($partnerId, $amountCents, $bankMethod, [
            'month' => $month ?: null,
            'payable_id' => (string) $payable->id,
            'payment_intent_id' => (string) $intent->id,
            'user_id' => (string) $userId,
            'payment_kind' => $paymentKind !== '' ? $paymentKind : null,
            'user_period_price_id' => $userPeriodPriceId ? (string) $userPeriodPriceId : null,
        ]);

        if (! $payment->payment_url || empty($payment->tinkoff_payment_id)) {
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
     * Оплата учеником через T‑Bank СБП (QR).
     * Flow: Init → показываем QR → ждём CONFIRMED (webhook/проверка статуса) → success.
     */
    public function createSbp(CreateSbpPaymentRequest $r, TinkoffPaymentsService $svc)
    {
        $partnerId = (int) app('current_partner')->id;

        // Показываем метод оплаты только если он реально настроен
        $ps = PaymentSystem::where('partner_id', $partnerId)->where('name', 'tbank')->first();
        if (! $ps || ! $ps->is_connected) {
            return back()->withErrors(['tinkoff' => 'Оплата T‑Bank не подключена для текущего партнёра']);
        }
        if (empty(app('current_partner')->tinkoff_partner_id)) {
            return back()->withErrors(['tinkoff' => 'Партнёр не зарегистрирован в T‑Bank (нет ShopCode)']);
        }

        $user = $r->user();
        $userId = (int) $user->id;
        $userName = (string) ($user->name ?? '');

        $paymentKind = (string) $r->input('payment_kind', '');
        $userPeriodPriceId = $r->filled('abonement_id') ? (int) $r->input('abonement_id') : null;

        $rawFmt = $r->input('formatedPaymentDate');
        $hasMonthly = $r->filled('formatedPaymentDate')
            && is_string($rawFmt)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFmt);

        if ($paymentKind === 'abonement') {
            $upp = null;
            if ($userPeriodPriceId !== null && $userPeriodPriceId > 0) {
                $upp = UserPeriodPrice::query()
                    ->whereKey($userPeriodPriceId)
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->first();
            }
            if (!$upp) {
                return back()->withErrors(['tinkoff' => 'Абонемент не найден']);
            }
            if ((bool) $upp->effective_is_paid) {
                return back()->withErrors(['tinkoff' => 'Абонемент уже оплачен']);
            }

            $outSum = number_format((float) $upp->amount, 2, '.', '');
            $paymentDate = (string) $r->input('paymentDate', 'Абонемент');
            $hasMonthly = false;
        } elseif ($hasMonthly) {
            $resolved = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
                $userId,
                (int) $partnerId,
                $rawFmt
            );
            $outSum = $resolved['out_sum'];
            $paymentDate = $resolved['month_first_day'];
        } else {
            $outSumRaw = (string) $r->input('outSum', '0');
            $outSum = PaymentOutSumNormalizer::normalize($outSumRaw);
            if ($outSum === null) {
                return back()->withErrors(['tinkoff' => 'Некорректная сумма']);
            }
            $paymentDate = 'Клубный взнос';
        }

        $amountCents = (int) round(((float) $outSum) * 100);
        // Ограничение банка для QR (СБП): сумма от 1 000 коп. (10 ₽) до 100 000 000 коп.
        if ($amountCents < 1000 || $amountCents > 100000000) {
            return back()->withErrors(['tinkoff' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.']);
        }

        $type = $paymentKind === 'abonement'
            ? 'abonement_fee_period'
            : ($hasMonthly ? 'monthly_fee' : 'club_fee');
        $month = null;
        $payableMeta = [];
        if ($type === 'monthly_fee') {
            $month = $paymentDate;
            $payableMeta['month'] = $paymentDate;
        } elseif ($type === 'abonement_fee_period') {
            $payableMeta['user_period_price_id'] = $userPeriodPriceId;
        }

        $payable = Payable::create([
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'type' => $type,
            'amount' => $outSum,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $month,
            'meta' => $payableMeta,
        ]);

        $intent = PaymentIntent::create(array_merge([
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'payment_method' => 'sbp_qr',
            'status' => 'pending',
            'out_sum' => $outSum,
            'payment_date' => $paymentDate,
            'meta' => json_encode([
                'user_name' => $userName,
                'method' => 'sbp',
            ], JSON_UNESCAPED_UNICODE),
        ], PaymentIntentClientContext::fromRequest($r)));

        $payment = $svc->initPayment($partnerId, $amountCents, 'sbp', [
            'month' => $month ?: null,
            'payable_id' => (string) $payable->id,
            'payment_intent_id' => (string) $intent->id,
            'user_id' => (string) $userId,
            'payment_kind' => $paymentKind !== '' ? $paymentKind : null,
            'user_period_price_id' => $userPeriodPriceId ? (string) $userPeriodPriceId : null,
        ]);

        if (empty($payment->tinkoff_payment_id)) {
            return back()->withErrors(['tinkoff' => 'Не удалось инициализировать оплату T‑Bank (СБП)']);
        }

        // Связываем intent с T‑Bank
        $intent->tbank_order_id = (string) $payment->order_id;
        $intent->tbank_payment_id = (int) $payment->tinkoff_payment_id;
        $intent->provider_inv_id = (int) $payment->tinkoff_payment_id;
        $intent->save();

        // Переходим на страницу QR
        return redirect()->route('tinkoff.qr', $payment->tinkoff_payment_id);
    }

    public function success($order)
    {
        Log::channel('tinkoff')->info('tbank.return', [
            'event' => 'success',
            'order' => (string) $order,
            'auth' => auth()->check(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return view('tinkoff.success', $this->tinkoffReturnViewData($order));
    }

    public function fail($order)
    {
        Log::channel('tinkoff')->info('tbank.return', [
            'event' => 'fail',
            'order' => (string) $order,
            'auth' => auth()->check(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return view('tinkoff.fail', $this->tinkoffReturnViewData($order));
    }

    /**
     * @return array{order: mixed, authenticated: bool, cabinetUrl: string, homeUrl: string}
     */
    private function tinkoffReturnViewData($order): array
    {
        return [
            'order' => $order,
            'authenticated' => auth()->check(),
            'cabinetUrl' => route('dashboard'),
            'homeUrl' => url('/'),
        ];
    }
}
