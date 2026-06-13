<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use App\Support\PartnerAdminUserOptions;

final class LocationAdminUsersSyncService
{
    /**
     * Полная замена списка администраторов объекта.
     *
     * @param  int[]  $userIds
     */
    public function syncAdminsForLocation(Location $location, array $userIds): void
    {
        $partnerId = (int) $location->partner_id;
        $userIds = $this->normalizeIds($userIds);

        $adminRoleId = PartnerAdminUserOptions::systemAdminRoleId();
        $validUserIds = $userIds === [] || $adminRoleId === null
            ? []
            : User::query()
                ->where('partner_id', $partnerId)
                ->where('role_id', $adminRoleId)
                ->where('is_enabled', 1)
                ->whereIn('id', $userIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $syncPayload = [];
        foreach ($validUserIds as $userId) {
            $syncPayload[$userId] = ['partner_id' => $partnerId];
        }

        $location->adminUsers()->sync($syncPayload);
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
