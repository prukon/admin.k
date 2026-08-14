<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Services\Schedule\TrainerSalary\Schemes\Kansas\KansasQuantity;
use App\Services\Schedule\TrainerSalary\Schemes\Kansas\KansasTrainerSalaryCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class KansasTrainerSalaryCalculatorTest extends TestCase
{
    public function test_worked_example_totals_41000_rubles(): void
    {
        $calculator = new KansasTrainerSalaryCalculator();

        $groupA = $calculator->computeGroup(
            10,
            140,
            160,
            100000,
            80000,
            10000,
        );
        $groupB = $calculator->computeGroup(
            10,
            250,
            180,
            100000,
            80000,
            10000,
        );

        $this->assertSame(140, $groupA['fact_avg_tenths']);
        $this->assertSame(-20, $groupA['diff_tenths']);
        $this->assertSame(60000, $groupA['premium_cents']);
        $this->assertSame(160000, $groupA['pay_per_training_cents']);
        $this->assertSame(1600000, $groupA['group_total_cents']);

        $this->assertSame(250, $groupB['fact_avg_tenths']);
        $this->assertSame(70, $groupB['diff_tenths']);
        $this->assertSame(150000, $groupB['premium_cents']);
        $this->assertSame(250000, $groupB['pay_per_training_cents']);
        $this->assertSame(2500000, $groupB['group_total_cents']);

        $this->assertSame(4100000, $groupA['group_total_cents'] + $groupB['group_total_cents']);
    }

    public function test_premium_floors_at_zero(): void
    {
        $calculator = new KansasTrainerSalaryCalculator();

        $group = $calculator->computeGroup(
            1,
            1,
            200,
            100000,
            10000,
            100000,
        );

        $this->assertSame(10, $group['fact_avg_tenths']);
        $this->assertSame(-190, $group['diff_tenths']);
        $this->assertSame(0, $group['premium_cents']);
        $this->assertSame(100000, $group['pay_per_training_cents']);
        $this->assertSame(100000, $group['group_total_cents']);
    }

    #[DataProvider('tenthsProvider')]
    public function test_quantity_tenths(string|int|float $input, int $expected): void
    {
        $this->assertSame($expected, KansasQuantity::toTenthsOrFail($input));
    }

    /**
     * @return array<string, array{0: string|int|float, 1: int}>
     */
    public static function tenthsProvider(): array
    {
        return [
            'int 16' => [16, 160],
            'string 16.5' => ['16.5', 165],
            'comma' => ['16,0', 160],
            'zero' => [0, 0],
        ];
    }
}
