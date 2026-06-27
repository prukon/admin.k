<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tinkoff\CreatePaymentRequest;
use App\Http\Requests\Tinkoff\CreateSbpPaymentRequest;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\UserCustomPayment;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentIntentClientContext;
use App\Models\Team;
use App\Services\Payments\UserLessonPackageFeePaymentResolver;
use App\Services\Payments\UserPriceMonthlyFeePaymentResolver;
use App\Services\Tinkoff\TbankTerminalConfig;
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

    public function create(CreatePaymentRequest $r, TinkoffPaymentsService $svc, PaymentService $paymentService)
    {
        $partnerId = (int) app('current_partner')->id;
        $partner = app('current_partner');

        // Показываем метод оплаты только если он реально настроен
        if (! TbankTerminalConfig::isGloballyActive()) {
            return back()->withErrors(['tinkoff' => 'Оплата T‑Bank не подключена на платформе']);
        }

        $user = $r->user();
        $userId = (int) $user->id;
        $userName = (string) ($user->name ?? '');

        $paymentKind = (string) $r->input('payment_kind', '');
        $userPeriodPriceId = $r->filled('custom_payment_id') ? (int) $r->input('custom_payment_id') : null;
        $userLessonPackageId = $r->filled('user_lesson_package_id') ? (int) $r->input('user_lesson_package_id') : null;

        $rawFmt = $r->input('formatedPaymentDate');
        $hasMonthly = $r->filled('formatedPaymentDate')
            && is_string($rawFmt)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFmt);
        $monthlyTeamId = null;

        if ($paymentKind === 'custom_payment') {
            $upp = null;
            if ($userPeriodPriceId !== null && $userPeriodPriceId > 0) {
                $upp = UserCustomPayment::query()
                    ->whereKey($userPeriodPriceId)
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->first();
            }
            if (!$upp) {
                return back()->withErrors(['tinkoff' => 'Дополнительный платеж не найден']);
            }
            if ((bool) $upp->effective_is_paid) {
                return back()->withErrors(['tinkoff' => 'Дополнительный платеж уже оплачен']);
            }

            $outSum = number_format((float) $upp->amount, 2, '.', '');
            $paymentDate = (string) $r->input('paymentDate', 'Дополнительный платеж');
            $hasMonthly = false;
        } elseif ($paymentKind === 'lesson_package') {
            // Amount Init — из fee_amount; outSum из формы не используется.
            $resolvedLp = app(UserLessonPackageFeePaymentResolver::class)->resolveOrAbort(
                $userId,
                (int) $partnerId,
                $userLessonPackageId ?? 0,
            );
            $outSum = $resolvedLp['out_sum'];
            $paymentDate = $resolvedLp['payment_label'];
            $hasMonthly = false;
        } elseif ($hasMonthly) {
            $teamIdParam = $r->filled('team_id') ? (int) $r->input('team_id') : null;
            $resolved = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
                $userId,
                (int) $partnerId,
                $rawFmt,
                $teamIdParam
            );
            $outSum = $resolved['out_sum'];
            $paymentDate = $resolved['month_first_day'];
            $monthlyTeamId = $resolved['team_id'];
        } else {
            $outSumRaw = (string) $r->input('outSum', '0');
            $outSum = PaymentOutSumNormalizer::normalize($outSumRaw);
            if ($outSum === null) {
                return back()->withErrors(['tinkoff' => 'Некорректная сумма']);
            }
            $paymentDate = 'Клубный взнос';
        }

        $monthlyTeam = ($monthlyTeamId ?? 0) > 0
            ? Team::query()->whereKey($monthlyTeamId)->where('partner_id', $partnerId)->first()
            : null;

        if (! $paymentService->isTbankAvailable($partner, $monthlyTeam)) {
            return back()->withErrors(['tinkoff' => 'Партнёр не зарегистрирован в T‑Bank (нет ShopCode)']);
        }

        $amountCents = (int) round(((float) $outSum) * 100);

        $type = $paymentKind === 'custom_payment'
            ? 'custom_payment_fee'
            : ($paymentKind === 'lesson_package'
                ? 'lesson_package_fee'
                : ($hasMonthly ? 'monthly_fee' : 'club_fee'));
        $month = null;
        $payableMeta = [];
        if ($type === 'monthly_fee') {
            $month = $paymentDate;
            $payableMeta['month'] = $paymentDate;
            if (! empty($monthlyTeamId)) {
                $payableMeta['team_id'] = (int) $monthlyTeamId;
            }
        } elseif ($type === 'custom_payment_fee') {
            $payableMeta['user_period_price_id'] = $userPeriodPriceId;
        } elseif ($type === 'lesson_package_fee') {
            $payableMeta['user_lesson_package_id'] = $userLessonPackageId;
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
            'meta' => json_encode($this->buildPaymentIntentMeta($userName, $monthlyTeamId), JSON_UNESCAPED_UNICODE),
        ], PaymentIntentClientContext::fromRequest($r)));

        // One-stage (PayType=O) + DATA.month для трассировки
        $payment = $svc->initPayment($partnerId, $amountCents, $bankMethod, $this->buildTbankInitData(
            $month,
            $payable->id,
            $intent->id,
            $userId,
            $paymentKind,
            $userPeriodPriceId,
            $userLessonPackageId,
            $monthlyTeamId,
        ));

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
    public function createSbp(CreateSbpPaymentRequest $r, TinkoffPaymentsService $svc, PaymentService $paymentService)
    {
        $partnerId = (int) app('current_partner')->id;
        $partner = app('current_partner');

        // Показываем метод оплаты только если он реально настроен
        if (! TbankTerminalConfig::isGloballyActive()) {
            return back()->withErrors(['tinkoff' => 'Оплата T‑Bank не подключена на платформе']);
        }

        $user = $r->user();
        $userId = (int) $user->id;
        $userName = (string) ($user->name ?? '');

        $paymentKind = (string) $r->input('payment_kind', '');
        $userPeriodPriceId = $r->filled('custom_payment_id') ? (int) $r->input('custom_payment_id') : null;
        $userLessonPackageId = $r->filled('user_lesson_package_id') ? (int) $r->input('user_lesson_package_id') : null;

        $rawFmt = $r->input('formatedPaymentDate');
        $hasMonthly = $r->filled('formatedPaymentDate')
            && is_string($rawFmt)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFmt);
        $monthlyTeamId = null;

        if ($paymentKind === 'custom_payment') {
            $upp = null;
            if ($userPeriodPriceId !== null && $userPeriodPriceId > 0) {
                $upp = UserCustomPayment::query()
                    ->whereKey($userPeriodPriceId)
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->first();
            }
            if (!$upp) {
                return back()->withErrors(['tinkoff' => 'Дополнительный платеж не найден']);
            }
            if ((bool) $upp->effective_is_paid) {
                return back()->withErrors(['tinkoff' => 'Дополнительный платеж уже оплачен']);
            }

            $outSum = number_format((float) $upp->amount, 2, '.', '');
            $paymentDate = (string) $r->input('paymentDate', 'Дополнительный платеж');
            $hasMonthly = false;
        } elseif ($paymentKind === 'lesson_package') {
            // Amount Init — из fee_amount; outSum из формы не используется.
            $resolvedLp = app(UserLessonPackageFeePaymentResolver::class)->resolveOrAbort(
                $userId,
                (int) $partnerId,
                $userLessonPackageId ?? 0,
            );
            $outSum = $resolvedLp['out_sum'];
            $paymentDate = $resolvedLp['payment_label'];
            $hasMonthly = false;
        } elseif ($hasMonthly) {
            $teamIdParam = $r->filled('team_id') ? (int) $r->input('team_id') : null;
            $resolved = app(UserPriceMonthlyFeePaymentResolver::class)->resolveOrAbort(
                $userId,
                (int) $partnerId,
                $rawFmt,
                $teamIdParam
            );
            $outSum = $resolved['out_sum'];
            $paymentDate = $resolved['month_first_day'];
            $monthlyTeamId = $resolved['team_id'];
        } else {
            $outSumRaw = (string) $r->input('outSum', '0');
            $outSum = PaymentOutSumNormalizer::normalize($outSumRaw);
            if ($outSum === null) {
                return back()->withErrors(['tinkoff' => 'Некорректная сумма']);
            }
            $paymentDate = 'Клубный взнос';
        }

        $monthlyTeam = ($monthlyTeamId ?? 0) > 0
            ? Team::query()->whereKey($monthlyTeamId)->where('partner_id', $partnerId)->first()
            : null;

        if (! $paymentService->isTbankAvailable($partner, $monthlyTeam)) {
            return back()->withErrors(['tinkoff' => 'Партнёр не зарегистрирован в T‑Bank (нет ShopCode)']);
        }

        $amountCents = (int) round(((float) $outSum) * 100);
        // Ограничение банка для QR (СБП): сумма от 1 000 коп. (10 ₽) до 100 000 000 коп.
        if ($amountCents < 1000 || $amountCents > 100000000) {
            return back()->withErrors(['tinkoff' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.']);
        }

        $type = $paymentKind === 'custom_payment'
            ? 'custom_payment_fee'
            : ($paymentKind === 'lesson_package'
                ? 'lesson_package_fee'
                : ($hasMonthly ? 'monthly_fee' : 'club_fee'));
        $month = null;
        $payableMeta = [];
        if ($type === 'monthly_fee') {
            $month = $paymentDate;
            $payableMeta['month'] = $paymentDate;
            if (! empty($monthlyTeamId)) {
                $payableMeta['team_id'] = (int) $monthlyTeamId;
            }
        } elseif ($type === 'custom_payment_fee') {
            $payableMeta['user_period_price_id'] = $userPeriodPriceId;
        } elseif ($type === 'lesson_package_fee') {
            $payableMeta['user_lesson_package_id'] = $userLessonPackageId;
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
            'meta' => json_encode(array_merge(
                $this->buildPaymentIntentMeta($userName, $monthlyTeamId),
                ['method' => 'sbp']
            ), JSON_UNESCAPED_UNICODE),
        ], PaymentIntentClientContext::fromRequest($r)));

        $payment = $svc->initPayment($partnerId, $amountCents, 'sbp', $this->buildTbankInitData(
            $month,
            $payable->id,
            $intent->id,
            $userId,
            $paymentKind,
            $userPeriodPriceId,
            $userLessonPackageId,
            $monthlyTeamId,
        ));

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

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentIntentMeta(string $userName, ?int $monthlyTeamId): array
    {
        $meta = [
            'user_name' => $userName,
        ];

        if ($monthlyTeamId !== null && $monthlyTeamId > 0) {
            $meta['team_id'] = $monthlyTeamId;
        }

        return $meta;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildTbankInitData(
        ?string $month,
        int $payableId,
        int $intentId,
        int $userId,
        string $paymentKind,
        ?int $userPeriodPriceId,
        ?int $userLessonPackageId,
        ?int $monthlyTeamId,
    ): array {
        $data = [
            'month' => $month ?: null,
            'payable_id' => (string) $payableId,
            'payment_intent_id' => (string) $intentId,
            'user_id' => (string) $userId,
            'payment_kind' => $paymentKind !== '' ? $paymentKind : null,
            'user_period_price_id' => $userPeriodPriceId ? (string) $userPeriodPriceId : null,
            'user_lesson_package_id' => $userLessonPackageId ? (string) $userLessonPackageId : null,
        ];

        if ($monthlyTeamId !== null && $monthlyTeamId > 0) {
            $data['team_id'] = (string) $monthlyTeamId;
        }

        return $data;
    }
}
