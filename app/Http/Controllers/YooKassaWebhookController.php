<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\PartnerPayment;
use App\Models\PartnerWalletTransaction;

class YooKassaWebhookController extends Controller
{
    // Разрешённые IP (как у тебя было)
private array $allowedIps = [
'185.71.76.0/27',
'185.71.77.0/27',
'77.75.153.0/25',
'77.75.156.11',
'77.75.156.35',
'77.75.154.128/25',
'2a02:5180::/32', // IPv6
];

    public function handle(Request $request)
    {
        $clientIp = $request->ip();
        if (!$this->isAllowedIp($clientIp)) {
            Log::warning('YooKassa webhook: unauthorized IP', ['ip' => $clientIp]);
            return response()->json(['error' => 'Unauthorized IP address.'], 403);
        }

        // Безопасно получаем payload и логируем
        $payload = $request->json()->all() ?: $request->all();
        Log::info('YooKassa webhook received', ['ip' => $clientIp, 'payload' => $payload]);

        // Базовая валидация
        $request->validate([
            'event'               => 'required|string',
            'object.id'           => 'required|string',
            'object.amount.value' => 'nullable', // для кошелька сверяем ниже, не критично для всех событий
        ]);

        $event     = $payload['event'];
        $object    = $payload['object'] ?? [];
        $paymentId = $object['id'] ?? null;
        $metadata  = $object['metadata'] ?? [];
        $scope     = $metadata['scope'] ?? null;

        // Маршрутизация по scope
        if ($scope === 'partner_wallet_topup') {
            return $this->handleWalletTopup($event, $object, $payload, $paymentId);
        }

        // По умолчанию — логика абонплаты (как в твоём старом WebhookController)
        return $this->handlePartnerSubscription($event, $object, $payload, $paymentId);
    }

    /**
     * Обработка пополнения кошелька партнёра.
     */
    private function handleWalletTopup(string $event, array $object, array $payload, ?string $paymentId)
    {
        $amountVal = isset($object['amount']['value']) ? (float)$object['amount']['value'] : null;
        $metadata  = $object['metadata'] ?? [];
        $walletTxId = $metadata['wallet_transaction_id'] ?? null;

        if (!$paymentId || !$walletTxId) {
            Log::warning('Wallet webhook: missing ids', ['payment_id' => $paymentId, 'wallet_transaction_id' => $walletTxId]);
            return response()->json(['ok' => false, 'message' => 'No payment_id or wallet_transaction_id'], 400);
        }

        /** @var PartnerWalletTransaction|null $tx */
        $tx = PartnerWalletTransaction::where('id', $walletTxId)
            ->where('provider', 'yookassa')
            ->first();

        if (!$tx) {
            Log::error('Wallet webhook: transaction not found', ['wallet_transaction_id' => $walletTxId]);
            return response()->json(['ok' => false, 'message' => 'Transaction not found'], 404);
        }

        // Идемпотентность
        if (in_array($tx->status, ['succeeded', 'canceled', 'failed'], true)) {
            Log::info('Wallet webhook: already finalized', ['wallet_transaction_id' => $tx->id, 'status' => $tx->status]);
            return response()->json(['ok' => true]);
        }

        // Необязательная сверка суммы
        if ($amountVal !== null && abs((float)$tx->amount - (float)$amountVal) > 0.009) {
            Log::warning('Wallet webhook: amount mismatch', [
                'wallet_transaction_id' => $tx->id,
                'tx_amount'  => (float)$tx->amount,
                'hook_amount'=> (float)$amountVal,
            ]);
            return response()->json(['ok' => false, 'message' => 'Amount mismatch'], 422);
        }

        if ($event === 'payment.succeeded') {
            DB::transaction(function () use ($tx, $payload, $paymentId) {
                $partner = Partner::where('id', $tx->partner_id)->lockForUpdate()->firstOrFail();

                // Сохраняем полезные данные
                $meta = (array)$tx->meta;
                $meta['last_webhook'] = $payload;
                $tx->meta = $meta;

                if (empty($tx->payment_id)) {
                    $tx->payment_id = $paymentId;
                }

                $tx->status = 'succeeded';
                $tx->save();

                // Зачисление
                $partner->wallet_balance = (float)$partner->wallet_balance + (float)$tx->amount;
                $partner->save();
            });

            Log::info('Wallet webhook: credited', [
                'wallet_transaction_id' => $tx->id,
                'partner_id' => $tx->partner_id,
                'amount' => (float)$tx->amount,
            ]);

            return response()->json(['ok' => true]);
        }

        if ($event === 'payment.canceled') {
            DB::transaction(function () use ($tx, $payload, $paymentId) {
                $meta = (array)$tx->meta;
                $meta['last_webhook'] = $payload;
                $tx->meta = $meta;

                if (empty($tx->payment_id)) {
                    $tx->payment_id = $paymentId;
                }

                $tx->status = 'canceled';
                $tx->save();
            });

            Log::info('Wallet webhook: canceled', ['wallet_transaction_id' => $tx->id, 'payment_id' => $paymentId]);
            return response()->json(['ok' => true]);
        }

        Log::info('Wallet webhook: event acknowledged', ['event' => $event, 'payment_id' => $paymentId]);
        return response()->json(['ok' => true]);
    }

    /**
     * Обработка абонплаты партнёра (твоя старая логика).
     */
    private function handlePartnerSubscription(string $event, array $object, array $payload, ?string $paymentId)
    {
        // Нас интересует только успешная оплата
        if ($event !== 'payment.succeeded') {
            Log::info('Partner subscription webhook: event acknowledged', ['event' => $event, 'payment_id' => $paymentId]);
            return response()->json(['ok' => true]);
        }

        try {
            DB::transaction(function () use ($paymentId) {
                /** @var PartnerPayment|null $partnerPayment */
                $partnerPayment = PartnerPayment::where('payment_id', $paymentId)->first();
                if (!$partnerPayment) {
                    throw new \RuntimeException("Платёж с ID {$paymentId} не найден в базе.");
                }

                $partnerPayment->update(['payment_status' => 'succeeded']);
                Log::info("Partner subscription: payment succeeded", ['payment_id' => $paymentId, 'partner_payment_id' => $partnerPayment->id]);

                /** @var PartnerAccess|null $partnerAccess */
                $partnerAccess = PartnerAccess::where('partner_payment_id', $partnerPayment->id)->first();
                if (!$partnerAccess) {
                    throw new \RuntimeException("Период с partnerPaymentId {$partnerPayment->id} не найден в базе.");
                }

                $partnerAccess->update(['is_active' => 1]);
                Log::info("Partner subscription: access activated", ['partner_payment_id' => $partnerPayment->id]);
            });
        } catch (\Throwable $e) {
            Log::error("Partner subscription webhook error: ".$e->getMessage());
            return response()->json(['error' => 'Internal server error.'], 500);
        }

        return response()->json(['message' => 'Webhook processed successfully.'], 200);
    }

    // ---- IP utils ----
    private function isAllowedIp(string $ip): bool
    {
        foreach ($this->allowedIps as $allowedIp) {
            if ($this->ipInRange($ip, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (ip2long($ip) & ~((1 << (32 - $bits)) - 1)) === ip2long($subnet);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, (int)$bits);
        }

        return false;
    }

    private function ipv6InRange(string $ip, string $subnet, int $bits): bool
    {
        $ipBin     = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        $mask = str_repeat('f', $bits >> 2);
        switch ($bits % 4) {
            case 1: $mask .= '8'; break;
            case 2: $mask .= 'c'; break;
            case 3: $mask .= 'e'; break;
        }
        $mask = str_pad($mask, 32, '0');
        $maskBin = pack('H*', $mask);

        return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
    }
}
