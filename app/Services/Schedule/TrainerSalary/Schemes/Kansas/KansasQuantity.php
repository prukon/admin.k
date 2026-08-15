<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Kansas;

/**
 * Средние Канзаса: десятые доли ученика (16.0 → 160 tenths).
 * Деньги остаются в копейках; десятые участвуют в формуле через целочисленное деление на 10.
 */
final class KansasQuantity
{
    public static function toTenths(string|int|float|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            if ($value < 0) {
                return null;
            }

            return $value * 10;
        }

        if (is_float($value)) {
            if (! is_finite($value) || $value < 0) {
                return null;
            }

            $value = number_format($value, 1, '.', '');
        }

        $v = trim(str_replace(["\xc2\xa0", ' '], '', (string) $value));
        $v = str_replace(',', '.', $v);
        if ($v === '' || ! preg_match('/^\d{1,3}(\.\d)?$/', $v)) {
            return null;
        }

        if (! str_contains($v, '.')) {
            return ((int) $v) * 10;
        }

        [$whole, $frac] = explode('.', $v, 2);

        return ((int) $whole) * 10 + (int) $frac;
    }

    public static function toTenthsOrFail(string|int|float|null $value): int
    {
        $tenths = self::toTenths($value);
        if ($tenths === null) {
            throw new \InvalidArgumentException('Некорректное среднее (ожидается число с одной десятой).');
        }

        return $tenths;
    }

    public static function formatTenths(int $tenths): string
    {
        $neg = $tenths < 0;
        $abs = abs($tenths);
        $body = intdiv($abs, 10) . '.' . (string) ($abs % 10);

        return $neg ? '-' . $body : $body;
    }

    /**
     * Целая часть десятых для ячейки (16.5 → 16). Расчёт и БД не трогает.
     */
    public static function formatTenthsAsInt(int $tenths): string
    {
        return (string) intdiv($tenths, 10);
    }

    /**
     * round(sum / count, 1) как tenths: round(sum * 10 / count) half-up.
     */
    public static function averageToTenths(int $sum, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        return self::roundDiv($sum * 10, $count);
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
