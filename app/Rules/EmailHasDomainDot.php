<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Email с @ и точкой в домене: name@mail.ru проходит, name@mail — нет.
 */
final class EmailHasDomainDot implements ValidationRule
{
    public const MESSAGE = 'Укажите корректный email с точкой в домене (например, name@mail.ru).';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail(self::MESSAGE);

            return;
        }

        $email = trim($value);
        $at = strrpos($email, '@');
        if ($at === false) {
            return;
        }

        $domain = substr($email, $at + 1);
        if ($domain === '' || ! str_contains($domain, '.')) {
            $fail(self::MESSAGE);
        }
    }
}
