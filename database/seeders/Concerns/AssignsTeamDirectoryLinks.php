<?php

namespace Database\Seeders\Concerns;

use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait AssignsTeamDirectoryLinks
{
    /**
     * Явная привязка групп к видам спорта партнёра (чередование по id группы).
     */
    protected function assignSportTypesToAllTeams(): void
    {
        /** @var Collection<int, Collection<int, object>> $sportTypesByPartner */
        $sportTypesByPartner = DB::table('sport_types')
            ->where('is_enabled', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'partner_id'])
            ->groupBy('partner_id');

        if ($sportTypesByPartner->isEmpty()) {
            return;
        }

        Team::query()->each(function (Team $team) use ($sportTypesByPartner): void {
            $partnerId = (int) $team->partner_id;
            /** @var Collection<int, object>|null $types */
            $types = $sportTypesByPartner->get($partnerId);

            if ($types === null || $types->isEmpty()) {
                return;
            }

            $sportTypeId = $this->resolveRoundRobinId((int) $team->id, $types);
            if ($sportTypeId === null) {
                return;
            }

            if ((int) $team->sport_type_id !== $sportTypeId) {
                $team->update(['sport_type_id' => $sportTypeId]);
            }
        });
    }

    /**
     * Явная привязка групп к локациям партнёра (после DevLocationsSeeder).
     */
    protected function assignLocationsToAllTeams(): void
    {
        /** @var Collection<int, Collection<int, object>> $locationsByPartner */
        $locationsByPartner = DB::table('locations')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get(['id', 'partner_id'])
            ->groupBy('partner_id');

        if ($locationsByPartner->isEmpty()) {
            return;
        }

        Team::query()->each(function (Team $team) use ($locationsByPartner): void {
            $partnerId = (int) $team->partner_id;
            /** @var Collection<int, object>|null $locations */
            $locations = $locationsByPartner->get($partnerId);

            if ($locations === null || $locations->isEmpty()) {
                return;
            }

            $locationId = $this->resolveRoundRobinId((int) $team->id, $locations);
            if ($locationId === null) {
                return;
            }

            if ((int) $team->location_id !== $locationId) {
                $team->update(['location_id' => $locationId]);
            }
        });
    }

    /**
     * @param  Collection<int, object>  $items
     */
    protected function resolveRoundRobinId(int $teamId, Collection $items): ?int
    {
        if ($items->isEmpty()) {
            return null;
        }

        $index = max(0, $teamId - 1) % $items->count();

        return (int) $items->values()[$index]->id;
    }
}
