<?php

namespace App\Enums;

enum SchoolLeadSource: string
{
    case Widget  = 'widget';
    case Landing = 'landing';

    public static function label(string $value): string
    {
        return match ($value) {
            self::Widget->value  => 'Виджет',
            self::Landing->value => 'Страница заявки',
            default              => $value,
        };
    }
}
