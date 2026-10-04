<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Администраторы объекта (location_admin_user) в отчётах.
 * ФИО и сокращение «ещё N шт.» совпадают с колонкой задолженностей.
 */
final class LocationAdminReport
{
    /**
     * @param  QueryBuilder|EloquentBuilder  $query
     */
    public static function apply($query, string $locationColumn, mixed $raw, int $partnerId): void
    {
        self::assertColumn($locationColumn);
        [$includeNone, $ids] = self::tokens($raw);
        if (! $includeNone && $ids === []) {
            return;
        }

        $pid = (int) $partnerId;
        $query->where(function ($outer) use ($includeNone, $ids, $locationColumn, $pid) {
            if ($ids !== []) {
                $outer->whereExists(function ($sub) use ($ids, $locationColumn, $pid) {
                    $sub->selectRaw('1')
                        ->from('location_admin_user as report_location_admins')
                        ->join('users as report_location_admin_users', 'report_location_admin_users.id', '=', 'report_location_admins.user_id')
                        ->whereColumn('report_location_admins.location_id', $locationColumn)
                        ->where('report_location_admins.partner_id', $pid)
                        ->whereNull('report_location_admin_users.deleted_at')
                        ->whereIn('report_location_admins.user_id', $ids);
                });
            }

            if ($includeNone) {
                $none = function ($inner) use ($locationColumn, $pid) {
                    $inner->whereNull($locationColumn)
                        ->orWhereNotExists(function ($sub) use ($locationColumn, $pid) {
                            $sub->selectRaw('1')
                                ->from('location_admin_user as report_location_admins_none')
                                ->join('users as report_location_admin_users_none', 'report_location_admin_users_none.id', '=', 'report_location_admins_none.user_id')
                                ->whereColumn('report_location_admins_none.location_id', $locationColumn)
                                ->where('report_location_admins_none.partner_id', $pid)
                                ->whereNull('report_location_admin_users_none.deleted_at');
                        });
                };

                if ($ids !== []) {
                    $outer->orWhere($none);
                } else {
                    $outer->where($none);
                }
            }
        });
    }

    /**
     * Фильтр по объекту группы платежа (teams.location_id), без обязательного join teams.
     *
     * @param  QueryBuilder|EloquentBuilder  $query
     */
    public static function applyForTeamId($query, string $teamIdColumn, mixed $raw, int $partnerId): void
    {
        self::assertColumn($teamIdColumn);
        [$includeNone, $ids] = self::tokens($raw);
        if (! $includeNone && $ids === []) {
            return;
        }

        $pid = (int) $partnerId;
        $query->where(function ($outer) use ($includeNone, $ids, $teamIdColumn, $pid) {
            if ($ids !== []) {
                $outer->whereExists(function ($sub) use ($ids, $teamIdColumn, $pid) {
                    $sub->selectRaw('1')
                        ->from('teams as report_admin_teams')
                        ->join('location_admin_user as report_location_admins', 'report_location_admins.location_id', '=', 'report_admin_teams.location_id')
                        ->join('users as report_location_admin_users', 'report_location_admin_users.id', '=', 'report_location_admins.user_id')
                        ->whereColumn('report_admin_teams.id', $teamIdColumn)
                        ->where('report_admin_teams.partner_id', $pid)
                        ->whereNull('report_admin_teams.deleted_at')
                        ->where('report_location_admins.partner_id', $pid)
                        ->whereNull('report_location_admin_users.deleted_at')
                        ->whereIn('report_location_admins.user_id', $ids);
                });
            }

            if ($includeNone) {
                $none = function ($inner) use ($teamIdColumn, $pid) {
                    $inner->whereNotExists(function ($sub) use ($teamIdColumn, $pid) {
                        $sub->selectRaw('1')
                            ->from('teams as report_admin_teams_none')
                            ->join('location_admin_user as report_location_admins_none', 'report_location_admins_none.location_id', '=', 'report_admin_teams_none.location_id')
                            ->join('users as report_location_admin_users_none', 'report_location_admin_users_none.id', '=', 'report_location_admins_none.user_id')
                            ->whereColumn('report_admin_teams_none.id', $teamIdColumn)
                            ->where('report_admin_teams_none.partner_id', $pid)
                            ->whereNull('report_admin_teams_none.deleted_at')
                            ->where('report_location_admins_none.partner_id', $pid)
                            ->whereNull('report_location_admin_users_none.deleted_at');
                    });
                };

                if ($ids !== []) {
                    $outer->orWhere($none);
                } else {
                    $outer->where($none);
                }
            }
        });
    }

    public static function namesSql(int $partnerId, string $locationColumn): string
    {
        self::assertColumn($locationColumn);

        return self::namesSqlFromExpr($partnerId, $locationColumn);
    }

    public static function namesSqlForTeamId(int $partnerId, string $teamIdColumn): string
    {
        self::assertColumn($teamIdColumn);
        $pid = (int) $partnerId;
        $expr = '(SELECT report_admin_teams.location_id FROM teams AS report_admin_teams'
            ." WHERE report_admin_teams.id = {$teamIdColumn}"
            ." AND report_admin_teams.partner_id = {$pid}"
            .' AND report_admin_teams.deleted_at IS NULL LIMIT 1)';

        return self::namesSqlFromExpr($partnerId, $expr);
    }

    /**
     * Один объект на группу (MAX скалярного списка).
     */
    public static function stableNamesSql(int $partnerId, string $locationColumn): string
    {
        return 'MAX('.self::namesSql($partnerId, $locationColumn).')';
    }

    public static function stableTeamNamesSql(int $partnerId, string $teamIdColumn): string
    {
        return 'MAX('.self::namesSqlForTeamId($partnerId, $teamIdColumn).')';
    }

    /**
     * Разные объекты внутри группы строк: списки склеиваются, дубли снимает PHP.
     */
    public static function groupedNamesSql(int $partnerId, string $locationColumn): string
    {
        $inner = self::namesSql($partnerId, $locationColumn);
        $separator = "\n";

        return "GROUP_CONCAT(DISTINCT {$inner} SEPARATOR '{$separator}')";
    }

    /**
     * @param  \Yajra\DataTables\DataTableAbstract  $table
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public static function decorate($table, bool $visible)
    {
        if (! $visible) {
            return $table;
        }

        return $table
            ->addColumn('location_admin', function ($row) {
                return self::shortLabel(self::names($row->location_admin_names_raw ?? null));
            })
            ->addColumn('location_admin_names', function ($row) {
                return self::names($row->location_admin_names_raw ?? null);
            })
            ->orderColumn('location_admin', function ($query, $order) {
                $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                // Алиас GROUP_CONCAT/MAX нельзя подставлять в CASE: MySQL 1247.
                $query->orderByRaw('location_admin_names_raw '.$dir);
            })
            ->removeColumn('location_admin_names_raw');
    }

    /**
     * @return list<string>
     */
    public static function names(mixed $raw): array
    {
        $text = trim((string) ($raw ?? ''));
        if ($text === '') {
            return [];
        }

        $names = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $out = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '' || isset($out[$name])) {
                continue;
            }
            $out[$name] = $name;
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $names
     */
    public static function shortLabel(array $names): string
    {
        $count = count($names);
        if ($count === 0) {
            return '';
        }
        if ($count <= 2) {
            return implode(', ', $names);
        }

        return $names[0].', еще '.($count - 1).' шт.';
    }

    /**
     * @return array{0: bool, 1: list<int>}
     */
    private static function tokens(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : ($raw === null || $raw === '' ? [] : [$raw]);
        $includeNone = false;
        $ids = [];
        foreach ($items as $item) {
            if (is_array($item) || is_object($item)) {
                continue;
            }
            $value = trim((string) $item);
            if ($value === 'none') {
                $includeNone = true;
            } elseif (ctype_digit($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return [$includeNone, array_values(array_unique($ids))];
    }

    private static function namesSqlFromExpr(int $partnerId, string $locationIdExpr): string
    {
        $pid = (int) $partnerId;
        $separator = "\n";

        return <<<SQL
(
    SELECT GROUP_CONCAT(
        TRIM(CONCAT(COALESCE(report_admin_name_users.lastname, ''), ' ', COALESCE(report_admin_name_users.name, '')))
        ORDER BY report_admin_name_users.lastname, report_admin_name_users.name, report_admin_name_users.id
        SEPARATOR '{$separator}'
    )
    FROM location_admin_user AS report_admin_name_pivot
    INNER JOIN users AS report_admin_name_users ON report_admin_name_users.id = report_admin_name_pivot.user_id
    WHERE report_admin_name_pivot.location_id = {$locationIdExpr}
      AND report_admin_name_pivot.partner_id = {$pid}
      AND report_admin_name_users.deleted_at IS NULL
)
SQL;
    }

    private static function assertColumn(string $column): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            throw new \InvalidArgumentException('Unsafe column for location admin report: '.$column);
        }
    }
}
