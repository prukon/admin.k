<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RubAmountInWords;
use PHPUnit\Framework\TestCase;

final class RubAmountInWordsTest extends TestCase
{
    public function test_spells_rubles_and_kopecks(): void
    {
        $this->assertSame('Ноль рублей 00 копеек', RubAmountInWords::spell(0));
        $this->assertSame('Один рубль 00 копеек', RubAmountInWords::spell(100));
        $this->assertSame('Один рубль 01 копейка', RubAmountInWords::spell(101));
        $this->assertSame('Два рубля 02 копейки', RubAmountInWords::spell(202));
        $this->assertSame('Пять рублей 05 копеек', RubAmountInWords::spell(505));
        $this->assertSame('Одиннадцать рублей 11 копеек', RubAmountInWords::spell(1111));
        $this->assertSame('Двадцать один рубль 00 копеек', RubAmountInWords::spell(2100));
        $this->assertSame('Одна тысяча рублей 00 копеек', RubAmountInWords::spell(100_000));
        $this->assertSame('Две тысячи рублей 00 копеек', RubAmountInWords::spell(200_000));
        $this->assertSame('Пять тысяч рублей 00 копеек', RubAmountInWords::spell(500_000));
        $this->assertSame('Один миллион рублей 00 копеек', RubAmountInWords::spell(100_000_000));
    }
}
