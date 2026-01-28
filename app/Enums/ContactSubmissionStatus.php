<?php

namespace App\Enums;

enum ContactSubmissionStatus: string
{
    case New        = 'new';
    case Processing = 'processing';
    case Sale       = 'sale';
    case Rejected   = 'rejected';
    case Spam       = 'spam';

    public static function label(string $value): string
    {
        return match ($value) {
            self::New->value        => 'Новый',
            self::Processing->value => 'Обработка',
            self::Sale->value       => 'Продажа',
            self::Rejected->value   => 'Отказ',
            self::Spam->value       => 'Спам',
            default                 => $value,
        };
    }

    public static function values(): array
    {
        return array_map(
            fn (self $case) => $case->value,
            self::cases()
        );
    }
}
