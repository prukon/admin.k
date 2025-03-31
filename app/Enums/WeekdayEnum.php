<?php

namespace App\Enums;

enum WeekdayEnum: int
{
case MONDAY = 1;
case TUESDAY = 2;
case WEDNESDAY = 3;
case THURSDAY = 4;
case FRIDAY = 5;
case SATURDAY = 6;
case SUNDAY = 7;

    public function label(): string
{
    return match($this) {
    self::MONDAY => 'пн',
            self::TUESDAY => 'вт',
            self::WEDNESDAY => 'ср',
            self::THURSDAY => 'чт',
            self::FRIDAY => 'пт',
            self::SATURDAY => 'сб',
            self::SUNDAY => 'вс',
        };
    }
}
