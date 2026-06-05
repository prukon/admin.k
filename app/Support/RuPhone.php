<?php

namespace App\Support;

final class RuPhone
{
    /**
     * Нормализует номер к 11 цифрам с ведущей 7 (79110263811).
     */
    public static function normalizeDigits(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        if (strlen($digits) > 11 && str_starts_with($digits, '7')) {
            $digits = substr($digits, 0, 11);
        }

        if (strlen($digits) !== 11 || !str_starts_with($digits, '7')) {
            return $digits !== '' ? $digits : null;
        }

        return $digits;
    }

    /**
     * Формат для input с маской Inputmask: +7 (999) 999-99-99
     */
    public static function formatForInput(?string $phone): string
    {
        $digits = self::normalizeDigits($phone);
        if ($digits === null || strlen($digits) !== 11) {
            return (string) ($phone ?? '');
        }

        return sprintf(
            '+7 (%s) %s-%s-%s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 2),
            substr($digits, 9, 2),
        );
    }
}
