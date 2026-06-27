<?php

namespace App\Enums;

enum PartnerLegalEntityBusinessType: string
{
    case OOO = 'OOO';
    case IP = 'IP';
    case ANO = 'ANO';
    case NKO = 'NKO';

    public function label(): string
    {
        return match ($this) {
            self::OOO => 'ООО',
            self::IP => 'ИП',
            self::ANO => 'АНО',
            self::NKO => 'НКО',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labelFor(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return self::tryFrom($value)?->label() ?? $value;
    }

    /**
     * Тип организации для API sm-register T‑Bank.
     */
    public function smRegisterApiType(): string
    {
        return match ($this) {
            self::OOO => 'company',
            self::IP => 'individual_entrepreneur',
            self::ANO, self::NKO => 'non_commercial_organization',
        };
    }

    public function requiresKpp(): bool
    {
        return $this === self::OOO;
    }
}
