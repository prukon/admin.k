<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Partner;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\TinkoffPayment;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use App\Services\Tinkoff\TbankTerminalConfig;
use App\Services\Tinkoff\TinkoffApiClient;
use App\Services\Tinkoff\TinkoffPaymentsService;
use App\Services\Tinkoff\TinkoffSignature;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UserLessonPackagePublicPayService
{
    private const LINK_TTL_DAYS = 30;

    private const INIT_TTL_DAYS = 30;

    public function __construct(
        private readonly UserLessonPackageFeePaymentResolver $feeResolver,
        private readonly TinkoffPaymentsService $tinkoffPayments,
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
     * @return array{
     *     kind: 'qr',
     *     paymentId: string,
     *     amountRubFormatted: string,
     *     successUrl: string,
     *     orderId: string,
     *     isMobileClient: bool
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

        return [
            'kind' => 'qr',
            'paymentId' => (string) $paymentId,
            'amountRubFormatted' => number_format($amountCents / 100, 2, '.', ''),
            'successUrl' => $orderId !== '' ? url('/payments/tinkoff/'.$orderId.'/success') : url('/payment/success'),
            'orderId' => $orderId,
            'isMobileClient' => $this->isLikelyMobileUserAgent($request->userAgent()),
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
                    $link->tinkoff_payment_id = null;
                    $link->payment_intent_id = null;
                    $link->payable_id = null;
                    $link->save();
                } else {
                    return $pid;
                }
            }
        }

        $studentUser = $ulp->user ?? $ulp->user()->first();
        if (! $studentUser) {
            return '__FAIL__';
        }

        try {
            $paymentTeamId = app(PayableTeamResolver::class)->resolveOrAbort(
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

    public function tinkoffQrJson(UserLessonPackagePublicPayLink $link, string $dataType): \Illuminate\Http\JsonResponse
    {
        if ($link->expires_at === null || $link->expires_at->isPast()) {
            return response()->json(['Success' => false, 'Message' => 'Ссылка недействительна'], 404);
        }

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
