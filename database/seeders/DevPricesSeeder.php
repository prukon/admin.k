<?php

namespace Database\Seeders;

use App\Models\LessonPackage;
use App\Models\Payable;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Support\Money;
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

    /** Доля слотов с привязанным абонементом (установка цен через пакет). */
    private const PACKAGE_ATTACH_PERCENT = 72;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $memberships = $this->studentTeamMemberships();

        if ($memberships->isEmpty()) {
            return;
        }

        /** @var Collection<int|string, Collection<int, LessonPackage>> $packagesByPartner */
        $packagesByPartner = LessonPackage::query()
            ->where('is_active', true)
            ->get()
            ->groupBy(static fn (LessonPackage $p): int => (int) $p->partner_id);

        $paidSlots = $this->buildUniqueSlots(
            $memberships,
            self::PAID_MONTHS_AGO_MIN,
            self::PAID_MONTHS_AGO_MAX,
        )
            ->shuffle()
            ->take($this->paidTarget())
            ->values();

        foreach ($paidSlots as $slot) {
            $package = $this->pickPackageForSlot($packagesByPartner, (int) $slot['partner_id'], preferPostpay: false);
            $amountCents = $package !== null
                ? $this->amountCentsForPackage($package)
                : Money::toCentsOrFail(random_int(500, 10000));

            Payable::factory()
                ->paidMonthlyWithAllRelations()
                ->create([
                    'partner_id' => $slot['partner_id'],
                    'user_id' => $slot['user_id'],
                    'month' => $slot['month'],
                    'amount_cents' => $amountCents,
                    'meta' => ['team_id' => $slot['team_id']],
                ]);

            if ($package !== null) {
                $this->attachPackageToUserPrice(
                    (int) $slot['user_id'],
                    (int) $slot['team_id'],
                    (string) $slot['month'],
                    $package,
                    $amountCents,
                );
            }
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
            $package = $this->pickPackageForSlot($packagesByPartner, (int) $slot['partner_id'], preferPostpay: false);
            $amountCents = $package !== null
                ? $this->amountCentsForPackage($package)
                : Money::toCentsOrFail(random_int(500, 10000));

            $attrs = [];
            if ($package !== null) {
                $attrs['lesson_package_id'] = (int) $package->id;
            }

            UserPrice::factory()
                ->unpaid()
                ->forUserAndMonth(
                    $slot['user_id'],
                    $slot['month'],
                    $amountCents,
                    false,
                    $slot['team_id']
                )
                ->create($attrs);
        }

        $this->seedCurrentMonthPostpayRows($memberships, $packagesByPartner);
        $this->seedTeamPrices($packagesByPartner);
        $this->syncUnpaidPostpayRows();
    }

    /**
     * Неоплаченные postpay-строки текущего месяца (сумма пересчитается после журнала).
     *
     * @param  Collection<int, array{user_id: int, team_id: int, partner_id: int}>  $memberships
     * @param  Collection<int|string, Collection<int, LessonPackage>>  $packagesByPartner
     */
    private function seedCurrentMonthPostpayRows(Collection $memberships, Collection $packagesByPartner): void
    {
        $month = Carbon::now()->startOfMonth()->format('Y-m-01');
        $target = app()->environment('testing') ? 8 : 24;

        $candidates = $memberships
            ->shuffle()
            ->take($target * 3)
            ->values();

        $created = 0;

        foreach ($candidates as $membership) {
            if ($created >= $target) {
                break;
            }

            $postpay = $this->randomPostpayPackage($packagesByPartner, (int) $membership['partner_id']);
            if ($postpay === null) {
                continue;
            }

            $exists = UserPrice::query()
                ->where('user_id', $membership['user_id'])
                ->where('team_id', $membership['team_id'])
                ->whereDate('new_month', $month)
                ->exists();

            if ($exists) {
                continue;
            }

            UserPrice::factory()
                ->unpaid()
                ->forUserAndMonth(
                    $membership['user_id'],
                    $month,
                    0,
                    false,
                    $membership['team_id']
                )
                ->create([
                    'lesson_package_id' => (int) $postpay->id,
                ]);

            $created++;
        }
    }

    /**
     * @param  Collection<int|string, Collection<int, LessonPackage>>  $packagesByPartner
     */
    private function seedTeamPrices(Collection $packagesByPartner): void
    {
        $teams = Team::query()
            ->whereNull('deleted_at')
            ->whereNotNull('partner_id')
            ->get();

        foreach ($teams as $team) {
            $partnerId = (int) $team->partner_id;
            $package = $this->pickNonPostpayPackage($packagesByPartner, $partnerId)
                ?? $this->pickPackageForSlot($packagesByPartner, $partnerId, preferPostpay: false);

            if ($package === null) {
                continue;
            }

            $priceCents = (int) $package->price_cents;

            for ($ago = 0; $ago <= 2; $ago++) {
                if (random_int(1, 100) > 65) {
                    continue;
                }

                $month = Carbon::now()
                    ->startOfMonth()
                    ->subMonths($ago)
                    ->format('Y-m-01');

                TeamPrice::query()->updateOrCreate(
                    [
                        'team_id' => (int) $team->id,
                        'new_month' => $month,
                    ],
                    [
                        'price_cents' => $priceCents,
                        'lesson_package_id' => (int) $package->id,
                    ]
                );
            }
        }
    }

    private function syncUnpaidPostpayRows(): void
    {
        $sync = app(PostpayUsersPriceSync::class);

        $rows = UserPrice::query()
            ->where('is_paid', 0)
            ->whereNotNull('lesson_package_id')
            ->with(['lessonPackage'])
            ->get()
            ->filter(static fn (UserPrice $row): bool => (bool) $row->lessonPackage?->isPostpay());

        foreach ($rows as $row) {
            $sync->syncRow($row);
        }
    }

    private function attachPackageToUserPrice(
        int $userId,
        int $teamId,
        string $month,
        LessonPackage $package,
        int $amountCents,
    ): void {
        UserPrice::query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereDate('new_month', $month)
            ->update([
                'lesson_package_id' => (int) $package->id,
                'price_cents' => $amountCents,
            ]);
    }

    /**
     * @param  Collection<int|string, Collection<int, LessonPackage>>  $packagesByPartner
     */
    private function pickPackageForSlot(
        Collection $packagesByPartner,
        int $partnerId,
        bool $preferPostpay,
    ): ?LessonPackage {
        if (random_int(1, 100) > self::PACKAGE_ATTACH_PERCENT) {
            return null;
        }

        /** @var Collection<int, LessonPackage>|null $pkgs */
        $pkgs = $packagesByPartner->get($partnerId);
        if ($pkgs === null || $pkgs->isEmpty()) {
            return null;
        }

        if ($preferPostpay) {
            $postpay = $pkgs->filter(static fn (LessonPackage $p): bool => $p->isPostpay());
            if ($postpay->isNotEmpty()) {
                return $postpay->random();
            }
        }

        // В оплаченных/старых месяцах чаще обычные пакеты; postpay — реже (~18%).
        if (random_int(1, 100) <= 18) {
            $postpay = $pkgs->filter(static fn (LessonPackage $p): bool => $p->isPostpay());
            if ($postpay->isNotEmpty()) {
                return $postpay->random();
            }
        }

        $regular = $pkgs->filter(static fn (LessonPackage $p): bool => ! $p->isPostpay());

        return $regular->isNotEmpty() ? $regular->random() : $pkgs->random();
    }

    /**
     * @param  Collection<int|string, Collection<int, LessonPackage>>  $packagesByPartner
     */
    private function pickNonPostpayPackage(Collection $packagesByPartner, int $partnerId): ?LessonPackage
    {
        /** @var Collection<int, LessonPackage>|null $pkgs */
        $pkgs = $packagesByPartner->get($partnerId);
        if ($pkgs === null || $pkgs->isEmpty()) {
            return null;
        }

        $regular = $pkgs->filter(static fn (LessonPackage $p): bool => ! $p->isPostpay());

        return $regular->isNotEmpty() ? $regular->random() : null;
    }

    /**
     * @param  Collection<int|string, Collection<int, LessonPackage>>  $packagesByPartner
     */
    private function randomPostpayPackage(Collection $packagesByPartner, int $partnerId): ?LessonPackage
    {
        /** @var Collection<int, LessonPackage>|null $pkgs */
        $pkgs = $packagesByPartner->get($partnerId);
        if ($pkgs === null || $pkgs->isEmpty()) {
            return null;
        }

        $postpay = $pkgs->filter(static fn (LessonPackage $p): bool => $p->isPostpay());

        return $postpay->isNotEmpty() ? $postpay->random() : null;
    }

    private function amountCentsForPackage(LessonPackage $package): int
    {
        $perUnitCents = (int) $package->price_cents;

        if ($package->isPostpay()) {
            return $perUnitCents * random_int(2, 8);
        }

        return $perUnitCents;
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
            // startOfMonth() до subMonths — иначе на 29–31 числе overflow даёт дубли месяцев (напр. февраль→март).
            $months[] = Carbon::now()
                ->startOfMonth()
                ->subMonths($ago)
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
