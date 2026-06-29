<?php

namespace App\Enums;

/**
 * Коды Items.Vat для CloudKassir (/kkt/receipt).
 *
 * @see docs/CloudKassir/cloudkassir-docs.md
 */
enum CloudKassirVatRate: int
{
    case VAT_0 = 0;
    case VAT_5 = 5;
    case VAT_7 = 7;
    case VAT_10 = 10;
    case VAT_20 = 20;
    case VAT_22 = 22;
    case VAT_CALC_5 = 105;
    case VAT_CALC_7 = 107;
    case VAT_CALC_10 = 110;
    case VAT_CALC_20 = 120;
    case VAT_CALC_22 = 122;

    public function label(): string
    {
        return match ($this) {
            self::VAT_0 => 'НДС 0%',
            self::VAT_5 => 'НДС 5%',
            self::VAT_7 => 'НДС 7%',
            self::VAT_10 => 'НДС 10%',
            self::VAT_20 => 'НДС 20%',
            self::VAT_22 => 'НДС 22%',
            self::VAT_CALC_5 => 'Расчётный НДС 5/105',
            self::VAT_CALC_7 => 'Расчётный НДС 7/107',
            self::VAT_CALC_10 => 'Расчётный НДС 10/110',
            self::VAT_CALC_20 => 'Расчётный НДС 20/120',
            self::VAT_CALC_22 => 'Расчётный НДС 22/122',
        };
    }

    /**
     * @return list<int>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labelFor(?int $value): string
    {
        if ($value === null) {
            return 'НДС не облагается';
        }

        return self::tryFrom($value)?->label() ?? (string) $value;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function selectOptions(): array
    {
        return array_map(
            static fn (self $rate) => ['value' => $rate->value, 'label' => $rate->label()],
            self::cases(),
        );
    }
}
