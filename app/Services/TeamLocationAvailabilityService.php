<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Support\UserTeamQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class TeamLocationAvailabilityService
{
    /**
     * Группа доступна в объекте, если у неё задан location_id и он совпадает.
     * При $locationId = null ограничений нет.
     */
    public function isTeamAllowedAtLocation(Team $team, ?int $locationId): bool
    {
        if ($locationId === null || $locationId <= 0) {
            return true;
        }

        if ($team->location_id === null) {
            return false;
        }

        return (int) $team->location_id === $locationId;
    }

    public function assertTeamAllowedAtLocation(Team $team, ?int $locationId): ?string
    {
        if ($this->isTeamAllowedAtLocation($team, $locationId)) {
            return null;
        }

        return 'Выбранная группа недоступна для этого объекта.';
    }

    /**
     * @param  Builder<Team>  $query
     * @return Builder<Team>
     */
    public function scopeAvailableForLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId === null || $locationId <= 0) {
            return $query;
        }

        return $query->where('teams.location_id', $locationId);
    }

    /**
     * Фильтр задолженностей (Query Builder по users) по объекту через группу.
     *
     * @param  QueryBuilder  $query
     */
    public function applyDebtUserTeamLocationFilter($query, int $partnerId, ?string $filterLocationId): void
    {
        if ($filterLocationId === null || $filterLocationId === '') {
            return;
        }

        if ($filterLocationId === 'none') {
            UserTeamQuery::applyDebtLocationNoneFilter($query, $partnerId);

            return;
        }

        if (! ctype_digit((string) $filterLocationId)) {
            return;
        }

        $locationId = (int) $filterLocationId;
        if ($locationId <= 0) {
            return;
        }

        UserTeamQuery::applyDebtLocationFilter($query, $partnerId, $locationId);
    }
}
