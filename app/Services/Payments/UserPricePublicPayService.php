<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Partner;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\UserPrice;
use App\Models\UserPricePublicPayLink;
use App\Services\PartnerLegalEntities\LegalEntityResolver;
use App\Services\Tinkoff\TbankTerminalConfig;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffPaymentsService;
use App\Services\Tinkoff\TinkoffSignature;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UserPricePublicPayService
{
    private const LINK_TTL_DAYS = 30;

    private const INIT_TTL_DAYS = 30;

    private const SHORT_CODE_LENGTH = 10;

    /** Без 0/O/1/I/l — проще прочитать в письме. */
    private const SHORT_CODE_ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly UserPriceMonthlyFeePaymentResolver $feeResolver,
        private readonly TinkoffPaymentsService $tinkoffPayments,
        private readonly PaymentCheckoutLegalEntityPresenter $checkoutLegalEntityPresenter,
    ) {
    }

    public function partnerTbankConfigured(int $partnerId, ?int $teamId = null): bool
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
            return false;
        }

        $partner = Partner::query()->find($partnerId);
        if (! $partner) {
            return false;
        }

        $team = null;
        if ($teamId !== null && $teamId > 0) {
            $team = Team::query()
                ->where('partner_id', $partnerId)
                ->whereKey($teamId)
                ->first();
        }

        return app(LegalEntityResolver::class)->hasRegisteredShopCode($partner, $team);
    }

    public function isAmountAllowedForSbp(int $amountCents): bool
    {
        return $amountCents >= 1000 && $amountCents <= 100000000;
    }

    /**
     * HTTPS-ссылка для письма. Пустая строка, если СБП сейчас недоступна
     * (нет T‑Bank, сумма вне диапазона, постоплата ещё закрыта, уже оплачено).
     * Init банка не выполняется.
     */
    public function shareUrlForNotification(UserPrice $userPrice): string
    {
        $userPrice->loadMissing(['user']);
        $partnerId = (int) ($userPrice->user?->partner_id ?? 0);
        if ($partnerId <= 0) {
            return '';
        }

        if (! $this->partnerTbankConfigured($partnerId, (int) $userPrice->team_id)) {
            return '';
        }

        try {
            $resolved = $this->feeResolver->resolvePublicPayForPartner($partnerId, $userPrice);
        } catch (HttpException) {
            return '';
        }

        $amountCents = (int) $resolved['amount_cents'];
        if (! $this->isAmountAllowedForSbp($amountCents)) {
            return '';
        }

        $link = $this->ensureFreshLink($userPrice);

        return $this->publicShareUrl($link);
    }

    public function ensureFreshLink(UserPrice $userPrice): UserPricePublicPayLink
    {
        $partnerId = (int) $userPrice->user->partner_id;

        /** @var UserPricePublicPayLink $link */
        $link = UserPricePublicPayLink::query()->firstOrNew(
            ['users_price_id' => $userPrice->id],
            ['partner_id' => $partnerId],
        );

        $needsRotation = ! $link->exists
            || $link->expires_at === null
            || $link->expires_at->isPast()
            || $link->token === '';

        if ($needsRotation) {
            $link->partner_id = $partnerId;
            $link->token = bin2hex(random_bytes(32));
            $link->short_code = $this->generateUniqueShortCode();
            $link->expires_at = now()->addDays(self::LINK_TTL_DAYS);
            $link->tinkoff_payment_id = null;
            $link->payment_intent_id = null;
            $link->payable_id = null;
            $link->save();
        } else {
            $dirty = false;
            if ((int) $link->partner_id !== $partnerId) {
                $link->partner_id = $partnerId;
                $dirty = true;
            }
            if (trim((string) ($link->short_code ?? '')) === '') {
                $link->short_code = $this->generateUniqueShortCode();
                $dirty = true;
            }
            $link->expires_at = now()->addDays(self::LINK_TTL_DAYS);
            $dirty = true;
            if ($dirty) {
                $link->save();
            }
        }

        return $link->fresh() ?? $link;
    }

    public function publicShareUrl(UserPricePublicPayLink $link): string
    {
        $code = trim((string) ($link->short_code ?? ''));
        if ($code !== '') {
            return route('up.public.pay.short', ['code' => $code], true);
        }

        return route('up.public.pay', ['token' => $link->token], true);
    }

    private function generateUniqueShortCode(): string
    {
        $alphabet = self::SHORT_CODE_ALPHABET;
        $max = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 16; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::SHORT_CODE_LENGTH; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
            $taken = UserPricePublicPayLink::query()
                ->where('short_code', $code)
                ->exists();
            if (! $taken) {
                return $code;
            }
        }

        throw new RuntimeException('Не удалось выделить уникальный короткий код ссылки на оплату.');
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, body: array<string, mixed>}
     */
    public function syncPublicPayPaymentForQr(UserPricePublicPayLink $link, Request $request): array
    {
        if ($link->expires_at === null || $link->expires_at->isPast()) {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Ссылка недействительна']];
        }

        $userPrice = $link->userPrice()->with(['user:id,partner_id', 'lessonPackage', 'team'])->first();
        if (! $userPrice || (int) ($userPrice->user?->partner_id ?? 0) !== (int) $link->partner_id) {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Payment not found']];
        }

        if ($userPrice->effective_is_paid) {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Payment already completed']];
        }

        if (! $this->partnerTbankConfigured((int) $link->partner_id, (int) $userPrice->team_id)) {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Payment not configured']];
        }

        try {
            $resolved = $this->feeResolver->resolvePublicPayForPartner((int) $link->partner_id, $userPrice);
        } catch (HttpException $e) {
            return ['ok' => false, 'status' => 422, 'body' => ['Success' => false, 'Message' => $e->getMessage()]];
        }

        $amountCents = (int) $resolved['amount_cents'];
        if (! $this->isAmountAllowedForSbp($amountCents)) {
            return ['ok' => false, 'status' => 422, 'body' => ['Success' => false, 'Message' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.']];
        }

        $paymentId = $this->ensureActiveTinkoffPaymentId(
            $link,
            $resolved,
            (int) $userPrice->user_id,
            $amountCents,
            $request,
        );

        if ($paymentId === '__PAID__') {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Payment already completed']];
        }
        if ($paymentId === '__FAIL__') {
            return ['ok' => false, 'status' => 500, 'body' => ['Success' => false, 'Message' => 'Payment init failed']];
        }

        return ['ok' => true];
    }

    /**
     * @return array{
     *     kind: 'qr',
     *     paymentId: string,
     *     amountRubFormatted: string,
     *     successUrl: string,
     *     orderId: string,
     *     isMobileClient: bool,
     *     serviceProviderTeamTitle: ?string,
     *     serviceProviderLabel: ?string,
     *     showTbankLegalEntityBlock: bool
     * }|array{kind: 'paid'}|array{kind: 'expired'}|array{kind: 'config'}|array{kind: 'error', message: string}
     */
    public function resolvePublicShow(UserPricePublicPayLink $link, Request $request): array
    {
        if ($link->expires_at === null || $link->expires_at->isPast()) {
            return ['kind' => 'expired'];
        }

        $userPrice = $link->userPrice()->with(['user:id,partner_id', 'lessonPackage', 'team'])->first();
        if (! $userPrice || (int) ($userPrice->user?->partner_id ?? 0) !== (int) $link->partner_id) {
            return ['kind' => 'error', 'message' => 'Начисление не найдено'];
        }

        if ($userPrice->effective_is_paid) {
            return ['kind' => 'paid'];
        }

        if (! $this->partnerTbankConfigured((int) $link->partner_id, (int) $userPrice->team_id)) {
            return ['kind' => 'config'];
        }

        try {
            $resolved = $this->feeResolver->resolvePublicPayForPartner((int) $link->partner_id, $userPrice);
        } catch (HttpException $e) {
            return ['kind' => 'error', 'message' => $e->getMessage()];
        }

        $amountCents = (int) $resolved['amount_cents'];
        if (! $this->isAmountAllowedForSbp($amountCents)) {
            return ['kind' => 'error', 'message' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.'];
        }

        $studentUserId = (int) $userPrice->user_id;

        $paymentId = $this->ensureActiveTinkoffPaymentId($link, $resolved, $studentUserId, $amountCents, $request);
        if ($paymentId === '__PAID__') {
            return ['kind' => 'paid'];
        }
        if ($paymentId === '__FAIL__') {
            return ['kind' => 'error', 'message' => 'Не удалось инициализировать оплату T‑Bank (СБП).'];
        }

        $tp = TinkoffPayment::query()
            ->where('partner_id', (int) $link->partner_id)
            ->where('tinkoff_payment_id', (string) $paymentId)
            ->first();

        $orderId = $tp ? (string) $tp->order_id : '';

        $checkoutDisplay = $this->resolvePublicPayCheckoutDisplay((int) $link->partner_id, $resolved['team_id']);

        return [
            'kind' => 'qr',
            'paymentId' => (string) $paymentId,
            'amountRubFormatted' => Money::formatRub($amountCents),
            'successUrl' => $orderId !== '' ? url('/payments/tinkoff/'.$orderId.'/success') : url('/payment/success'),
            'orderId' => $orderId,
            'isMobileClient' => $this->isLikelyMobileUserAgent($request->userAgent()),
            'serviceProviderTeamTitle' => $checkoutDisplay['teamTitle'],
            'serviceProviderLabel' => $checkoutDisplay['serviceProviderLabel'],
            'showTbankLegalEntityBlock' => true,
        ];
    }

    /**
     * @return array{teamTitle: ?string, serviceProviderLabel: ?string}
     */
    private function resolvePublicPayCheckoutDisplay(int $partnerId, int $teamId): array
    {
        $team = Team::query()
            ->where('partner_id', $partnerId)
            ->whereKey($teamId)
            ->first();

        $teamTitle = $team ? trim((string) $team->title) : null;
        if ($teamTitle === '') {
            $teamTitle = null;
        }

        $label = $this->checkoutLegalEntityPresenter->labelForTeamId($partnerId, $teamId);

        return [
            'teamTitle' => $teamTitle,
            'serviceProviderLabel' => $label !== null && $label !== '' ? $label : null,
        ];
    }

    /**
     * @param  array{user_price: UserPrice, amount_cents: int, out_sum: string, month_first_day: string, team_id: int}  $resolved
     */
    private function ensureActiveTinkoffPaymentId(
        UserPricePublicPayLink $link,
        array $resolved,
        int $studentUserId,
        int $amountCents,
        Request $request,
    ): string {
        $partnerId = (int) $link->partner_id;
        $userPrice = $resolved['user_price'];
        $userPriceId = (int) $userPrice->id;
        $paymentTeamId = (int) $resolved['team_id'];
        $monthFirstDay = (string) $resolved['month_first_day'];

        $redirectDue = CarbonImmutable::now()->addDays(self::INIT_TTL_DAYS);

        if ($link->tinkoff_payment_id) {
            $pid = (string) $link->tinkoff_payment_id;
            $tp = TinkoffPayment::query()
                ->where('partner_id', $partnerId)
                ->where('tinkoff_payment_id', $pid)
                ->first();

            if (! $tp) {
                $link->tinkoff_payment_id = null;
                $link->payment_intent_id = null;
                $link->payable_id = null;
                $link->save();
            } else {
                if ((string) $tp->status === 'CONFIRMED') {
                    return '__PAID__';
                }

                $state = $this->callGetState($partnerId, $pid);
                $bankOk = is_array($state) && ! empty($state['Success']);
                $bankStatus = $bankOk ? (string) ($state['Status'] ?? '') : '';

                if ($bankStatus === 'CONFIRMED') {
                    return '__PAID__';
                }

                if (in_array($bankStatus, ['CANCELED', 'REJECTED', 'DEADLINE_EXPIRED'], true)) {
                    $this->markLinkedPublicPayRecordsCancelled($link);
                    $this->clearPublicPayLinkPaymentBinding($link);
                } elseif (
                    $bankOk
                    && isset($state['Amount'])
                    && (int) $state['Amount'] !== $amountCents
                ) {
                    $this->invalidateActivePublicPayPayment($link);
                    $link->refresh();
                } elseif ($this->activePaymentAmountMatches($link, $amountCents)) {
                    return $pid;
                } else {
                    $this->invalidateActivePublicPayPayment($link);
                    $link->refresh();
                }
            }
        }

        $payable = Payable::create([
            'partner_id' => $partnerId,
            'user_id' => $studentUserId,
            'type' => 'monthly_fee',
            'amount_cents' => $amountCents,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $monthFirstDay,
            'meta' => [
                'month' => $monthFirstDay,
                'team_id' => $paymentTeamId,
                'users_price_id' => $userPriceId,
                'up_public_pay' => true,
            ],
        ]);

        $intent = PaymentIntent::create(array_merge([
            'partner_id' => $partnerId,
            'user_id' => $studentUserId,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'payment_method' => 'sbp_qr',
            'status' => 'pending',
            'out_sum_cents' => $amountCents,
            'payment_date' => $monthFirstDay,
            'meta' => json_encode([
                'method' => 'sbp',
                'up_public_pay' => true,
            ], JSON_UNESCAPED_UNICODE),
        ], PaymentIntentClientContext::fromRequest($request)));

        $payment = $this->tinkoffPayments->initPayment($partnerId, $amountCents, 'sbp', [
            'payable_id' => (string) $payable->id,
            'payment_intent_id' => (string) $intent->id,
            'user_id' => (string) $studentUserId,
            'month' => $monthFirstDay,
            'team_id' => (string) $paymentTeamId,
            'users_price_id' => (string) $userPriceId,
            'up_public_pay' => '1',
        ], $redirectDue);

        if (empty($payment->tinkoff_payment_id)) {
            return '__FAIL__';
        }

        $intent->tbank_order_id = (string) $payment->order_id;
        $intent->tbank_payment_id = (int) $payment->tinkoff_payment_id;
        $intent->provider_inv_id = (int) $payment->tinkoff_payment_id;
        $intent->save();

        $link->tinkoff_payment_id = (string) $payment->tinkoff_payment_id;
        $link->payment_intent_id = (int) $intent->id;
        $link->payable_id = (int) $payable->id;
        $link->save();

        return (string) $payment->tinkoff_payment_id;
    }

    private function activePaymentAmountMatches(UserPricePublicPayLink $link, int $expectedAmountCents): bool
    {
        $paymentId = (string) ($link->tinkoff_payment_id ?? '');
        if ($paymentId === '') {
            return false;
        }

        $tp = TinkoffPayment::query()
            ->where('partner_id', (int) $link->partner_id)
            ->where('tinkoff_payment_id', $paymentId)
            ->first();

        if (! $tp || (int) $tp->amount !== $expectedAmountCents) {
            return false;
        }

        if ($link->payable_id) {
            $payable = Payable::query()->find((int) $link->payable_id);
            if ($payable && (int) $payable->amount_cents !== $expectedAmountCents) {
                return false;
            }
        }

        if ($link->payment_intent_id) {
            $intent = PaymentIntent::query()->find((int) $link->payment_intent_id);
            if ($intent && (int) $intent->out_sum_cents !== $expectedAmountCents) {
                return false;
            }
        }

        return true;
    }

    private function invalidateActivePublicPayPayment(UserPricePublicPayLink $link): void
    {
        $paymentId = (string) ($link->tinkoff_payment_id ?? '');
        if ($paymentId === '') {
            return;
        }

        $partnerId = (int) $link->partner_id;

        $state = $this->callGetState($partnerId, $paymentId);
        $bankOk = is_array($state) && ! empty($state['Success']);
        $bankStatus = $bankOk ? (string) ($state['Status'] ?? '') : '';

        if ($bankStatus === 'CONFIRMED') {
            return;
        }

        if (
            $bankStatus === ''
            || ! in_array($bankStatus, ['CANCELED', 'REJECTED', 'DEADLINE_EXPIRED'], true)
        ) {
            try {
                $cancelResponse = $this->callCancel($partnerId, $paymentId);
                if (! is_array($cancelResponse) || empty($cancelResponse['Success'])) {
                    Log::channel('tinkoff')->warning('[up-public-pay cancel] PaymentId='.$paymentId, [
                        'response' => $cancelResponse,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::channel('tinkoff')->warning(
                    '[up-public-pay cancel failed] PaymentId='.$paymentId.' '.$e->getMessage()
                );
            }
        }

        $this->markLinkedPublicPayRecordsCancelled($link);

        TinkoffPayment::query()
            ->where('partner_id', $partnerId)
            ->where('tinkoff_payment_id', $paymentId)
            ->where('status', '!=', 'CONFIRMED')
            ->update(['status' => 'CANCELED']);

        $this->clearPublicPayLinkPaymentBinding($link);
    }

    private function markLinkedPublicPayRecordsCancelled(UserPricePublicPayLink $link): void
    {
        if ($link->payment_intent_id) {
            PaymentIntent::query()
                ->whereKey((int) $link->payment_intent_id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        }

        if ($link->payable_id) {
            Payable::query()
                ->whereKey((int) $link->payable_id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        }
    }

    private function clearPublicPayLinkPaymentBinding(UserPricePublicPayLink $link): void
    {
        $link->tinkoff_payment_id = null;
        $link->payment_intent_id = null;
        $link->payable_id = null;
        $link->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callCancel(int $partnerId, string $paymentId): ?array
    {
        $cfg = $this->resolvePaymentConfig($partnerId);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId' => $paymentId,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        return TinkoffApiClient::post($cfg['base_url'], '/v2/Cancel', $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callGetState(int $partnerId, string $paymentId): ?array
    {
        $cfg = $this->resolvePaymentConfig($partnerId);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId' => $paymentId,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        return TinkoffApiClient::post($cfg['base_url'], '/v2/GetState', $payload);
    }

    /**
     * @return array{terminal_key: string, password: string, base_url: string}
     */
    private function resolvePaymentConfig(int $partnerId): array
    {
        return TbankTerminalConfig::paymentConfig();
    }

    public function tinkoffQrJson(UserPricePublicPayLink $link, string $dataType, Request $request): \Illuminate\Http\JsonResponse
    {
        $sync = $this->syncPublicPayPaymentForQr($link, $request);
        if (! $sync['ok']) {
            return response()->json($sync['body'], $sync['status']);
        }

        $link->refresh();

        $userPrice = $link->userPrice()->with('user:id,partner_id')->first();
        if (! $userPrice || $userPrice->effective_is_paid || (int) ($userPrice->user?->partner_id ?? 0) !== (int) $link->partner_id) {
            return response()->json(['Success' => false, 'Message' => 'Payment not found'], 404);
        }

        $pid = (string) ($link->tinkoff_payment_id ?? '');
        if ($pid === '') {
            return response()->json(['Success' => false, 'Message' => 'Payment not initialized'], 404);
        }

        $cfg = $this->resolvePaymentConfig((int) $link->partner_id);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId' => $pid,
            'DataType' => $dataType,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);
        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetQr', $payload);

        return response()->json($res);
    }

    public function tinkoffQrState(UserPricePublicPayLink $link): \Illuminate\Http\JsonResponse
    {
        if ($link->expires_at === null || $link->expires_at->isPast()) {
            return response()->json(['Success' => false, 'Message' => 'Ссылка недействительна'], 404);
        }

        $userPrice = $link->userPrice()->with('user:id,partner_id')->first();
        if (! $userPrice || (int) ($userPrice->user?->partner_id ?? 0) !== (int) $link->partner_id) {
            return response()->json(['Success' => false, 'Message' => 'Payment not found'], 404);
        }

        if ($userPrice->effective_is_paid) {
            return response()->json([
                'Success' => true,
                'ErrorCode' => '0',
                'Status' => 'CONFIRMED',
            ]);
        }

        $pid = (string) ($link->tinkoff_payment_id ?? '');
        if ($pid === '') {
            return response()->json(['Success' => false, 'Message' => 'Payment not initialized'], 404);
        }

        $cfg = $this->resolvePaymentConfig((int) $link->partner_id);
        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'PaymentId' => $pid,
        ];
        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);
        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/GetState', $payload);

        return response()->json($res);
    }

    private function isLikelyMobileUserAgent(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        return (bool) preg_match(
            '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile\b|CriOS|FxiOS/i',
            $userAgent
        );
    }
}
