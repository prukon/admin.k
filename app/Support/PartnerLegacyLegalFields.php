<?php

namespace App\Support;

/**
 * Legacy-поля юр. лица на таблице partners (данные перенесены в partner_legal_entities).
 * Колонки в БД сохранены, но приложение их не читает и не пишет.
 */
final class PartnerLegacyLegalFields
{
    /** @var list<string> */
    public const KEYS = [
        'business_type',
        'organization_name',
        'tax_id',
        'kpp',
        'registration_number',
        'address',
        'bank_name',
        'bank_bik',
        'bank_account',
        'vat',
        'city',
        'zip',
        'ceo',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function strip(array $data): array
    {
        return array_diff_key($data, array_flip(self::KEYS));
    }
}
