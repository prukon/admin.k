<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pricing;

use App\Models\User;
use App\Services\Pricing\UserPercentDiscount;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Персональная скидка: вариант А, снимок vs override, tooltip.
 *
 * @see /docs/documentation/money.html#discount
 */
final class UserPercentDiscountTest extends TestCase
{
    #[DataProvider('payableProvider')]
    public function test_payable_cents_variant_a(int $priceCents, int $percent, int $expected): void
    {
        $this->assertSame($expected, UserPercentDiscount::payableCents($priceCents, $percent));
    }

    public static function payableProvider(): array
    {
        return [
            '1000 rub 10%' => [100000, 10, 90000],
            '0 percent' => [100000, 0, 100000],
            '100 percent' => [100000, 100, 0],
            '999 rub 10%' => [99900, 10, 89910],
        ];
    }

    public function test_percent_clamps_and_ignores_zero(): void
    {
        $user = new User(['discount_percent' => 0]);
        $this->assertSame(0, UserPercentDiscount::percent($user));
        $this->assertFalse(UserPercentDiscount::hasDiscount($user));

        $user->discount_percent = 10;
        $this->assertSame(10, UserPercentDiscount::percent($user));
        $this->assertTrue(UserPercentDiscount::hasDiscount($user));

        $user->discount_percent = 150;
        $this->assertSame(100, UserPercentDiscount::percent($user));

        $this->assertSame(0, UserPercentDiscount::percent(null));
    }

    public function test_snapshot_if_matches_catalog_keeps_percent_only_when_formula_matches(): void
    {
        $user = new User([
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);

        $match = UserPercentDiscount::snapshotIfMatchesCatalog(100000, 90000, $user);
        $this->assertSame(10, $match['discount_percent']);
        $this->assertSame('Льгота', $match['discount_comment']);

        $override = UserPercentDiscount::snapshotIfMatchesCatalog(100000, 85000, $user);
        $this->assertNull($override['discount_percent']);
        $this->assertNull($override['discount_comment']);
    }

    public function test_tooltip_uses_applied_percent_and_reason(): void
    {
        $this->assertSame('Скидка 10%. Льгота семьи', UserPercentDiscount::tooltip(10, 'Льгота семьи'));
        $this->assertSame('Скидка 10%.', UserPercentDiscount::tooltip(10, '  '));
        $this->assertNull(UserPercentDiscount::tooltip(0, 'Льгота'));
        $this->assertNull(UserPercentDiscount::tooltip(null, 'Льгота'));
    }
}
