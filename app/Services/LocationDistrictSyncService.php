<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\District;
use App\Models\Location;

final class LocationDistrictSyncService
{
    /**
     * Полная замена списка объектов района: выбранным — district_id, снятым с района — null.
     *
     * @param  int[]  $locationIds
     */
    public function syncLocationsForDistrict(District $district, array $locationIds): void
    {
        $partnerId = (int) $district->partner_id;
        $locationIds = $this->normalizeIds($locationIds);

        $validLocationIds = $locationIds === []
            ? []
            : Location::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', $locationIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        Location::query()
            ->where('partner_id', $partnerId)
            ->where('district_id', $district->id)
            ->when($validLocationIds !== [], fn ($q) => $q->whereNotIn('id', $validLocationIds))
            ->update(['district_id' => null]);

        if ($validLocationIds !== []) {
            Location::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', $validLocationIds)
                ->update(['district_id' => $district->id]);
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
