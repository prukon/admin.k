<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ФИО в родительном падеже: после trim и схлопывания пробелов — не короче 10 символов и не меньше трёх слов.
 * Пустая строка пропускается (обязательность задаёт required).
 */
final class GenitiveFullName implements ValidationRule
{
    public const MESSAGE = 'Укажите ФИО полностью в родительном падеже: фамилия, имя и отчество.';

    public const HINT = 'Фамилия, имя и отчество, например: Иванова Ивана Ивановича';

    public const MIN_LENGTH = 10;

    public const MIN_WORDS = 3;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! self::isComplete($value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function isComplete(string $value): bool
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return true;
        }

        if (mb_strlen($normalized) < self::MIN_LENGTH) {
            return false;
        }

        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) && count($words) >= self::MIN_WORDS;
    }

    public static function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value));

        return is_string($collapsed) ? $collapsed : trim($value);
    }
}
