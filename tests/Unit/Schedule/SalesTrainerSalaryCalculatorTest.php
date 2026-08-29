<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Services\Schedule\TrainerSalary\Schemes\Sales\SalesTrainerSalaryCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SalesTrainerSalaryCalculatorTest extends TestCase
{
    public function test_worked_example_salary_plus_percent_plus_bonuses_minus_deductions(): void
    {
        $calculator = new SalesTrainerSalaryCalculator();

        $result = $calculator->compute(
            500000,
            800000,
            200000,
            10,
            10000,
            5000,
        );

        $this->assertSame(1000000, $result['sales_base_cents']);
        $this->assertSame(100000, $result['commission_cents']);
        $this->assertSame(605000, $result['total_cents']);
    }

    public function test_zero_percent_gives_only_salary_and_adjustments(): void
    {
        $calculator = new SalesTrainerSalaryCalculator();

        $result = $calculator->compute(150000, 999999, 1, 0, 2000, 1000);

        $this->assertSame(1000000, $result['sales_base_cents']);
        $this->assertSame(0, $result['commission_cents']);
        $this->assertSame(151000, $result['total_cents']);
    }

    public function test_integer_division_drops_remainder(): void
    {
        $calculator = new SalesTrainerSalaryCalculator();

        $result = $calculator->compute(0, 100, 0, 33, 0, 0);

        $this->assertSame(100, $result['sales_base_cents']);
        $this->assertSame(33, $result['commission_cents']);
        $this->assertSame(33, $result['total_cents']);
    }

    #[DataProvider('percentClampProvider')]
    public function test_percent_is_clamped_to_0_100(int $percent, int $expectedCommission): void
    {
        $calculator = new SalesTrainerSalaryCalculator();

        $result = $calculator->compute(0, 10000, 0, $percent, 0, 0);

        $this->assertSame($expectedCommission, $result['commission_cents']);
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function percentClampProvider(): array
    {
        return [
            'negative' => [-5, 0],
            'over_100' => [150, 10000],
            'hundred' => [100, 10000],
        ];
    }
}
