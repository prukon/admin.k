<?php

namespace App\Support\Payments;

/**
 * Признак оплаты месячного начисления по публичной СБП-ссылке из email-рассылки
 * («Установка цен → Уведомления», /pm/{code}). Не абонемент /p/{code} (ulp_public_pay).
 */
final class EmailNewsletterPaymentSource
{
    public const LABEL = 'Email рассылка';

    public const FILTER_YES = '1';

    public const FILTER_NO = '0';

    public const META_KEY = 'up_public_pay';

    /**
     * SQL-выражение 0/1 по JSON-meta (TEXT). Никогда не NULL — удобно для WHERE и SELECT.
     * $metaColumn — квалифицированное имя колонки, без пользовательского ввода.
     */
    public static function sqlFlagExpr(string $metaColumn): string
    {
        $key = self::META_KEY;

        return <<<SQL
CASE
  WHEN {$metaColumn} IS NULL OR TRIM({$metaColumn}) = '' OR JSON_VALID({$metaColumn}) = 0 THEN 0
  WHEN JSON_UNQUOTE(JSON_EXTRACT({$metaColumn}, '$.{$key}')) IN ('true', '1') THEN 1
  ELSE 0
END
SQL;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyQueryFilter($query, mixed $raw, string $metaColumn): void
    {
        if (! is_string($raw) && ! is_int($raw)) {
            return;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return;
        }

        $expr = self::sqlFlagExpr($metaColumn);
        if ($value === self::FILTER_YES) {
            $query->whereRaw("({$expr}) = 1");

            return;
        }

        if ($value === self::FILTER_NO) {
            $query->whereRaw("({$expr}) = 0");
        }
    }

    public static function isFromMeta(mixed $meta): bool
    {
        $arr = self::metaToArray($meta);
        if ($arr === []) {
            return false;
        }

        $raw = $arr[self::META_KEY] ?? false;

        return $raw === true || $raw === 1 || $raw === '1';
    }

    public static function labelFromMeta(mixed $meta): string
    {
        return self::isFromMeta($meta) ? self::LABEL : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function metaToArray(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (! is_string($meta) || trim($meta) === '') {
            return [];
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }
}
