<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Partner;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use App\Services\Tinkoff\TbankTerminalConfig;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffPaymentsService;
use App\Services\Tinkoff\TinkoffSignature;
use App\Support\Payments\PaymentOutSumNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UserLessonPackagePublicPayService
{
    private const LINK_TTL_DAYS = 30;

    private const INIT_TTL_DAYS = 30;

    public function __construct(
        private readonly UserLessonPackageFeePaymentResolver $feeResolver,
        private readonly TinkoffPaymentsService $tinkoffPayments,
        private readonly PayableTeamResolver $payableTeamResolver,
        private readonly PaymentCheckoutLegalEntityPresenter $checkoutLegalEntityPresenter,
    ) {
    }

    public function partnerTbankConfigured(int $partnerId): bool
    {
        if (! TbankTerminalConfig::isGloballyActive()) {
            return false;
        }

        $partner = Partner::query()->find($partnerId);
        if (! $partner) {
            return false;
        }

        return app(\App\Services\PartnerLegalEntities\LegalEntityResolver::class)
            ->hasRegisteredShopCode($partner);
    }

    /**
     * @throws HttpException
     */
    public function assertAmountAllowedForSbp(int $partnerId, int $ulpId): void
    {
        $resolved = $this->feeResolver->resolvePublicPayForPartner($partnerId, $ulpId);
        $amountCents = (int) round(((float) $resolved['out_sum']) * 100);
        if ($amountCents < 1000 || $amountCents > 100000000) {
            throw new HttpException(422, 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.');
        }
    }

    public function ensureFreshLink(UserLessonPackage $ulp): UserLessonPackagePublicPayLink
    {
        $partnerId = (int) $ulp->user->partner_id;

        /** @var UserLessonPackagePublicPayLink $link */
        $link = UserLessonPackagePublicPayLink::query()->firstOrNew(
            ['user_lesson_package_id' => $ulp->id],
            ['partner_id' => $partnerId],
        );

        $needsRotation = ! $link->exists
            || $link->expires_at === null
            || $link->expires_at->isPast()
            || $link->token === '';

        if ($needsRotation) {
            $link->partner_id = $partnerId;
            $link->token = bin2hex(random_bytes(32));
            $link->expires_at = now()->addDays(self::LINK_TTL_DAYS);
            $link->tinkoff_payment_id = null;
            $link->payment_intent_id = null;
            $link->payable_id = null;
            $link->save();
        } elseif ((int) $link->partner_id !== $partnerId) {
            $link->partner_id = $partnerId;
            $link->save();
        }

        return $link->fresh() ?? $link;
    }

    /**
     * При смене fee_amount: отменяет активный T‑Bank-платёж и выпускает новый token.
     * Старая ссылка /pay/ulp/{token} перестаёт работать — нужно скопировать новую.
     */
    public function resetPublicPayAfterFeeChange(UserLessonPackage $ulp): ?string
    {
        if ($ulp->effective_is_paid) {
            return null;
        }

        $link = UserLessonPackagePublicPayLink::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->first();

        if (! $link) {
            return null;
        }

        if ((string) ($link->tinkoff_payment_id ?? '') !== '') {
            $this->invalidateActivePublicPayPayment($link);
            $link->refresh();
        }

        $link->token = bin2hex(random_bytes(32));
        $link->expires_at = now()->addDays(self::LINK_TTL_DAYS);
        $link->tinkoff_payment_id = null;
        $link->payment_intent_id = null;
        $link->payable_id = null;
        $link->save();

        return route('ulp.public.pay', ['token' => $link->token], true);
    }

    /**
     * Сбрасывает активный T‑Bank-платёж, если сумма в платеже не совпадает с fee_amount.
     */
    public function invalidatePublicPayIfAmountMismatch(UserLessonPackage $ulp): void
    {
        if ($ulp->effective_is_paid) {
            return;
        }

        $link = UserLessonPackagePublicPayLink::query()
            ->where('user_lesson_package_id', $ulp->id)
            ->first();

        if (! $link || (string) ($link->tinkoff_payment_id ?? '') === '') {
            return;
        }

        try {
            $resolved = $this->feeResolver->resolvePublicPayForPartner((int) $link->partner_id, (int) $ulp->id);
        } catch (HttpException) {
            return;
        }

        $expectedAmountCents = (int) round(((float) $resolved['out_sum']) * 100);

        if ($this->activePaymentAmountMatches($link, $expectedAmountCents)) {
            return;
        }

        $this->invalidateActivePublicPayPayment($link);
    }

    /**
     * @deprecated use invalidatePublicPayIfAmountMismatch()
     */
    public function invalidatePublicPayOnFeeChange(UserLessonPackage $ulp): void
    {
        $this->invalidatePublicPayIfAmountMismatch($ulp);
    }

    /**
     * Синхронизирует T‑Bank-платёж перед выдачей QR / payload (та же логика, что при открытии страницы).
     *
     * @return array{ok: true}|array{ok: false, status: int, body: array<string, mixed>}
     */
    public function syncPublicPayPaymentForQr(UserLessonPackagePublicPayLink $link, Request $request): array
    {
        if ($link->expires_at === null || $link->expires_at->isPast()) {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Ссылка недействительна']];
        }

        $ulp = $link->userLessonPackage()->with(['user:id,partner_id', 'lessonPackage:id,name'])->first();
        if (! $ulp || $ulp->effective_is_paid || (int) $ulp->user->partner_id !== (int) $link->partner_id) {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Payment not found']];
        }

        if (! $this->partnerTbankConfigured((int) $link->partner_id)) {
            return ['ok' => false, 'status' => 404, 'body' => ['Success' => false, 'Message' => 'Payment not configured']];
        }

        try {
            $resolved = $this->feeResolver->resolvePublicPayForPartner((int) $link->partner_id, (int) $ulp->id);
        } catch (HttpException $e) {
            return ['ok' => false, 'status' => 422, 'body' => ['Success' => false, 'Message' => $e->getMessage()]];
        }

        $amountCents = (int) round(((float) $resolved['out_sum']) * 100);
        if ($amountCents < 1000 || $amountCents > 100000000) {
            return ['ok' => false, 'status' => 422, 'body' => ['Success' => false, 'Message' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.']];
        }

        $paymentId = $this->ensureActiveTinkoffPaymentId(
            $link,
            $ulp,
            $resolved,
            (int) $ulp->user_id,
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
    public function resolvePublicShow(UserLessonPackagePublicPayLink $link, Request $request): array
    {
        if ($link->expires_at === null || $link->expires_at->isPast()) {
            return ['kind' => 'expired'];
        }

        $ulp = $link->userLessonPackage()->with(['user:id,partner_id', 'lessonPackage:id,name'])->first();
        if (! $ulp || (int) $ulp->user->partner_id !== (int) $link->partner_id) {
            return ['kind' => 'error', 'message' => 'Назначение не найдено'];
        }

        if ($ulp->effective_is_paid) {
            return ['kind' => 'paid'];
        }

        if (! $this->partnerTbankConfigured((int) $link->partner_id)) {
            return ['kind' => 'config'];
        }

        try {
            $resolved = $this->feeResolver->resolvePublicPayForPartner((int) $link->partner_id, (int) $ulp->id);
        } catch (HttpException $e) {
            return ['kind' => 'error', 'message' => $e->getMessage()];
        }

        $amountCents = (int) round(((float) $resolved['out_sum']) * 100);
        if ($amountCents < 1000 || $amountCents > 100000000) {
            return ['kind' => 'error', 'message' => 'Оплата по СБП доступна для суммы от 10 ₽ до 1 000 000 ₽.'];
        }

        $studentUserId = (int) $ulp->user_id;

        $paymentId = $this->ensureActiveTinkoffPaymentId($link, $ulp, $resolved, $studentUserId, $amountCents, $request);
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

        $checkoutDisplay = $this->resolvePublicPayCheckoutDisplay((int) $link->partner_id, $ulp, $link);

        return [
            'kind' => 'qr',
            'paymentId' => (string) $paymentId,
            'amountRubFormatted' => number_format((int) round($amountCents / 100), 0, ',', ' '),
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
    private function resolvePublicPayCheckoutDisplay(
        int $partnerId,
        UserLessonPackage $ulp,
        UserLessonPackagePublicPayLink $link,
    ): array {
        $studentUser = $ulp->user;
        if (! $studentUser) {
            return ['teamTitle' => null, 'serviceProviderLabel' => null];
        }

        $teamId = null;

        if ($link->payable_id) {
            $payable = Payable::query()->find((int) $link->payable_id);
            if ($payable) {
                $teamId = $this->payableTeamResolver->resolveFromPayable($payable, $studentUser);
            }
        }

        if ($teamId === null || $teamId <= 0) {
            try {
                $teamId = $this->payableTeamResolver->resolveOrAbort(
                    'lesson_package_fee',
                    $partnerId,
                    $studentUser,
                    null,
                    null,
                    $ulp,
                );
            } catch (HttpException) {
                return ['teamTitle' => null, 'serviceProviderLabel' => null];
            }
        }

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

    private function ensureActiveTinkoffPaymentId(
        UserLessonPackagePublicPayLink $link,
        UserLessonPackage $ulp,
        array $resolved,
        int $studentUserId,
        int $amountCents,
        Request $request,
    ): string {
        $partnerId = (int) $link->partner_id;
        $ulpId = (int) $ulp->id;

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

        $studentUser = $ulp->user ?? $ulp->user()->first();
        if (! $studentUser) {
            return '__FAIL__';
        }

        try {
            $paymentTeamId = $this->payableTeamResolver->resolveOrAbort(
                'lesson_package_fee',
                $partnerId,
                $studentUser,
                null,
                null,
                $ulp,
            );
        } catch (HttpException) {
            return '__FAIL__';
        }

        $payable = Payable::create([
            'partner_id' => $partnerId,
            'user_id' => $studentUserId,
            'type' => 'lesson_package_fee',
            'amount' => $resolved['out_sum'],
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => null,
            'meta' => [
                'user_lesson_package_id' => $ulpId,
                'team_id' => $paymentTeamId,
            ],
        ]);

        $intent = PaymentIntent::create(array_merge([
            'partner_id' => $partnerId,
            'user_id' => $studentUserId,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'payment_method' => 'sbp_qr',
            'status' => 'pending',
            'out_sum' => $resolved['out_sum'],
            'payment_date' => $resolved['payment_label'],
            'meta' => json_encode([
                'method' => 'sbp',
                'ulp_public_pay' => true,
            ], JSON_UNESCAPED_UNICODE),
        ], PaymentIntentClientContext::fromRequest($request)));

        $payment = $this->tinkoffPayments->initPayment($partnerId, $amountCents, 'sbp', [
            'payable_id' => (string) $payable->id,
            'payment_intent_id' => (string) $intent->id,
            'user_id' => (string) $studentUserId,
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => (string) $ulpId,
            'team_id' => (string) $paymentTeamId,
            'ulp_public_pay' => '1',
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

    private function activePaymentAmountMatches(UserLessonPackagePublicPayLink $link, int $expectedAmountCents): bool
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
            if ($payable) {
                $payableCents = $this->amountCentsFromOutSum((string) $payable->amount);
                if ($payableCents === null || $payableCents !== $expectedAmountCents) {
                    return false;
                }
            }
        }

        if ($link->payment_intent_id) {
            $intent = PaymentIntent::query()->find((int) $link->payment_intent_id);
            if ($intent) {
                $intentCents = $this->amountCentsFromOutSum((string) $intent->out_sum);
                if ($intentCents === null || $intentCents !== $expectedAmountCents) {
                    return false;
                }
            }
        }

        return true;
    }

    private function storedAmountCentsForLink(UserLessonPackagePublicPayLink $link): ?int
    {
        $paymentId = (string) ($link->tinkoff_payment_id ?? '');
        if ($paymentId !== '') {
            $tp = TinkoffPayment::query()
                ->where('partner_id', (int) $link->partner_id)
                ->where('tinkoff_payment_id', $paymentId)
                ->first();

            if ($tp && $tp->amount !== null) {
                return (int) $tp->amount;
            }
        }

        if ($link->payable_id) {
            $payable = Payable::query()->find((int) $link->payable_id);
            if ($payable) {
                return $this->amountCentsFromOutSum((string) $payable->amount);
            }
        }

        if ($link->payment_intent_id) {
            $intent = PaymentIntent::query()->find((int) $link->payment_intent_id);
            if ($intent) {
                return $this->amountCentsFromOutSum((string) $intent->out_sum);
            }
        }

        return null;
    }

    private function amountCentsFromOutSum(string $outSum): ?int
    {
        $normalized = PaymentOutSumNormalizer::normalize($outSum);
        if ($normalized === null) {
            return null;
        }

        return (int) round(((float) $normalized) * 100);
    }

    private function invalidateActivePublicPayPayment(UserLessonPackagePublicPayLink $link): void
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
                    Log::channel('tinkoff')->warning('[ulp-public-pay cancel] PaymentId='.$paymentId, [
                        'response' => $cancelResponse,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::channel('tinkoff')->warning(
                    '[ulp-public-pay cancel failed] PaymentId='.$paymentId.' '.$e->getMessage()
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

    private function markLinkedPublicPayRecordsCancelled(UserLessonPackagePublicPayLink $link): void
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

    private function clearPublicPayLinkPaymentBinding(UserLessonPackagePublicPayLink $link): void
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

    public function tinkoffQrJson(UserLessonPackagePublicPayLink $link, string $dataType, Request $request): \Illuminate\Http\JsonResponse
    {
        $sync = $this->syncPublicPayPaymentForQr($link, $request);
        if (! $sync['ok']) {
            return response()->json($sync['body'], $sync['status']);
        }

        $link->refresh();

        $ulp = $link->userLessonPackage()->with('user:id,partner_id')->first();
        if (! $ulp || $ulp->effective_is_paid || (int) $ulp->user->partner_id !== (int) $link->partner_id) {
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

    public function tinkoffQrState(UserLessonPackagePublicPayLink $link): \Illuminate\Http\JsonResponse
    {
        if ($link->expires_at === null || $link->expires_at->isPast()) {
            return response()->json(['Success' => false, 'Message' => 'Ссылка недействительна'], 404);
        }

        $ulp = $link->userLessonPackage()->with('user:id,partner_id')->first();
        if (! $ulp || (int) $ulp->user->partner_id !== (int) $link->partner_id) {
            return response()->json(['Success' => false, 'Message' => 'Payment not found'], 404);
        }

        // Webhook помечает абонемент оплаченным до того, как вкладка с QR успевает получить CONFIRMED из GetState.
        if ($ulp->effective_is_paid) {
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
