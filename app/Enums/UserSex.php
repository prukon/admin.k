<?php

namespace App\Enums;

enum UserSex: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Мужской',
            self::Female => 'Женский',
        };
    }

    public static function labelFor(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'Не указано';
        }

        return self::tryFrom($value)?->label() ?? 'Не указано';
    }
}
