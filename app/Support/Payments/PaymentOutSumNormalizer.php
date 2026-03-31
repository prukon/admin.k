<?php

namespace App\Support\Payments;

/**
 * Нормализация суммы в рублях до строки "0.00" (округление до копеек по 3-му знаку).
 * Используется при Init T‑Bank, Robokassa и при чтении цены из users_prices.
 */
final class PaymentOutSumNormalizer
{
    public static function normalize(string $value): ?string
    {
        $v = trim(str_replace(',', '.', $value));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d+(\.\d{1,6})?$/', $v)) {
            return null;
        }

        $a = $v;
        $b = '';
        if (str_contains($v, '.')) {
            [$a, $b] = explode('.', $v, 2);
        }

        $a = ltrim($a, '0');
        if ($a === '') {
            $a = '0';
        }

        $b = str_pad($b, 6, '0');
        $cents = (int) substr($b, 0, 2);
        $third = (int) substr($b, 2, 1);

        if ($third >= 5) {
            $cents++;
            if ($cents >= 100) {
                $cents = 0;
                $a = (string) ((int) $a + 1);
            }
        }

        return $a . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }
}
