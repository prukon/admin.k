<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

final class LocationTeamSyncService
{
    /**
     * Полная замена привязок локаций для группы.
     *
     * @param  int[]  $locationIds
     */
    public function syncLocationsForTeam(Team $team, array $locationIds): void
    {
        $partnerId = (int) $team->partner_id;

        $locationIds = $this->normalizeIds($locationIds);

        $validLocationIds = $locationIds === []
            ? []
            : Location::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', $locationIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        DB::table('location_team')
            ->where('team_id', $team->id)
            ->where('partner_id', $partnerId)
            ->when($validLocationIds !== [], fn ($q) => $q->whereNotIn('location_id', $validLocationIds))
            ->delete();

        foreach ($validLocationIds as $locationId) {
            DB::table('location_team')->updateOrInsert(
                [
                    'location_id' => $locationId,
                    'team_id' => $team->id,
                ],
                [
                    'partner_id' => $partnerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * Полная замена привязок групп для локации.
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

        DB::table('location_team')
            ->where('location_id', $location->id)
            ->where('partner_id', $partnerId)
            ->when($validTeamIds !== [], fn ($q) => $q->whereNotIn('team_id', $validTeamIds))
            ->delete();

        foreach ($validTeamIds as $teamId) {
            DB::table('location_team')->updateOrInsert(
                [
                    'location_id' => $location->id,
                    'team_id' => $teamId,
                ],
                [
                    'partner_id' => $partnerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
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
