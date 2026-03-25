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
        $hadUnmappedSource = false;
        if ($source !== null && $source !== '') {
            $intent = $this->mapSourceToIntent($source);
            if ($intent === null) {
                $hadUnmappedSource = true;
            }
        }

        // В поле Pan иногда приходит маска телефона (+7(9**)…), а не PAN карты — типично для СБП/T‑Pay с той же формы Init=card.
        if ($intent === null && $init === 'card' && ! $hadUnmappedSource
            && ! empty($webhook['Pan']) && $this->panLooksLikePhoneBinding((string) $webhook['Pan'])) {
            $intent = 'tpay';
        }

        // Pan без ExpDate часто приходит и для СБП/кошелька/QR на той же форме — не считаем это картой.
        // Если в DATA уже есть source, но мы его не распознали — не додумываем способ по Pan.
        if (! $hadUnmappedSource && $intent === null
            && ! empty($webhook['Pan']) && ! empty($webhook['ExpDate'])
            && ! $this->panLooksLikePhoneBinding((string) $webhook['Pan'])) {
            $intent = 'card';
        }

        // Та же сценарная форма Init=card: маска Pan без срока — типично не классическая карта (QR/кошелёк на странице банка).
        if ($intent === null && $init === 'card' && ! $hadUnmappedSource
            && ! empty($webhook['Pan']) && empty($webhook['ExpDate'])) {
            $intent = 'tpay';
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
        foreach (['Source', 'source', 'PaymentSource', 'paymentSource'] as $rootKey) {
            if (! empty($webhook[$rootKey]) && is_string($webhook[$rootKey])) {
                return $webhook[$rootKey];
            }
        }

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

        if (str_contains($s, 'sbp')
            || str_contains($s, 'сбп')
            || str_contains($s, 'nspk')
            || str_contains($s, 'fps')) {
            return 'sbp_qr';
        }

        if (str_contains($s, 'tpay')
            || str_contains($s, 't-pay')
            || str_contains($s, 'tinkoffpay')
            || str_contains($s, 'тинькоф')
            || str_contains($s, 'mirpay')
            || (bool) preg_match('/\bmir\b/u', $s)
            || (bool) preg_match('/\bqr\b/u', $s)
            || str_contains($s, 'wallet')
            || str_contains($s, 'кошел')) {
            return 'tpay';
        }

        if ((bool) preg_match('/\bcards?\b/u', $s)
            || str_contains($s, 'карт')
            || (bool) preg_match('/\b(visa|mastercard|maestro|union\s*pay)\b/u', $s)) {
            return 'card';
        }

        return null;
    }

    private function panLooksLikePhoneBinding(string $pan): bool
    {
        $p = trim($pan);
        if ($p === '') {
            return false;
        }
        if (preg_match('/^\s*\+?\s*7\s*\(/u', $p)) {
            return true;
        }
        if (preg_match('/^\s*\+/u', $p) && (str_contains($p, '(') || str_contains($p, ')'))) {
            return true;
        }

        return false;
    }
}
