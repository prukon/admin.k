<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('toCentsProvider')]
    public function test_to_cents(string|int|float $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::toCents($input));
    }

    public static function toCentsProvider(): array
    {
        return [
            'int rubles' => [8000, 800000],
            'string int' => ['8000', 800000],
            'string with kopecks' => ['8000.50', 800050],
            'comma decimal' => ['899,10', 89910],
            'spaces thousands' => ['8 000.50', 800050],
            'round half up 10.005' => ['10.005', 1001],
            'round down 10.004' => ['10.004', 1000],
            'float' => [33.33, 3333],
            'empty' => ['', null],
            'invalid' => ['12.34.56', null],
            'negative string' => ['-1', null],
        ];
    }

    public function test_from_cents_and_roundtrip(): void
    {
        $this->assertSame('8000.00', Money::fromCents(800000));
        $this->assertSame('8000.50', Money::fromCents(800050));
        $this->assertSame('0.01', Money::fromCents(1));
        $this->assertSame(89910, Money::toCents(Money::fromCents(89910)));
    }

    public function test_format_rub_hides_zero_kopecks(): void
    {
        $this->assertSame('8 000', Money::formatRub(800000));
        $this->assertSame('8 000,50', Money::formatRub(800050));
        $this->assertSame('899,10', Money::formatRub(89910));
        $this->assertSame('0', Money::formatRub(0));
        $this->assertSame('8 000 ₽', Money::formatRub(800000, ' ₽'));
    }

    public function test_format_rub_fixed_always_kopecks(): void
    {
        $this->assertSame('8 000,00', Money::formatRubFixed(800000));
        $this->assertSame('8 000,50', Money::formatRubFixed(800050));
    }

    /**
     * Вариант А: скидка = round(price × % / 100), к оплате = price − скидка.
     */
    #[DataProvider('discountProvider')]
    public function test_discount_variant_a(
        int $priceCents,
        float|int|string $percent,
        int $expectedDiscount,
        int $expectedPayable
    ): void {
        $this->assertSame($expectedDiscount, Money::discountCents($priceCents, $percent));
        $this->assertSame($expectedPayable, Money::payableAfterDiscountCents($priceCents, $percent));
    }

    public static function discountProvider(): array
    {
        return [
            '1000 rub 10%' => [100000, 10, 10000, 90000],
            '1500 rub 7%' => [150000, 7, 10500, 139500],
            '999 rub 10%' => [99900, 10, 9990, 89910],
            '100 rub 17%' => [10000, 17, 1700, 8300],
            // 33.33 × 5% = 1.6665 → 1.67
            '33.33 rub 5%' => [3333, 5, 167, 3166],
            // Спорный: 1.05 × 50% = 0.525 → скидка 0.53, к оплате 0.52
            '1.05 rub 50%' => [105, 50, 53, 52],
            'zero percent' => [10000, 0, 0, 10000],
            '100 percent' => [10000, 100, 10000, 0],
        ];
    }

    public function test_normalize_rub_string_matches_legacy_rules(): void
    {
        $this->assertSame('10.01', Money::normalizeRubString('10.005'));
        $this->assertSame('10.00', Money::normalizeRubString('10.004'));
        $this->assertSame('0.01', Money::normalizeRubString('0.005'));
    }
}
