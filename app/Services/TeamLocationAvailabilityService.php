<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class TeamLocationAvailabilityService
{
    /**
     * Группа доступна в локации, если у неё нет привязок к локациям
     * или есть привязка к указанной локации.
     * При $locationId = null ограничений нет.
     */
    public function isTeamAllowedAtLocation(Team $team, ?int $locationId): bool
    {
        if ($locationId === null || $locationId <= 0) {
            return true;
        }

        $partnerId = (int) $team->partner_id;

        $boundCount = (int) DB::table('location_team')
            ->where('team_id', $team->id)
            ->where('partner_id', $partnerId)
            ->count();

        if ($boundCount === 0) {
            return true;
        }

        return DB::table('location_team')
            ->where('team_id', $team->id)
            ->where('partner_id', $partnerId)
            ->where('location_id', $locationId)
            ->exists();
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

        return $query->where(function (Builder $q) use ($locationId) {
            $q->whereDoesntHave('locations')
                ->orWhereHas('locations', fn (Builder $lq) => $lq->where('locations.id', $locationId));
        });
    }

    /**
     * Фильтр задолженностей (Query Builder по users) по локации через группу.
     *
     * @param  QueryBuilder  $query
     */
    public function applyDebtUserTeamLocationFilter($query, int $partnerId, ?string $filterLocationId): void
    {
        if ($filterLocationId === null || $filterLocationId === '') {
            return;
        }

        if ($filterLocationId === 'none') {
            $query->where(function ($q) use ($partnerId) {
                $q->whereNull('users.team_id')
                    ->orWhereIn('users.team_id', $this->universalTeamIdsSubquery($partnerId));
            });

            return;
        }

        if (! ctype_digit((string) $filterLocationId)) {
            return;
        }

        $locationId = (int) $filterLocationId;
        if ($locationId <= 0) {
            return;
        }

        $query->whereIn('users.team_id', $this->teamIdsAvailableAtLocationSubquery($partnerId, $locationId));
    }

    /**
     * @return \Closure(QueryBuilder): void
     */
    private function teamIdsAvailableAtLocationSubquery(int $partnerId, int $locationId): \Closure
    {
        return function (QueryBuilder $sub) use ($partnerId, $locationId) {
            $sub->select('teams.id')
                ->from('teams')
                ->where('teams.partner_id', $partnerId)
                ->where(function (QueryBuilder $q) use ($partnerId, $locationId) {
                    $q->whereNotExists(function (QueryBuilder $ex) use ($partnerId) {
                        $ex->select(DB::raw('1'))
                            ->from('location_team')
                            ->whereColumn('location_team.team_id', 'teams.id')
                            ->where('location_team.partner_id', $partnerId);
                    })->orWhereExists(function (QueryBuilder $ex) use ($partnerId, $locationId) {
                        $ex->select(DB::raw('1'))
                            ->from('location_team')
                            ->whereColumn('location_team.team_id', 'teams.id')
                            ->where('location_team.partner_id', $partnerId)
                            ->where('location_team.location_id', $locationId);
                    });
                });
        };
    }

    /**
     * @return \Closure(QueryBuilder): void
     */
    private function universalTeamIdsSubquery(int $partnerId): \Closure
    {
        return function (QueryBuilder $sub) use ($partnerId) {
            $sub->select('teams.id')
                ->from('teams')
                ->where('teams.partner_id', $partnerId)
                ->whereNotExists(function (QueryBuilder $ex) use ($partnerId) {
                    $ex->select(DB::raw('1'))
                        ->from('location_team')
                        ->whereColumn('location_team.team_id', 'teams.id')
                        ->where('location_team.partner_id', $partnerId);
                });
        };
    }
}
