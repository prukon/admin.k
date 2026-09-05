<?php

namespace App\Services\Tinkoff;

use App\Models\PaymentSystem;
use RuntimeException;

/**
 * Глобальный терминал обычного эквайринга T‑Bank (без мультирасчётов).
 */
final class TbankAcquiringTerminalConfig
{
    public const NAME = 'tbank_acquiring';

    public const SBP_MIN_RUB = 10;

    public const SBP_MAX_RUB = 1_000_000;

    public static function globalRecord(): ?PaymentSystem
    {
        return PaymentSystem::query()
            ->whereNull('partner_id')
            ->where('name', self::NAME)
            ->first();
    }

    public static function isActive(): bool
    {
        $ps = self::globalRecord();

        return $ps !== null
            && $ps->is_enabled
            && $ps->is_connected;
    }

    /**
     * @return array{
     *     terminal_key: string,
     *     password: string,
     *     base_url: string,
     *     notify_url: string
     * }
     */
    public static function paymentConfig(): array
    {
        $ps = self::globalRecord();
        if ($ps === null || ! $ps->is_connected) {
            throw new RuntimeException('T‑Bank acquiring terminal is not configured');
        }

        $s = $ps->settings;
        $isTest = (bool) $ps->test_mode;

        return [
            'terminal_key' => (string) ($s['terminal_key'] ?? ''),
            'password' => (string) ($s['token_password'] ?? ''),
            'base_url' => $isTest ? 'https://rest-api-test.tinkoff.ru' : 'https://securepay.tinkoff.ru',
            'notify_url' => url('/webhooks/tinkoff/acquiring'),
        ];
    }

    public static function tryPaymentConfig(): ?array
    {
        try {
            return self::paymentConfig();
        } catch (RuntimeException) {
            return null;
        }
    }
}
