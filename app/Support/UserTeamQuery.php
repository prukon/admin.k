<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class UserTeamQuery
{
    /**
     * Correlated subquery: подписи групп ученика через запятую (без join-дублей строк).
     */
    public static function sqlStudentTeamTitlesSubquery(int $partnerId, string $usersAlias = 'users'): string
    {
        $pid = (int) $partnerId;

        return <<<SQL
(SELECT GROUP_CONCAT(DISTINCT t.title ORDER BY t.title SEPARATOR ', ')
 FROM team_user tu
 INNER JOIN teams t ON t.id = tu.team_id
   AND t.partner_id = {$pid}
   AND t.deleted_at IS NULL
 WHERE tu.user_id = {$usersAlias}.id
   AND tu.partner_id = {$pid})
SQL;
    }

    /**
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyStudentTeamTitleLikeExists($query, int $partnerId, string $like, string $usersAlias = 'users'): void
    {
        $query->whereExists(function (QueryBuilder $sub) use ($partnerId, $like, $usersAlias) {
            $sub->selectRaw('1')
                ->from('team_user')
                ->join('teams', 'teams.id', '=', 'team_user.team_id')
                ->whereColumn('team_user.user_id', "{$usersAlias}.id")
                ->where('team_user.partner_id', $partnerId)
                ->where('teams.title', 'like', $like);
        });
    }

    /**
     * Фильтр отчётов: filter_team_id или текстовый team_title.
     *
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyReportTeamFilters(
        $query,
        int $partnerId,
        mixed $filterTeamId,
        ?string $teamTitle,
        string $usersAlias = 'users',
    ): void {
        if ($filterTeamId !== null && $filterTeamId !== '' && ctype_digit((string) $filterTeamId)) {
            $tid = (int) $filterTeamId;
            if ($tid > 0) {
                self::applyStudentInTeamExists($query, $partnerId, $tid, $usersAlias);
            }

            return;
        }

        if ($teamTitle !== null && trim($teamTitle) !== '') {
            self::applyStudentTeamTitleLikeExists(
                $query,
                $partnerId,
                '%'.trim($teamTitle).'%',
                $usersAlias,
            );
        }
    }

    /**
     * Фильтр отчётов по тренеру: ученик состоит в любой группе тренера.
     *
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyReportTrainerTeamFilter(
        $query,
        int $partnerId,
        mixed $filterTrainerProfileId,
        string $usersAlias = 'users',
    ): void {
        if ($filterTrainerProfileId === null || $filterTrainerProfileId === '' || ! ctype_digit((string) $filterTrainerProfileId)) {
            return;
        }

        $tpid = (int) $filterTrainerProfileId;
        if ($tpid <= 0) {
            return;
        }

        $trainerTeamIds = DB::table('team_trainer')
            ->where('partner_id', $partnerId)
            ->where('trainer_profile_id', $tpid)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($trainerTeamIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        self::applyStudentInAnyTeamExists($query, $partnerId, $trainerTeamIds, $usersAlias);
    }
    /**
     * Фильтр учеников по группе через pivot team_user.
     *
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyStudentTeamFilter($query, int $partnerId, mixed $teamFilter, string $usersAlias = 'users'): void
    {
        if ($teamFilter === null || $teamFilter === '' || $teamFilter === 'all') {
            return;
        }

        if ($teamFilter === 'none') {
            $query->whereNotExists(function (QueryBuilder $sub) use ($partnerId, $usersAlias) {
                $sub->selectRaw('1')
                    ->from('team_user')
                    ->whereColumn('team_user.user_id', "{$usersAlias}.id")
                    ->where('team_user.partner_id', $partnerId);
            });

            return;
        }

        if (! ctype_digit((string) $teamFilter)) {
            return;
        }

        $teamId = (int) $teamFilter;
        if ($teamId <= 0) {
            return;
        }

        self::applyStudentInTeamExists($query, $partnerId, $teamId, $usersAlias);
    }

    /**
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyStudentInTeamExists($query, int $partnerId, int $teamId, string $usersAlias = 'users'): void
    {
        if ($teamId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function (QueryBuilder $sub) use ($partnerId, $teamId, $usersAlias) {
            $sub->selectRaw('1')
                ->from('team_user')
                ->whereColumn('team_user.user_id', "{$usersAlias}.id")
                ->where('team_user.partner_id', $partnerId)
                ->where('team_user.team_id', $teamId);
        });
    }

    /**
     * @param  int[]  $teamIds
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyStudentInAnyTeamExists($query, int $partnerId, array $teamIds, string $usersAlias = 'users'): void
    {
        $teamIds = array_values(array_unique(array_filter(
            array_map('intval', $teamIds),
            fn (int $id) => $id > 0
        )));

        if ($teamIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function (QueryBuilder $sub) use ($partnerId, $teamIds, $usersAlias) {
            $sub->selectRaw('1')
                ->from('team_user')
                ->whereColumn('team_user.user_id', "{$usersAlias}.id")
                ->where('team_user.partner_id', $partnerId)
                ->whereIn('team_user.team_id', $teamIds);
        });
    }

    /**
     * Фильтр задолженностей: ученик без групп или с группой без объекта.
     *
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyDebtLocationNoneFilter($query, int $partnerId, string $usersAlias = 'users'): void
    {
        $query->where(function ($q) use ($partnerId, $usersAlias) {
            $q->whereNotExists(function (QueryBuilder $sub) use ($partnerId, $usersAlias) {
                $sub->selectRaw('1')
                    ->from('team_user')
                    ->whereColumn('team_user.user_id', "{$usersAlias}.id")
                    ->where('team_user.partner_id', $partnerId);
            })->orWhereExists(function (QueryBuilder $sub) use ($partnerId, $usersAlias) {
                $sub->selectRaw('1')
                    ->from('team_user')
                    ->join('teams', 'teams.id', '=', 'team_user.team_id')
                    ->whereColumn('team_user.user_id', "{$usersAlias}.id")
                    ->where('team_user.partner_id', $partnerId)
                    ->where('teams.partner_id', $partnerId)
                    ->whereNull('teams.location_id');
            });
        });
    }

    /**
     * @param  QueryBuilder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyDebtLocationFilter($query, int $partnerId, int $locationId, string $usersAlias = 'users'): void
    {
        if ($locationId <= 0) {
            return;
        }

        $query->whereExists(function (QueryBuilder $sub) use ($partnerId, $locationId, $usersAlias) {
            $sub->selectRaw('1')
                ->from('team_user')
                ->join('teams', 'teams.id', '=', 'team_user.team_id')
                ->whereColumn('team_user.user_id', "{$usersAlias}.id")
                ->where('team_user.partner_id', $partnerId)
                ->where('teams.partner_id', $partnerId)
                ->where('teams.location_id', $locationId);
        });
    }
}
