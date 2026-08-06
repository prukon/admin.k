<?php

namespace App\Support\Payments;

use App\Support\Money;

/**
 * Нормализация суммы в рублях до строки "0.00" (округление до копеек).
 * Делегирует в {@see Money::normalizeRubString}.
 * Используется при Init T‑Bank / Robokassa на границе рублёвого ввода форм.
 */
final class PaymentOutSumNormalizer
{
    public static function normalize(string $value): ?string
    {
        $v = trim(str_replace(["\xc2\xa0", ' '], '', $value));

        return Money::normalizeRubString($v);
    }
}
