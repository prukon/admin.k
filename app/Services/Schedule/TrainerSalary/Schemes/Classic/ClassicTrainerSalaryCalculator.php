<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Classic;

/**
 * Схема classic: оклад + (кол-во тренировок × ставка) + бонусы − вычеты.
 * Канон — целочисленная арифметика в копейках (BIGINT *_cents).
 */
final class ClassicTrainerSalaryCalculator
{
    /**
     * @return array{
     *     trainings_amount_cents: int,
     *     total_cents: int
     * }
     */
    public function compute(
        int $trainingsCount,
        int $baseSalaryCents,
        int $ratePerTrainingCents,
        int $bonusesCents,
        int $deductionsCents,
    ): array {
        $trainingsAmountCents = max(0, $trainingsCount) * $ratePerTrainingCents;
        $totalCents = $baseSalaryCents + $trainingsAmountCents + $bonusesCents - $deductionsCents;

        return [
            'trainings_amount_cents' => $trainingsAmountCents,
            'total_cents' => $totalCents,
        ];
    }
}
