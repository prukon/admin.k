<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Sales;

/**
 * Схема sales: оклад + % от базы продаж + бонусы − вычеты.
 * Канон — целочисленная арифметика в копейках (BIGINT *_cents).
 * Процент — целое 0–100; комиссия = intdiv(база × %, 100).
 */
final class SalesTrainerSalaryCalculator
{
    /**
     * @return array{
     *     sales_base_cents: int,
     *     commission_cents: int,
     *     total_cents: int
     * }
     */
    public function compute(
        int $baseSalaryCents,
        int $paidMonthsCents,
        int $paidPackagesCents,
        int $salesPercent,
        int $bonusesCents,
        int $deductionsCents,
    ): array {
        $paidMonthsCents = max(0, $paidMonthsCents);
        $paidPackagesCents = max(0, $paidPackagesCents);
        $salesPercent = max(0, min(100, $salesPercent));

        $salesBaseCents = $paidMonthsCents + $paidPackagesCents;
        $commissionCents = intdiv($salesBaseCents * $salesPercent, 100);
        $totalCents = $baseSalaryCents + $commissionCents + $bonusesCents - $deductionsCents;

        return [
            'sales_base_cents' => $salesBaseCents,
            'commission_cents' => $commissionCents,
            'total_cents' => $totalCents,
        ];
    }
}
