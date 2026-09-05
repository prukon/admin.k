<?php

namespace App\Services\Tinkoff;

use App\Jobs\SendCloudKassirReceiptJob;
use App\Models\FiscalReceipt;
use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\PartnerPayment;
use App\Models\PartnerWalletTransaction;
use App\Models\TinkoffPayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TinkoffAcquiringPaymentsService
{
    public const SCOPE_WALLET_TOPUP = 'partner_wallet_topup';

    public const SCOPE_SERVICE_PAYMENT = 'partner_service_payment';

    public function initSbp(
        int $partnerId,
        int $amountCents,
        array $data,
        string $successUrl,
    ): TinkoffPayment {
        $orderId = Str::uuid()->toString();

        $payment = TinkoffPayment::create([
            'order_id' => $orderId,
            'partner_id' => $partnerId,
            'legal_entity_id' => null,
            'amount' => $amountCents,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'NEW',
        ]);

        $cfg = TbankAcquiringTerminalConfig::paymentConfig();
        $success = $this->asciiUrl($successUrl);

        $payload = [
            'TerminalKey' => $cfg['terminal_key'],
            'Amount' => $amountCents,
            'OrderId' => $orderId,
            'SuccessURL' => $success,
            'FailURL' => $success,
            'NotificationURL' => $this->asciiUrl($cfg['notify_url']),
            'Description' => 'Kidscrm.online',
            'PayType' => 'O',
            'Language' => 'ru',
            'DATA' => $data,
        ];

        $payload['Token'] = TinkoffSignature::makeToken($payload, $cfg['password']);

        $res = TinkoffApiClient::post($cfg['base_url'], '/v2/Init', $payload);

        if (! empty($res['Success'])) {
            $payment->update([
                'status' => 'FORM',
                'tinkoff_payment_id' => $res['PaymentId'] ?? null,
                'payment_url' => $res['PaymentURL'] ?? null,
                'payload' => [
                    'init_data' => $data,
                    'success_url' => $successUrl,
                    'response' => $res,
                ],
            ]);
        } else {
            $payment->update([
                'payload' => [
                    'init_data' => $data,
                    'success_url' => $successUrl,
                    'response' => $res,
                ],
            ]);
        }

        return $payment->fresh();
    }

    public function handleWebhook(array $data, bool $skipSignature = false): void
    {
        $orderId = $data['OrderId'] ?? null;
        if (! $orderId) {
            return;
        }

        $payment = TinkoffPayment::query()
            ->where('order_id', $orderId)
            ->where('channel', TinkoffPayment::CHANNEL_ACQUIRING)
            ->first();
        if (! $payment) {
            return;
        }

        $cfg = TbankAcquiringTerminalConfig::paymentConfig();
        if (! $skipSignature) {
            if (! TinkoffSignature::verify($data, $cfg['password'])) {
                throw new RuntimeException('Invalid T-Bank acquiring signature');
            }
        }

        $status = $data['Status'] ?? null;
        if ($status === 'CONFIRMED') {
            $payment->status = 'CONFIRMED';
            $payment->confirmed_at = now();
        } elseif (in_array($status, ['CANCELED', 'REJECTED', 'DEADLINE_EXPIRED'], true)) {
            $payment->status = 'CANCELED';
            $payment->canceled_at = now();
        }

        $pl = $payment->payload ?? [];
        $pl['last_webhook'] = $data;
        $payment->payload = $pl;
        $payment->save();

        try {
            $this->applyPlatformEffects($payment, $data);
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[acquiring domain-effects failed] '.$e->getMessage(), [
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function applyPlatformEffects(TinkoffPayment $payment, array $webhook): void
    {
        $status = (string) ($webhook['Status'] ?? '');
        $isSuccess = $status === 'CONFIRMED';
        $isFail = in_array($status, ['CANCELED', 'REJECTED', 'DEADLINE_EXPIRED'], true);
        if (! $isSuccess && ! $isFail) {
            return;
        }

        $data = $this->webhookData($webhook);
        if ($data === []) {
            $data = is_array($payment->payload['init_data'] ?? null)
                ? $payment->payload['init_data']
                : [];
        }

        $scope = (string) ($data['scope'] ?? '');
        $bankPaymentId = isset($webhook['PaymentId']) ? (string) $webhook['PaymentId'] : null;
        $amountCents = isset($webhook['Amount']) ? (int) $webhook['Amount'] : (int) $payment->amount;

        if ($scope === self::SCOPE_WALLET_TOPUP) {
            $this->applyWalletTopup($payment, $data, $isSuccess, $bankPaymentId, $amountCents, $webhook);

            return;
        }

        if ($scope === self::SCOPE_SERVICE_PAYMENT) {
            $this->applyServicePayment($payment, $data, $isSuccess, $bankPaymentId, $amountCents, $webhook);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $webhook
     */
    private function applyWalletTopup(
        TinkoffPayment $payment,
        array $data,
        bool $isSuccess,
        ?string $bankPaymentId,
        int $amountCents,
        array $webhook,
    ): void {
        $txId = (int) ($data['wallet_transaction_id'] ?? 0);
        if ($txId <= 0) {
            return;
        }

        DB::transaction(function () use ($txId, $payment, $isSuccess, $bankPaymentId, $amountCents, $webhook) {
            /** @var PartnerWalletTransaction|null $tx */
            $tx = PartnerWalletTransaction::query()
                ->whereKey($txId)
                ->where('provider', 'tinkoff')
                ->lockForUpdate()
                ->first();
            if (! $tx) {
                return;
            }

            if (in_array($tx->status, ['succeeded', 'canceled', 'failed'], true)) {
                return;
            }

            $meta = (array) $tx->meta;
            $meta['last_webhook'] = $webhook;
            $tx->meta = $meta;
            if ($bankPaymentId && $tx->payment_id === null) {
                $tx->payment_id = $bankPaymentId;
            }

            if (! $isSuccess) {
                $tx->status = 'canceled';
                $tx->save();

                return;
            }

            if (abs((int) $tx->amount_cents - $amountCents) > 0) {
                Log::channel('tinkoff')->warning('[acquiring wallet amount mismatch]', [
                    'wallet_transaction_id' => $tx->id,
                    'tx_amount' => (int) $tx->amount_cents,
                    'hook_amount' => $amountCents,
                ]);

                return;
            }

            $partner = Partner::query()->whereKey($tx->partner_id)->lockForUpdate()->firstOrFail();
            $tx->status = 'succeeded';
            $tx->save();

            $partner->wallet_balance_cents = (int) $partner->wallet_balance_cents + (int) $tx->amount_cents;
            $partner->save();

            $this->createPlatformFiscalReceipt(
                $partner,
                (int) $tx->amount_cents,
                'income:platform:wallet:'.$tx->id,
                'wallet_tx_'.$tx->id,
                (string) $tx->partner_id,
                walletTransactionId: (int) $tx->id,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $webhook
     */
    private function applyServicePayment(
        TinkoffPayment $payment,
        array $data,
        bool $isSuccess,
        ?string $bankPaymentId,
        int $amountCents,
        array $webhook,
    ): void {
        $paymentId = (int) ($data['partner_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            return;
        }

        DB::transaction(function () use ($paymentId, $isSuccess, $bankPaymentId, $amountCents, $webhook) {
            /** @var PartnerPayment|null $partnerPayment */
            $partnerPayment = PartnerPayment::query()->whereKey($paymentId)->lockForUpdate()->first();
            if (! $partnerPayment) {
                return;
            }

            if (in_array((string) $partnerPayment->payment_status, ['succeeded', 'canceled', 'failed'], true)) {
                return;
            }

            if ($bankPaymentId && (string) $partnerPayment->payment_id !== $bankPaymentId) {
                $partnerPayment->payment_id = $bankPaymentId;
            }

            if (! $isSuccess) {
                $partnerPayment->payment_status = 'canceled';
                $partnerPayment->save();

                return;
            }

            if (abs((int) $partnerPayment->amount_cents - $amountCents) > 0) {
                Log::channel('tinkoff')->warning('[acquiring service amount mismatch]', [
                    'partner_payment_id' => $partnerPayment->id,
                    'tx_amount' => (int) $partnerPayment->amount_cents,
                    'hook_amount' => $amountCents,
                ]);

                return;
            }

            $partnerPayment->payment_status = 'succeeded';
            $partnerPayment->save();

            PartnerAccess::query()
                ->where('partner_payment_id', $partnerPayment->id)
                ->update(['is_active' => 1]);

            $partner = Partner::query()->whereKey($partnerPayment->partner_id)->firstOrFail();

            $this->createPlatformFiscalReceipt(
                $partner,
                (int) $partnerPayment->amount_cents,
                'income:platform:service:'.$partnerPayment->id,
                'partner_payment_'.$partnerPayment->id,
                (string) $partnerPayment->partner_id,
                partnerPaymentId: (int) $partnerPayment->id,
            );
        });
    }

    private function createPlatformFiscalReceipt(
        Partner $partner,
        int $amountCents,
        string $idempotencyKey,
        string $invoiceId,
        string $accountId,
        ?int $walletTransactionId = null,
        ?int $partnerPaymentId = null,
    ): void {
        $fiscalReceipt = FiscalReceipt::query()->firstOrCreate(
            [
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'partner_id' => (int) $partner->id,
                'legal_entity_id' => null,
                'payment_intent_id' => null,
                'payment_id' => null,
                'payable_id' => null,
                'wallet_transaction_id' => $walletTransactionId,
                'partner_payment_id' => $partnerPaymentId,
                'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
                'source' => FiscalReceipt::SOURCE_PLATFORM,
                'type' => FiscalReceipt::TYPE_INCOME,
                'status' => FiscalReceipt::STATUS_PENDING,
                'amount_cents' => $amountCents,
                'invoice_id' => $invoiceId,
                'account_id' => $accountId,
            ]
        );

        if (in_array($fiscalReceipt->status, [
            FiscalReceipt::STATUS_QUEUED,
            FiscalReceipt::STATUS_PROCESSED,
        ], true)) {
            return;
        }

        SendCloudKassirReceiptJob::dispatch($fiscalReceipt->id);
    }

    /**
     * @param  array<string, mixed>  $webhook
     * @return array<string, mixed>
     */
    private function webhookData(array $webhook): array
    {
        $data = $webhook['Data'] ?? ($webhook['DATA'] ?? null);
        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($data) ? $data : [];
    }

    private function asciiUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! $parts || empty($parts['host'])) {
            return $url;
        }
        $asciiHost = idn_to_ascii($parts['host'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (! $asciiHost) {
            return $url;
        }
        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$asciiHost.$path.$query;
    }
}
