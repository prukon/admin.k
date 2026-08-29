<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Sales;

use App\Models\Payable;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * База продаж схемы sales: оплаченные месяца периода + оплаченные абонементы по дате оплаты.
 * Каждый тренер группы (team_trainer) получает 100% суммы этой группы.
 */
final class SalesPaidSalesAggregator
{
    private const EFFECTIVE_PAID_SQL = '(CASE WHEN %s.is_manual_paid IS NOT NULL THEN %s.is_manual_paid ELSE %s.is_paid END) = 1';

    /**
     * @return array<int, array{paid_months_cents: int, paid_packages_cents: int}>
     */
    public function trainerPaidTotals(int $partnerId, string $dateFrom, string $dateTo): array
    {
        $trainersByTeam = $this->trainerIdsByTeam($partnerId);
        $totals = [];

        foreach ($this->paidMonths($partnerId, $dateFrom) as $row) {
            $this->addToTrainers(
                $totals,
                $trainersByTeam,
                (int) $row->team_id,
                (int) $row->price_cents,
                'paid_months_cents',
            );
        }

        $skipUlpIds = $this->ulpIdsCountedAsPaidMonths($partnerId);
        $payablePaidAtByUlpId = $this->latestPaidAtByUlpId($partnerId);

        foreach ($this->paidPackages($partnerId) as $ulp) {
            $ulpId = (int) $ulp->id;
            if (isset($skipUlpIds[$ulpId])) {
                continue;
            }

            $paidAt = $this->resolvePackagePaidAt($ulp, $payablePaidAtByUlpId);
            if ($paidAt === null) {
                continue;
            }

            $paidDate = $paidAt->toDateString();
            if ($paidDate < $dateFrom || $paidDate > $dateTo) {
                continue;
            }

            $feeCents = (int) $ulp->fee_amount_cents;
            if ($feeCents <= 0) {
                continue;
            }

            $this->addToTrainers(
                $totals,
                $trainersByTeam,
                (int) ($ulp->team_id ?? 0),
                $feeCents,
                'paid_packages_cents',
            );
        }

        return $totals;
    }

    /**
     * @return array<int, list<int>> team_id => trainer_profile_ids
     */
    private function trainerIdsByTeam(int $partnerId): array
    {
        $rows = DB::table('team_trainer')
            ->where('partner_id', $partnerId)
            ->get(['team_id', 'trainer_profile_id']);

        $map = [];
        foreach ($rows as $row) {
            $teamId = (int) $row->team_id;
            $trainerId = (int) $row->trainer_profile_id;
            if ($teamId <= 0 || $trainerId <= 0) {
                continue;
            }
            $map[$teamId][] = $trainerId;
        }

        return $map;
    }

    /**
     * @return list<object{team_id: int|string, price_cents: int|string}>
     */
    private function paidMonths(int $partnerId, string $dateFrom): array
    {
        $monthStart = Carbon::parse($dateFrom)->startOfMonth()->toDateString();
        $sql = sprintf(self::EFFECTIVE_PAID_SQL, 'users_prices', 'users_prices', 'users_prices');

        return UserPrice::query()
            ->join('users', 'users.id', '=', 'users_prices.user_id')
            ->where('users.partner_id', $partnerId)
            ->whereDate('users_prices.new_month', $monthStart)
            ->where('users_prices.price_cents', '>', 0)
            ->where('users_prices.team_id', '>', 0)
            ->whereRaw($sql)
            ->get(['users_prices.team_id', 'users_prices.price_cents'])
            ->all();
    }

    /**
     * ULP, уже учтённые как оплаченный месяц (любой месяц) — не дублировать в абонементах.
     *
     * @return array<int, true>
     */
    private function ulpIdsCountedAsPaidMonths(int $partnerId): array
    {
        $sql = sprintf(self::EFFECTIVE_PAID_SQL, 'users_prices', 'users_prices', 'users_prices');

        $ids = UserPrice::query()
            ->join('users', 'users.id', '=', 'users_prices.user_id')
            ->where('users.partner_id', $partnerId)
            ->whereNotNull('users_prices.user_lesson_package_id')
            ->where('users_prices.user_lesson_package_id', '>', 0)
            ->where('users_prices.price_cents', '>', 0)
            ->whereRaw($sql)
            ->pluck('users_prices.user_lesson_package_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * @return list<UserLessonPackage>
     */
    private function paidPackages(int $partnerId): array
    {
        $sql = sprintf(
            self::EFFECTIVE_PAID_SQL,
            'user_lesson_packages',
            'user_lesson_packages',
            'user_lesson_packages',
        );

        return UserLessonPackage::query()
            ->join('users', 'users.id', '=', 'user_lesson_packages.user_id')
            ->where('users.partner_id', $partnerId)
            ->where('user_lesson_packages.fee_amount_cents', '>', 0)
            ->where('user_lesson_packages.team_id', '>', 0)
            ->whereRaw($sql)
            ->get([
                'user_lesson_packages.id',
                'user_lesson_packages.team_id',
                'user_lesson_packages.fee_amount_cents',
                'user_lesson_packages.is_paid',
                'user_lesson_packages.is_manual_paid',
                'user_lesson_packages.manual_paid_at',
            ])
            ->all();
    }

    /**
     * @return array<int, Carbon>
     */
    private function latestPaidAtByUlpId(int $partnerId): array
    {
        $payables = Payable::query()
            ->where('partner_id', $partnerId)
            ->where('type', 'lesson_package_fee')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->get(['meta', 'paid_at']);

        $map = [];
        foreach ($payables as $payable) {
            $ulpId = (int) ($payable->meta['user_lesson_package_id'] ?? 0);
            if ($ulpId <= 0 || $payable->paid_at === null) {
                continue;
            }
            $paidAt = Carbon::parse($payable->paid_at);
            if (! isset($map[$ulpId]) || $paidAt->gt($map[$ulpId])) {
                $map[$ulpId] = $paidAt;
            }
        }

        return $map;
    }

    /**
     * @param array<int, Carbon> $payablePaidAtByUlpId
     */
    private function resolvePackagePaidAt(UserLessonPackage $ulp, array $payablePaidAtByUlpId): ?Carbon
    {
        if ($ulp->is_manual_paid === true) {
            return $ulp->manual_paid_at !== null
                ? Carbon::parse($ulp->manual_paid_at)
                : null;
        }

        return $payablePaidAtByUlpId[(int) $ulp->id] ?? null;
    }

    /**
     * @param array<int, array{paid_months_cents: int, paid_packages_cents: int}> $totals
     * @param array<int, list<int>> $trainersByTeam
     * @param 'paid_months_cents'|'paid_packages_cents' $bucket
     */
    private function addToTrainers(
        array &$totals,
        array $trainersByTeam,
        int $teamId,
        int $cents,
        string $bucket,
    ): void {
        if ($teamId <= 0 || $cents <= 0) {
            return;
        }

        foreach ($trainersByTeam[$teamId] ?? [] as $trainerId) {
            if (! isset($totals[$trainerId])) {
                $totals[$trainerId] = [
                    'paid_months_cents' => 0,
                    'paid_packages_cents' => 0,
                ];
            }
            $totals[$trainerId][$bucket] += $cents;
        }
    }
}
