<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Сумма в копейках прописью: «Одна тысяча рублей 00 копеек».
 */
final class RubAmountInWords
{
    /** @var array<int, string> */
    private const ONES_M = [
        1 => 'один',
        2 => 'два',
        3 => 'три',
        4 => 'четыре',
        5 => 'пять',
        6 => 'шесть',
        7 => 'семь',
        8 => 'восемь',
        9 => 'девять',
    ];

    /** @var array<int, string> */
    private const ONES_F = [
        1 => 'одна',
        2 => 'две',
        3 => 'три',
        4 => 'четыре',
        5 => 'пять',
        6 => 'шесть',
        7 => 'семь',
        8 => 'восемь',
        9 => 'девять',
    ];

    /** @var array<int, string> */
    private const TEENS = [
        10 => 'десять',
        11 => 'одиннадцать',
        12 => 'двенадцать',
        13 => 'тринадцать',
        14 => 'четырнадцать',
        15 => 'пятнадцать',
        16 => 'шестнадцать',
        17 => 'семнадцать',
        18 => 'восемнадцать',
        19 => 'девятнадцать',
    ];

    /** @var array<int, string> */
    private const TENS = [
        2 => 'двадцать',
        3 => 'тридцать',
        4 => 'сорок',
        5 => 'пятьдесят',
        6 => 'шестьдесят',
        7 => 'семьдесят',
        8 => 'восемьдесят',
        9 => 'девяносто',
    ];

    /** @var array<int, string> */
    private const HUNDREDS = [
        1 => 'сто',
        2 => 'двести',
        3 => 'триста',
        4 => 'четыреста',
        5 => 'пятьсот',
        6 => 'шестьсот',
        7 => 'семьсот',
        8 => 'восемьсот',
        9 => 'девятьсот',
    ];

    public static function spell(int $cents): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Сумма в копейках не может быть отрицательной.');
        }

        $rubles = intdiv($cents, 100);
        $kopecks = $cents % 100;
        $rubleWords = $rubles === 0 ? 'ноль' : self::spellInteger($rubles);

        $text = $rubleWords.' '.self::plural($rubles, 'рубль', 'рубля', 'рублей')
            .' '.sprintf('%02d', $kopecks).' '.self::plural($kopecks, 'копейка', 'копейки', 'копеек');

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private static function spellInteger(int $value): string
    {
        $millions = intdiv($value, 1_000_000);
        $thousands = intdiv($value % 1_000_000, 1000);
        $rest = $value % 1000;

        $parts = [];
        if ($millions > 0) {
            $parts[] = self::triplet($millions, false);
            $parts[] = self::plural($millions, 'миллион', 'миллиона', 'миллионов');
        }
        if ($thousands > 0) {
            $parts[] = self::triplet($thousands, true);
            $parts[] = self::plural($thousands, 'тысяча', 'тысячи', 'тысяч');
        }
        if ($rest > 0) {
            $parts[] = self::triplet($rest, false);
        }

        return implode(' ', $parts);
    }

    private static function triplet(int $value, bool $female): string
    {
        $value %= 1000;
        $parts = [];
        $hundreds = intdiv($value, 100);
        $tail = $value % 100;

        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }

        if ($tail >= 10 && $tail <= 19) {
            $parts[] = self::TEENS[$tail];
        } else {
            $tens = intdiv($tail, 10);
            $ones = $tail % 10;
            if ($tens > 0) {
                $parts[] = self::TENS[$tens];
            }
            if ($ones > 0) {
                $parts[] = $female ? self::ONES_F[$ones] : self::ONES_M[$ones];
            }
        }

        return implode(' ', $parts);
    }

    private static function plural(int $value, string $one, string $two, string $five): string
    {
        $n = abs($value) % 100;
        if ($n >= 11 && $n <= 14) {
            return $five;
        }

        return match ($n % 10) {
            1 => $one,
            2, 3, 4 => $two,
            default => $five,
        };
    }
}
