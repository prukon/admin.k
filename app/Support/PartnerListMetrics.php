<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Contract;
use App\Models\Partner;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Метрики строк списка /admin/partners: активные ученики, подписанные договоры, оборот.
 */
final class PartnerListMetrics
{
    public const COLUMN_ACTIVE_USERS = 'active_users_count';
    public const COLUMN_SIGNED_CONTRACTS = 'signed_contracts_count';
    public const COLUMN_TURNOVER_ALL = 'turnover_all';
    public const COLUMN_TURNOVER_MONTH_0 = 'turnover_month_0';
    public const COLUMN_TURNOVER_MONTH_1 = 'turnover_month_1';
    public const COLUMN_TURNOVER_MONTH_2 = 'turnover_month_2';

    /** @var list<string> */
    public const JSON_KEYS = [
        self::COLUMN_ACTIVE_USERS,
        self::COLUMN_SIGNED_CONTRACTS,
        self::COLUMN_TURNOVER_ALL,
        self::COLUMN_TURNOVER_MONTH_0,
        self::COLUMN_TURNOVER_MONTH_1,
        self::COLUMN_TURNOVER_MONTH_2,
    ];

    /** @var array<int, string> именительный падеж, строчные — для «Оборот за …» */
    private const MONTH_NAMES = [
        1  => 'январь',
        2  => 'февраль',
        3  => 'март',
        4  => 'апрель',
        5  => 'май',
        6  => 'июнь',
        7  => 'июль',
        8  => 'август',
        9  => 'сентябрь',
        10 => 'октябрь',
        11 => 'ноябрь',
        12 => 'декабрь',
    ];

    /**
     * Подписи месяцев: 0 = текущий, 1 = прошлый, 2 = позапрошлый.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function monthColumnLabels(?Carbon $now = null): array
    {
        $now = $now ?? now();

        return [
            0 => self::monthName($now),
            1 => self::monthName($now->copy()->subMonthNoOverflow()),
            2 => self::monthName($now->copy()->subMonthsNoOverflow(2)),
        ];
    }

    /**
     * Left join агрегатов к запросу партнёров (после фильтров, до сортировки и пагинации).
     *
     * @param  Builder<Partner>  $query
     * @return Builder<Partner>
     */
    public static function applyJoins(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?? now();
        $windows = self::monthWindows($now);

        $studentRoleId = (int) (Role::query()->where('name', 'user')->value('id') ?? 0);

        $activeUsersSub = DB::table('users')
            ->selectRaw('partner_id, COUNT(*) as active_users_count')
            ->where('is_enabled', 1)
            ->where('role_id', $studentRoleId)
            ->whereNull('deleted_at')
            ->whereNotNull('partner_id')
            ->groupBy('partner_id');

        $signedContractsSub = DB::table('contracts')
            ->selectRaw('school_id, COUNT(*) as signed_contracts_count')
            ->where('status', Contract::STATUS_SIGNED)
            ->groupBy('school_id');

        $paymentsSub = DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.summ_cents', '>', 0)
            ->whereNotNull('users.partner_id')
            ->selectRaw(
                'users.partner_id as partner_id,
                 SUM(payments.summ_cents) as turnover_all_cents,
                 SUM(CASE WHEN payments.operation_date >= ? AND payments.operation_date < ? THEN payments.summ_cents ELSE 0 END) as turnover_month_0_cents,
                 SUM(CASE WHEN payments.operation_date >= ? AND payments.operation_date < ? THEN payments.summ_cents ELSE 0 END) as turnover_month_1_cents,
                 SUM(CASE WHEN payments.operation_date >= ? AND payments.operation_date < ? THEN payments.summ_cents ELSE 0 END) as turnover_month_2_cents',
                [
                    $windows[0]['start'], $windows[0]['end'],
                    $windows[1]['start'], $windows[1]['end'],
                    $windows[2]['start'], $windows[2]['end'],
                ]
            )
            ->groupBy('users.partner_id');

        return $query
            ->select('partners.*')
            ->leftJoinSub($activeUsersSub, 'partner_active_users', 'partner_active_users.partner_id', '=', 'partners.id')
            ->leftJoinSub($signedContractsSub, 'partner_signed_contracts', 'partner_signed_contracts.school_id', '=', 'partners.id')
            ->leftJoinSub($paymentsSub, 'partner_turnover', 'partner_turnover.partner_id', '=', 'partners.id')
            ->addSelect([
                DB::raw('COALESCE(partner_active_users.active_users_count, 0) as active_users_count'),
                DB::raw('COALESCE(partner_signed_contracts.signed_contracts_count, 0) as signed_contracts_count'),
                DB::raw('COALESCE(partner_turnover.turnover_all_cents, 0) as turnover_all_cents'),
                DB::raw('COALESCE(partner_turnover.turnover_month_0_cents, 0) as turnover_month_0_cents'),
                DB::raw('COALESCE(partner_turnover.turnover_month_1_cents, 0) as turnover_month_1_cents'),
                DB::raw('COALESCE(partner_turnover.turnover_month_2_cents, 0) as turnover_month_2_cents'),
            ]);
    }

    /**
     * SQL-выражение для ORDER BY метрики (после {@see applyJoins}).
     */
    public static function orderByExpression(string $columnKey): ?string
    {
        return match ($columnKey) {
            self::COLUMN_ACTIVE_USERS => 'active_users_count',
            self::COLUMN_SIGNED_CONTRACTS => 'signed_contracts_count',
            self::COLUMN_TURNOVER_ALL => 'turnover_all_cents',
            self::COLUMN_TURNOVER_MONTH_0 => 'turnover_month_0_cents',
            self::COLUMN_TURNOVER_MONTH_1 => 'turnover_month_1_cents',
            self::COLUMN_TURNOVER_MONTH_2 => 'turnover_month_2_cents',
            default => null,
        };
    }

    /**
     * Поля JSON-строки DataTables.
     *
     * @return array<string, int|float>
     */
    public static function payload(Partner $partner): array
    {
        return [
            self::COLUMN_ACTIVE_USERS => (int) ($partner->active_users_count ?? 0),
            self::COLUMN_SIGNED_CONTRACTS => (int) ($partner->signed_contracts_count ?? 0),
            self::COLUMN_TURNOVER_ALL => self::centsToRubles((int) ($partner->turnover_all_cents ?? 0)),
            self::COLUMN_TURNOVER_MONTH_0 => self::centsToRubles((int) ($partner->turnover_month_0_cents ?? 0)),
            self::COLUMN_TURNOVER_MONTH_1 => self::centsToRubles((int) ($partner->turnover_month_1_cents ?? 0)),
            self::COLUMN_TURNOVER_MONTH_2 => self::centsToRubles((int) ($partner->turnover_month_2_cents ?? 0)),
        ];
    }

    public static function centsToRubles(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Полуинтервалы [start, end) для текущего / прошлого / позапрошлого месяца.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public static function monthWindows(?Carbon $now = null): array
    {
        $now = $now ?? now();

        $windows = [];
        for ($offset = 0; $offset <= 2; $offset++) {
            $start = $now->copy()->subMonthsNoOverflow($offset)->startOfMonth();
            $windows[$offset] = [
                'start' => $start->toDateTimeString(),
                'end'   => $start->copy()->addMonth()->toDateTimeString(),
            ];
        }

        return $windows;
    }

    private static function monthName(Carbon $date): string
    {
        return self::MONTH_NAMES[(int) $date->month] ?? $date->format('m');
    }
}
