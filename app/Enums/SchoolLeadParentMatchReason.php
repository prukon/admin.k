<?php

namespace App\Enums;

enum SchoolLeadParentMatchReason: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Name = 'name';

    public function bannerLabel(): string
    {
        return match ($this) {
            self::Email => 'почте',
            self::Phone => 'номеру телефона',
            self::Name  => 'фамилии и имени',
        };
    }
}
