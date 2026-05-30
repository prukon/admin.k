<?php

namespace App\Services\SchoolLeads;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Последний договор (по created_at, затем id) для каждого user_id в рамках партнёра.
 */
final class LatestUserContractLookup
{
    public const FILTER_WITH     = 'with';
    public const FILTER_WITHOUT  = 'without';
    public const FILTER_SIGNED   = 'signed';
    public const FILTER_UNSIGNED = 'unsigned';

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

    /**
     * Подзапрос: последний договор партнёра на каждого user_id.
     */
    public function latestContractsSubquery(int $partnerId): QueryBuilder
    {
        return DB::table('contracts as uc')
            ->select([
                'uc.user_id',
                'uc.id as contract_id',
                'uc.status',
                'uc.created_at',
            ])
            ->where('uc.school_id', $partnerId)
            ->whereRaw(
                'uc.id = (
                    SELECT c2.id
                    FROM contracts c2
                    WHERE c2.user_id = uc.user_id
                      AND c2.school_id = ?
                    ORDER BY c2.created_at DESC, c2.id DESC
                    LIMIT 1
                )',
                [$partnerId]
            );
    }

    public function applyUsersListContractFilter(EloquentBuilder $query, int $partnerId, ?string $filter): void
    {
        $filter = trim((string) $filter);

        if ($filter === '') {
            return;
        }

        match ($filter) {
            self::FILTER_WITH => $query->whereExists(function ($sub) use ($partnerId) {
                $sub->selectRaw('1')
                    ->from('contracts')
                    ->whereColumn('contracts.user_id', 'users.id')
                    ->where('contracts.school_id', $partnerId);
            }),
            self::FILTER_WITHOUT => $query->whereNotExists(function ($sub) use ($partnerId) {
                $sub->selectRaw('1')
                    ->from('contracts')
                    ->whereColumn('contracts.user_id', 'users.id')
                    ->where('contracts.school_id', $partnerId);
            }),
            self::FILTER_SIGNED, self::FILTER_UNSIGNED => $query->whereIn('users.id', function ($sub) use ($partnerId, $filter) {
                $sub->select('user_id')
                    ->fromSub($this->latestContractsSubquery($partnerId), 'users_latest_contract_filter');

                if ($filter === self::FILTER_SIGNED) {
                    $sub->where('users_latest_contract_filter.status', Contract::STATUS_SIGNED);
                } else {
                    $sub->where('users_latest_contract_filter.status', '!=', Contract::STATUS_SIGNED);
                }
            }),
            default => null,
        };
    }

    public function applyUsersListSortByLatestContractStatus(
        EloquentBuilder $query,
        int $partnerId,
        string $direction
    ): void {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $query
            ->leftJoinSub(
                $this->latestContractsSubquery($partnerId),
                'users_latest_contract_sort',
                'users_latest_contract_sort.user_id',
                '=',
                'users.id'
            )
            ->select('users.*')
            ->orderBy('users_latest_contract_sort.status', $dir)
            ->orderBy('users.lastname', 'asc')
            ->orderBy('users.name', 'asc');
    }

    public function formatActionLabel(Contract $contract): string
    {
        $statusLabel = Contract::$STATUS_RU[$contract->status] ?? $contract->status;

        return sprintf('Договор №%d (%s)', $contract->id, $statusLabel);
    }
}
