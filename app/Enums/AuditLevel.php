<?php

namespace App\Enums;

enum AuditLevel: string
{
    case Info = 'info';
    case Security = 'security';
    case Integration = 'integration';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Информация',
            self::Security => 'Безопасность',
            self::Integration => 'Интеграция',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case) => $case->value,
            self::cases()
        );
    }

    /**
     * @return array<string, string> level value => label (UI-фильтры)
     */
    public static function labelsForUi(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
