<?php

namespace App\Services\SchoolLeads;

use App\Models\Contract;
use Illuminate\Support\Collection;

/**
 * Последний договор (по created_at, затем id) для каждого user_id в рамках партнёра.
 */
final class LatestUserContractLookup
{
    /**
     * @param  list<int>  $userIds
     * @return Collection<int, Contract> keyed by user_id
     */
    public function forUserIds(int $partnerId, array $userIds): Collection
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if ($userIds === []) {
            return collect();
        }

        $contracts = Contract::query()
            ->where('school_id', $partnerId)
            ->whereIn('user_id', $userIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $contracts->unique('user_id')->keyBy('user_id');
    }

    public function formatActionLabel(Contract $contract): string
    {
        $statusLabel = Contract::$STATUS_RU[$contract->status] ?? $contract->status;

        return sprintf('Договор №%d (%s)', $contract->id, $statusLabel);
    }
}
