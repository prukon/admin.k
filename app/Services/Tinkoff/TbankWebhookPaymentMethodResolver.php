<?php

namespace App\Services\Tinkoff;

/**
 * Фактический способ оплаты по нотификации T‑Bank (см. Data.source на платёжной форме банка)
 * и полям вроде Pan. Значения intent: card | sbp_qr | tpay — как в payment_intents.
 * Для строки tinkoff_payments / правил комиссий: card | sbp | tpay.
 */
final class TbankWebhookPaymentMethodResolver
{
    /**
     * @return array{intent: 'card'|'sbp_qr'|'tpay'|null, tinkoff: 'card'|'sbp'|'tpay'|null}
     */
    public function resolve(array $webhook, ?string $initTinkoffMethod): array
    {
        $init = $initTinkoffMethod !== null ? strtolower(trim($initTinkoffMethod)) : '';

        $intent = null;

        $source = $this->extractSource($webhook);
        if ($source !== null && $source !== '') {
            $intent = $this->mapSourceToIntent($source);
        }

        if ($intent === null && ! empty($webhook['Pan'])) {
            $intent = 'card';
        }

        if ($intent === null && $init === 'sbp') {
            $intent = 'sbp_qr';
        }

        if ($intent === null && $init === 'tpay') {
            $intent = 'tpay';
        }

        if ($intent === null && $init === 'card') {
            $intent = 'card';
        }

        $tinkoff = $this->intentToTinkoff($intent);

        return ['intent' => $intent, 'tinkoff' => $tinkoff];
    }

    private function intentToTinkoff(?string $intent): ?string
    {
        return match ($intent) {
            'sbp_qr' => 'sbp',
            'tpay' => 'tpay',
            'card' => 'card',
            default => null,
        };
    }

    private function extractSource(array $webhook): ?string
    {
        $data = $webhook['Data'] ?? $webhook['DATA'] ?? null;
        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if (! is_array($data)) {
            return null;
        }
        foreach (['source', 'Source'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }

    private function mapSourceToIntent(string $source): ?string
    {
        $s = mb_strtolower(trim($source));

        if (str_contains($s, 'tpay')
            || str_contains($s, 't-pay')
            || str_contains($s, 'tinkoffpay')
            || str_contains($s, 'тинькоф')
            || str_contains($s, 'mirpay')) {
            return 'tpay';
        }

        if (str_contains($s, 'sbp')
            || str_contains($s, 'сбп')
            || str_contains($s, 'nspk')
            || str_contains($s, 'fps')) {
            return 'sbp_qr';
        }

        if (str_contains($s, 'card')
            || str_contains($s, 'карт')
            || (str_contains($s, 'mir') && str_contains($s, 'card'))) {
            return 'card';
        }

        return null;
    }
}
