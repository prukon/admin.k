<?php

namespace App\Services\Tinkoff;

use App\Models\PaymentSystem;
use RuntimeException;

/**
 * Единая точка чтения глобальных ключей терминала T‑Bank (мультирасчёты).
 */
final class TbankTerminalConfig
{
    public static function globalRecord(): ?PaymentSystem
    {
        return PaymentSystem::query()
            ->whereNull('partner_id')
            ->where('name', 'tbank')
            ->first();
    }

    public static function isGloballyActive(): bool
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
     *     success_url: string,
     *     fail_url: string,
     *     notify_url: string
     * }
     */
    public static function paymentConfig(): array
    {
        $ps = self::globalRecord();
        if ($ps === null || ! $ps->is_connected) {
            throw new RuntimeException('T‑Bank terminal is not configured');
        }

        $s = $ps->settings;
        $isTest = (bool) $ps->test_mode;

        return [
            'terminal_key' => (string) ($s['terminal_key'] ?? ''),
            'password' => (string) ($s['token_password'] ?? ''),
            'base_url' => $isTest ? 'https://rest-api-test.tinkoff.ru' : 'https://securepay.tinkoff.ru',
            'success_url' => url('/payments/tinkoff/{order}/success'),
            'fail_url' => url('/payments/tinkoff/{order}/fail'),
            'notify_url' => url('/webhooks/tinkoff/payments'),
        ];
    }

    /**
     * @return array{terminal_key: string, password: string, base_url: string}
     */
    public static function e2cConfig(): array
    {
        $ps = self::globalRecord();
        if ($ps === null || ! $ps->is_connected) {
            throw new RuntimeException('T‑Bank e2c terminal is not configured');
        }

        $s = $ps->settings;
        $isTest = (bool) $ps->test_mode;

        return [
            'terminal_key' => (string) ($s['e2c_terminal_key'] ?? ''),
            'password' => (string) ($s['e2c_token_password'] ?? ''),
            'base_url' => $isTest ? 'https://rest-api-test.tinkoff.ru' : 'https://securepay.tinkoff.ru',
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

    public static function tryE2cConfig(): ?array
    {
        try {
            return self::e2cConfig();
        } catch (RuntimeException) {
            return null;
        }
    }
}
