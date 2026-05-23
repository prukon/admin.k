<?php

declare(strict_types=1);

namespace App\Services\Schedule;

/**
 * Расчёт сумм ЗП тренеров (денежные поля, 2 знака после запятой).
 */
final class TrainerSalaryCalculator
{
    /**
     * @return array{
     *     trainings_amount: string,
     *     total: string
     * }
     */
    public function compute(
        int $trainingsCount,
        string|float|int $baseSalary,
        string|float|int $ratePerTraining,
        string|float|int $bonuses,
        string|float|int $deductions,
    ): array {
        $base = $this->normalizeMoney($baseSalary);
        $rate = $this->normalizeMoney($ratePerTraining);
        $bonus = $this->normalizeMoney($bonuses);
        $deduction = $this->normalizeMoney($deductions);

        $trainingsAmount = bcmul((string) max(0, $trainingsCount), $rate, 2);
        $total = bcsub(bcadd(bcadd($base, $trainingsAmount, 2), $bonus, 2), $deduction, 2);

        return [
            'trainings_amount' => $trainingsAmount,
            'total' => $total,
        ];
    }

    public function normalizeMoney(string|float|int|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
