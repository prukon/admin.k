<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\Team;

final class TeamLocationSyncService
{
    /**
     * Полная замена списка групп объекта: выбранным — location_id, снятым с объекта — null.
     *
     * @param  int[]  $teamIds
     */
    public function syncTeamsForLocation(Location $location, array $teamIds): void
    {
        $partnerId = (int) $location->partner_id;
        $teamIds = $this->normalizeIds($teamIds);

        $validTeamIds = $teamIds === []
            ? []
            : Team::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', $teamIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        Team::query()
            ->where('partner_id', $partnerId)
            ->where('location_id', $location->id)
            ->when($validTeamIds !== [], fn ($q) => $q->whereNotIn('id', $validTeamIds))
            ->update(['location_id' => null]);

        if ($validTeamIds !== []) {
            Team::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', $validTeamIds)
                ->update(['location_id' => $location->id]);
        }
    }

    public function resolveLocationIdForTeam(int $partnerId, mixed $locationId): ?int
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $locationId = (int) $locationId;
        if ($locationId <= 0) {
            return null;
        }

        $exists = Location::query()
            ->where('partner_id', $partnerId)
            ->whereKey($locationId)
            ->exists();

        return $exists ? $locationId : null;
    }

    /**
     * @param  array<int|string>  $ids
     * @return int[]
     */
    private function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return array_values(array_filter($ids, fn (int $id) => $id > 0));
    }
}
