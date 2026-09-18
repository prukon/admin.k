<?php

namespace App\Services\Tinkoff;

use App\Models\TinkoffPayout;

final class TinkoffPayoutBankError
{
    public static function summary(TinkoffPayout $payout): ?string
    {
        foreach ([$payout->payload_init, $payout->payload_payment, $payout->payload_state] as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $text = self::fromPayload($payload);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): ?string
    {
        if (($payload['cancelled_by_refund'] ?? false) === true
            || ($payload['rejected_reason'] ?? null) === 'cancelled_by_refund'
        ) {
            return 'Выплата отменена из‑за возврата';
        }

        if (($payload['rejected_reason'] ?? null) === 'net_amount_zero') {
            return 'Сумма к выплате 0 ₽ после комиссий';
        }

        $success = $payload['Success'] ?? null;
        $code = isset($payload['ErrorCode']) ? trim((string) $payload['ErrorCode']) : '';
        $message = trim((string) ($payload['Message'] ?? ''));
        $details = trim((string) ($payload['Details'] ?? ''));

        $failed = $success === false || ($code !== '' && $code !== '0');
        if (! $failed) {
            return null;
        }

        $parts = [];
        if ($code !== '' && $code !== '0') {
            $parts[] = 'Код '.$code;
        }
        if ($message !== '') {
            $parts[] = $message;
        }
        if ($details !== '' && $details !== $message) {
            $parts[] = $details;
        }

        return $parts === [] ? 'Банк отклонил выплату' : implode('. ', $parts);
    }
}
