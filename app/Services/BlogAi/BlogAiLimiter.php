<?php

namespace App\Services\BlogAi;

use App\Models\BlogAiDailyUsage;
use App\Services\BlogAi\Exceptions\BlogAiBudgetException;
use Illuminate\Support\Facades\DB;

class BlogAiLimiter
{
    public function __construct(
        private readonly BlogAiSettings $settings,
    ) {
    }

    /**
     * Reserve daily budget for a generation (concurrency-safe).
     * Returns reserved USD amount.
     */
    public function reserveOrFail(float $estimatedMaxUsd, ?string $date = null): float
    {
        $budget = $this->settings->dailyBudgetUsd();
        if ($budget <= 0) {
            return 0.0;
        }

        if ($estimatedMaxUsd <= 0) {
            throw new BlogAiBudgetException('Невозможно оценить стоимость запроса. Проверьте цены токенов в настройках блога.');
        }

        $today = $date ?: now()->toDateString();

        return (float) DB::transaction(function () use ($today, $budget, $estimatedMaxUsd) {
            /** @var BlogAiDailyUsage|null $row */
            $row = BlogAiDailyUsage::query()
                ->where('date', $today)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                $row = BlogAiDailyUsage::query()->create([
                    'date' => $today,
                    'reserved_usd' => 0,
                    'spent_usd' => 0,
                    'requests_count' => 0,
                ]);
                $row->refresh();
            }

            $current = (float) $row->reserved_usd + (float) $row->spent_usd;
            if ($current + $estimatedMaxUsd > $budget) {
                $remain = max(0, $budget - $current);
                throw new BlogAiBudgetException('Превышен дневной лимит ИИ: осталось примерно $' . number_format($remain, 2, '.', '') . ' из $' . number_format($budget, 2, '.', '') . '.');
            }

            $row->reserved_usd = (float) $row->reserved_usd + $estimatedMaxUsd;
            $row->requests_count = (int) $row->requests_count + 1;
            $row->save();

            return $estimatedMaxUsd;
        });
    }

    /**
     * Finalize reservation: move reserved -> spent (or release on failure).
     */
    public function finalize(?string $date, float $reservedUsd, float $spentUsd): void
    {
        $budget = $this->settings->dailyBudgetUsd();
        if ($budget <= 0) {
            return;
        }

        $today = $date ?: now()->toDateString();

        DB::transaction(function () use ($today, $reservedUsd, $spentUsd) {
            /** @var BlogAiDailyUsage|null $row */
            $row = BlogAiDailyUsage::query()
                ->where('date', $today)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return;
            }

            $row->reserved_usd = max(0, (float) $row->reserved_usd - $reservedUsd);
            if ($spentUsd > 0) {
                $row->spent_usd = (float) $row->spent_usd + $spentUsd;
            }
            $row->save();
        });
    }
}

