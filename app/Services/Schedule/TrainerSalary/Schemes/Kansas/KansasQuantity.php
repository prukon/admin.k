<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Kansas;

/**
 * Средние Канзаса: в формуле и UI — целые ученики.
 * В БД колонки *_tenths: 16 учеников → 160 (канон деления на 10 для денег).
 */
final class KansasQuantity
{
    public static function toTenths(string|int|float|null $value): ?int
    {
        return self::toWholeTenths($value);
    }

    /**
     * Только целое число учеников (16 ок, 16.5 нет). Возвращает tenths (16 → 160).
     */
    public static function toWholeTenths(string|int|float|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            if ($value < 0 || $value > 999) {
                return null;
            }

            return $value * 10;
        }

        if (is_float($value)) {
            if (! is_finite($value) || $value < 0 || $value > 999 || $value != floor($value)) {
                return null;
            }

            return ((int) $value) * 10;
        }

        $v = trim(str_replace(["\xc2\xa0", ' '], '', (string) $value));
        if ($v === '' || ! preg_match('/^\d{1,3}$/', $v)) {
            return null;
        }

        return ((int) $v) * 10;
    }

    public static function toTenthsOrFail(string|int|float|null $value): int
    {
        return self::toWholeTenthsOrFail($value);
    }

    public static function toWholeTenthsOrFail(string|int|float|null $value): int
    {
        $tenths = self::toWholeTenths($value);
        if ($tenths === null) {
            throw new \InvalidArgumentException('Некорректное среднее (ожидается целое число учеников).');
        }

        return $tenths;
    }

    public static function formatTenths(int $tenths): string
    {
        return self::formatTenthsAsInt($tenths);
    }

    /**
     * Целое учеников из tenths (160 → 16). Хранимые средние всегда кратны 10.
     */
    public static function formatTenthsAsInt(int $tenths): string
    {
        $neg = $tenths < 0;
        $body = (string) intdiv(abs($tenths), 10);

        return $neg ? '-' . $body : $body;
    }

    /**
     * round(sum / count, 1), затем вверх до целого: 15.04 → 15, 15.1 → 16.
     */
    public static function averageToTenths(int $sum, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        return self::ceilTenthsToWholeTenths(self::roundDiv($sum * 10, $count));
    }

    /**
     * 150 → 150 (15.0), 151 → 160 (15.1 → 16).
     */
    public static function ceilTenthsToWholeTenths(int $tenths): int
    {
        if ($tenths >= 0) {
            return intdiv($tenths + 9, 10) * 10;
        }

        return -intdiv(-$tenths + 9, 10) * 10;
    }

    /**
     * Математическое округление (от 5 вверх) частного numerator/denominator.
     */
    public static function roundDiv(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('Делитель должен быть положительным.');
        }

        if ($numerator >= 0) {
            return intdiv($numerator + intdiv($denominator, 2), $denominator);
        }

        return -intdiv(-$numerator + intdiv($denominator, 2), $denominator);
    }
}
