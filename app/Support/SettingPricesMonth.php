<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * Месяц вкладки «По месяцам»: подпись «Сентябрь 2026» ↔ YYYY-MM-01.
 * Строгий разбор: неизвестное имя месяца не подменяется текущей датой.
 */
final class SettingPricesMonth
{
    /**
     * @return array<int, string>
     */
    public static function ruMonthNames(): array
    {
        return [
            1 => 'Январь',
            2 => 'Февраль',
            3 => 'Март',
            4 => 'Апрель',
            5 => 'Май',
            6 => 'Июнь',
            7 => 'Июль',
            8 => 'Август',
            9 => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь',
        ];
    }

    /**
     * «Сентябрь 2026» → 2026-09-01. Невалидная подпись → null.
     */
    public static function tryParseLabel(string $label): ?string
    {
        $parts = preg_split('/\s+/u', trim($label)) ?: [];
        if (count($parts) < 2) {
            return null;
        }

        $monthKey = mb_strtolower($parts[0], 'UTF-8');
        $yearRaw = $parts[1];
        if (! preg_match('/^\d{4}$/', $yearRaw)) {
            return null;
        }

        $map = [];
        foreach (self::ruMonthNames() as $num => $name) {
            $map[mb_strtolower($name, 'UTF-8')] = $num;
        }

        if (! isset($map[$monthKey])) {
            return null;
        }

        $year = (int) $yearRaw;
        if ($year < 2000 || $year > 2100) {
            return null;
        }

        return sprintf('%04d-%02d-01', $year, $map[$monthKey]);
    }

    public static function toLabel(string $ymd): string
    {
        $date = Carbon::parse($ymd)->startOfMonth();
        $name = self::ruMonthNames()[(int) $date->month] ?? '';

        return $name.' '.$date->year;
    }

    public static function nextMonth(string $ymd): string
    {
        return Carbon::parse($ymd)->startOfMonth()->addMonth()->format('Y-m-d');
    }

    /**
     * «2026-09-01» → «сентябре» (предложный падеж, для «в сентябре»).
     */
    public static function toPrepositionalMonth(string $ymd): string
    {
        $date = Carbon::parse($ymd)->startOfMonth();
        $names = [
            1 => 'январе',
            2 => 'феврале',
            3 => 'марте',
            4 => 'апреле',
            5 => 'мае',
            6 => 'июне',
            7 => 'июле',
            8 => 'августе',
            9 => 'сентябре',
            10 => 'октябре',
            11 => 'ноябре',
            12 => 'декабре',
        ];

        return $names[(int) $date->month] ?? '';
    }
}
