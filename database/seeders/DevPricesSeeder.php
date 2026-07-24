<?php

namespace Database\Seeders;

use App\Models\Payable;
use App\Models\UserPrice;
use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DevPricesSeeder extends Seeder
{
    use GuardsDevSeedData;

    /** Целевой объём оплаченных цепочек (факт ≤ размера уникального пула). */
    private const PAID_TARGET = 1000;

    /** Целевой объём неоплаченных users_prices (факт ≤ размера уникального пула). */
    private const UNPAID_TARGET = 30;

    /** В env=testing меньше нагрузка на CI, инварианты те же. */
    private const PAID_TARGET_TESTING = 48;

    private const UNPAID_TARGET_TESTING = 12;

    private const PAID_MONTHS_AGO_MIN = 0;

    private const PAID_MONTHS_AGO_MAX = 6;

    private const UNPAID_MONTHS_AGO_MIN = 7;

    private const UNPAID_MONTHS_AGO_MAX = 12;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $memberships = $this->studentTeamMemberships();

        if ($memberships->isEmpty()) {
            return;
        }

        $paidSlots = $this->buildUniqueSlots(
            $memberships,
            self::PAID_MONTHS_AGO_MIN,
            self::PAID_MONTHS_AGO_MAX,
        )
            ->shuffle()
            ->take($this->paidTarget())
            ->values();

        foreach ($paidSlots as $slot) {
            Payable::factory()
                ->paidMonthlyWithAllRelations()
                ->create([
                    'partner_id' => $slot['partner_id'],
                    'user_id' => $slot['user_id'],
                    'month' => $slot['month'],
                    'amount' => random_int(500, 10000),
                    'meta' => ['team_id' => $slot['team_id']],
                ]);
        }

        // Окна месяцев 0–6 и 7–12 не пересекаются → коллизий с paid нет.
        $unpaidSlots = $this->buildUniqueSlots(
            $memberships,
            self::UNPAID_MONTHS_AGO_MIN,
            self::UNPAID_MONTHS_AGO_MAX,
        )
            ->shuffle()
            ->take($this->unpaidTarget())
            ->values();

        foreach ($unpaidSlots as $slot) {
            UserPrice::factory()
                ->unpaid()
                ->forUserAndMonth(
                    $slot['user_id'],
                    $slot['month'],
                    random_int(500, 10000),
                    false,
                    $slot['team_id']
                )
                ->create();
        }
    }

    private function paidTarget(): int
    {
        return app()->environment('testing')
            ? self::PAID_TARGET_TESTING
            : self::PAID_TARGET;
    }

    private function unpaidTarget(): int
    {
        return app()->environment('testing')
            ? self::UNPAID_TARGET_TESTING
            : self::UNPAID_TARGET;
    }

    /**
     * Актуальные пары ученик↔группа из pivot (только роль user, не soft-deleted группы).
     *
     * @return Collection<int, array{user_id: int, team_id: int, partner_id: int}>
     */
    private function studentTeamMemberships(): Collection
    {
        return DB::table('team_user')
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->where('roles.name', 'user')
            ->whereNull('users.deleted_at')
            ->whereNull('teams.deleted_at')
            ->whereNotNull('users.partner_id')
            ->whereColumn('teams.partner_id', 'users.partner_id')
            ->select([
                'team_user.user_id',
                'team_user.team_id',
                'users.partner_id',
            ])
            ->distinct()
            ->get()
            ->map(static fn ($row): array => [
                'user_id' => (int) $row->user_id,
                'team_id' => (int) $row->team_id,
                'partner_id' => (int) $row->partner_id,
            ])
            ->values();
    }

    /**
     * Декартово произведение membership × месяцы окна — гарантированно уникальные
     * ключи (user_id, team_id, new_month) до shuffle/take.
     *
     * @param  Collection<int, array{user_id: int, team_id: int, partner_id: int}>  $memberships
     * @return Collection<int, array{user_id: int, team_id: int, partner_id: int, month: string}>
     */
    private function buildUniqueSlots(Collection $memberships, int $monthsAgoMin, int $monthsAgoMax): Collection
    {
        $months = [];
        for ($ago = $monthsAgoMin; $ago <= $monthsAgoMax; $ago++) {
            $months[] = Carbon::now()
                ->subMonths($ago)
                ->startOfMonth()
                ->format('Y-m-01');
        }

        $slots = collect();

        foreach ($memberships as $membership) {
            foreach ($months as $month) {
                $slots->push([
                    'user_id' => $membership['user_id'],
                    'team_id' => $membership['team_id'],
                    'partner_id' => $membership['partner_id'],
                    'month' => $month,
                ]);
            }
        }

        return $slots;
    }
}
