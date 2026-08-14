<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Kansas;

/**
 * Схема kansas: по группе, затем сумма.
 * premium = max(0, base_premium + (round(fact,1) − round(base,1)) × X)
 * pay = rate_per_training + premium
 * group_total = pay × trainings_count
 * Канон — целочисленная арифметика: копейки и десятые доли ученика.
 */
final class KansasTrainerSalaryCalculator
{
    /**
     * @return array{
     *     fact_avg_tenths: int,
     *     diff_tenths: int,
     *     premium_cents: int,
     *     pay_per_training_cents: int,
     *     group_total_cents: int
     * }
     */
    public function computeGroup(
        int $trainingsCount,
        int $studentsVisitedSum,
        int $baseAvgTenths,
        int $ratePerTrainingCents,
        int $basePremiumCents,
        int $premiumIncrementCents,
    ): array {
        $trainingsCount = max(0, $trainingsCount);
        $studentsVisitedSum = max(0, $studentsVisitedSum);

        $factAvgTenths = $trainingsCount > 0
            ? KansasQuantity::averageToTenths($studentsVisitedSum, $trainingsCount)
            : 0;
        $diffTenths = $factAvgTenths - $baseAvgTenths;
        $premiumDeltaCents = KansasQuantity::roundDiv($diffTenths * $premiumIncrementCents, 10);
        $premiumCents = max(0, $basePremiumCents + $premiumDeltaCents);
        $payPerTrainingCents = $ratePerTrainingCents + $premiumCents;
        $groupTotalCents = $payPerTrainingCents * $trainingsCount;

        return [
            'fact_avg_tenths' => $factAvgTenths,
            'diff_tenths' => $diffTenths,
            'premium_cents' => $premiumCents,
            'pay_per_training_cents' => $payPerTrainingCents,
            'group_total_cents' => $groupTotalCents,
        ];
    }
}
