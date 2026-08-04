<?php

declare(strict_types=1);

namespace App\Services\Postpay;

use Carbon\Carbon;

/**
 * Календарный месяц постоплаты в Europe/Moscow.
 */
final class PostpayMonth
{
    public const TIMEZONE = 'Europe/Moscow';

    /**
     * Первое число месяца по дате (Y-m-d или datetime), TZ Europe/Moscow.
     */
    public static function firstDayFromDate(string $dateYmd): string
    {
        return Carbon::parse($dateYmd, self::TIMEZONE)->startOfMonth()->format('Y-m-d');
    }

    /**
     * @return array{0: Carbon, 1: Carbon} [startOfMonth, endOfMonth] в Europe/Moscow
     */
    public static function bounds(string $monthFirstDayYmd): array
    {
        $start = Carbon::parse($monthFirstDayYmd, self::TIMEZONE)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    /**
     * Дата, с которой родителю доступна оплата за месяц начисления (1-е следующего месяца).
     */
    public static function payAvailableFrom(string $billingMonthFirstDayYmd): Carbon
    {
        return Carbon::parse($billingMonthFirstDayYmd, self::TIMEZONE)
            ->startOfMonth()
            ->addMonthNoOverflow()
            ->startOfDay();
    }

    public static function isPayAvailableNow(string $billingMonthFirstDayYmd, ?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now(self::TIMEZONE);

        return $now->greaterThanOrEqualTo(self::payAvailableFrom($billingMonthFirstDayYmd));
    }

    /**
     * Человекочитаемая дата разблокировки оплаты: «1 сентября 2026».
     */
    public static function payAvailableFromLabel(string $billingMonthFirstDayYmd): string
    {
        $date = self::payAvailableFrom($billingMonthFirstDayYmd);
        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];

        $monthName = $months[(int) $date->month] ?? $date->format('m');

        return $date->day.' '.$monthName.' '.$date->year;
    }
}
