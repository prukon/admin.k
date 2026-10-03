<?php

declare(strict_types=1);

namespace App\Services\Tables;

use App\Models\Contract;
use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLeadStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTableSetting;
use App\Services\TrainerOwnTeamsScope;

/**
 * Фильтры списков на пользователя: user_table_settings.filters.
 * Колонки остаются в columns, «Показать N» — в page_length.
 */
final class PersistedListFilters
{
    public const USERS = 'users_index';

    public const TRAINERS = 'trainers_index';

    public const ADMINISTRATORS = 'role_staff_admin';

    public const SCHOOL_LEADS = 'school_leads_index';

    public const CONTRACTS = 'contracts_index';

    public const CONTRACTS_DEFAULT_PAGE_LENGTH = 20;

    /** @var list<string> */
    public const CONTRACT_STATUSES = [
        Contract::STATUS_DRAFT,
        Contract::STATUS_AWAITING_CLIENT_FILL,
        Contract::STATUS_SENT,
        Contract::STATUS_OPENED,
        Contract::STATUS_SIGNED,
        Contract::STATUS_REVOKED,
        Contract::STATUS_EXPIRED,
        Contract::STATUS_FAILED,
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function store(int $userId, string $tableKey, array $filters): void
    {
        UserTableSetting::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => $tableKey,
            ],
            [
                'filters' => $filters,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function present(?User $user, string $tableKey): array
    {
        $defaults = $this->defaults($user, $tableKey);
        $raw = $this->loadRaw($user, $tableKey);
        if ($raw === null) {
            return $defaults;
        }

        return $this->sanitize($raw, $tableKey, $user);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function isActive(array $filters, string $tableKey, ?User $user): bool
    {
        return $this->signature($filters) !== $this->signature($this->defaults($user, $tableKey));
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(?User $user, string $tableKey): array
    {
        return match ($tableKey) {
            self::USERS => [
                'name' => '',
                'team_id' => '',
                'status' => 'active',
                'contract' => '',
            ],
            self::TRAINERS => [
                'name' => '',
                'team_id' => '',
                'status' => 'active',
            ],
            self::ADMINISTRATORS => [
                'name' => '',
                'status' => 'active',
            ],
            self::SCHOOL_LEADS => [
                'status_ids' => $this->defaultLeadStatusIds(),
                'district_id' => '',
                'location_ids' => [],
                'team_ids' => [],
                'has_special_conditions' => 0,
            ],
            self::CONTRACTS => [
                'search_value' => '',
                'group_id' => '',
                'status' => '',
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input, string $tableKey, ?User $user): array
    {
        return $this->sanitize($input, $tableKey, $user);
    }

    /**
     * @return list<string>
     */
    public function allowedTeamValues(?User $user, string $tableKey): array
    {
        $values = [''];
        if (in_array($tableKey, [self::USERS, self::SCHOOL_LEADS, self::CONTRACTS], true)) {
            $values[] = 'none';
        }

        return array_merge($values, $this->teamIds($user, $tableKey === self::SCHOOL_LEADS));
    }

    /**
     * @return list<string>
     */
    public function allowedLeadStatusIds(): array
    {
        return SchoolLeadStatus::query()
            ->availableForPartner($this->partnerId())
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allowedLocationValues(): array
    {
        $ids = Location::query()
            ->where('partner_id', $this->partnerId())
            ->where('is_enabled', true)
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        return array_merge(['none'], $ids);
    }

    /**
     * @return list<string>
     */
    public function allowedDistrictValues(): array
    {
        $ids = District::query()
            ->where('partner_id', $this->partnerId())
            ->where('is_enabled', true)
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        return array_merge(['', 'none'], $ids);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadRaw(?User $user, string $tableKey): ?array
    {
        $userId = (int) ($user?->id ?? 0);
        if ($userId < 1) {
            return null;
        }

        $raw = UserTableSetting::query()
            ->where('user_id', $userId)
            ->where('table_key', $tableKey)
            ->value('filters');

        return is_array($raw) ? $raw : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function sanitize(array $raw, string $tableKey, ?User $user): array
    {
        $filters = $this->defaults($user, $tableKey);

        foreach ($filters as $key => $default) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $filters[$key] = $this->sanitizeValue($key, $raw[$key], $tableKey, $user);
        }

        return $this->visibleFor($user, $tableKey, $filters);
    }

    private function sanitizeValue(string $key, mixed $raw, string $tableKey, ?User $user): mixed
    {
        if (in_array($key, ['status_ids', 'location_ids', 'team_ids'], true)) {
            return $this->idList($raw, $this->allowedList($key, $user));
        }

        if ($key === 'has_special_conditions') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (in_array($key, ['name', 'search_value'], true)) {
            $text = trim((string) ($raw ?? ''));
            if (mb_strlen($text) > 255) {
                $text = mb_substr($text, 0, 255);
            }

            return $text;
        }

        $value = trim((string) ($raw ?? ''));

        if ($key === 'status' && $tableKey === self::CONTRACTS) {
            return in_array($value, self::CONTRACT_STATUSES, true) ? $value : '';
        }

        if ($key === 'status') {
            return in_array($value, ['', 'active', 'inactive'], true) ? $value : 'active';
        }

        if ($key === 'contract') {
            return in_array($value, ['', 'with', 'without', 'signed', 'unsigned'], true) ? $value : '';
        }

        if (in_array($key, ['team_id', 'group_id'], true)) {
            $allowed = $this->allowedTeamValues($user, $key === 'group_id' ? self::CONTRACTS : $tableKey);

            return in_array($value, $allowed, true) ? $value : '';
        }

        if ($key === 'district_id') {
            return in_array($value, $this->allowedDistrictValues(), true) ? $value : '';
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function allowedList(string $key, ?User $user): array
    {
        return match ($key) {
            'status_ids' => $this->allowedLeadStatusIds(),
            'location_ids' => $this->allowedLocationValues(),
            'team_ids' => $this->allowedTeamValues($user, self::SCHOOL_LEADS),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function visibleFor(?User $user, string $tableKey, array $filters): array
    {
        if ($tableKey === self::USERS && ! $user?->can('contracts.view')) {
            $filters['contract'] = '';
        }

        if ($tableKey === self::SCHOOL_LEADS && ! $user?->can('districts.view')) {
            $filters['district_id'] = '';
        }

        if ($tableKey === self::SCHOOL_LEADS && ! $user?->can('locations.view')) {
            $filters['location_ids'] = [];
        }

        return $filters;
    }

    /**
     * @return list<string>
     */
    private function idList(mixed $raw, array $allowed): array
    {
        $items = is_array($raw) ? $raw : (($raw === null || $raw === '') ? [] : [$raw]);
        $allowedMap = array_fill_keys($allowed, true);
        $out = [];

        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value === '' || ! isset($allowedMap[$value])) {
                continue;
            }
            $out[] = $value;
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function defaultLeadStatusIds(): array
    {
        return SchoolLeadStatus::query()
            ->availableForPartner($this->partnerId())
            ->where('is_default_in_filter', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function teamIds(?User $user, bool $enabledOnly): array
    {
        $query = Team::query()->where('partner_id', $this->partnerId());
        if ($enabledOnly) {
            $query->where('is_enabled', true);
        }

        app(TrainerOwnTeamsScope::class)->restrictTeamsQuery($query, $user, $this->partnerId());

        return $query->pluck('teams.id')
            ->map(static fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function signature(array $filters): string
    {
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $value = array_map('strval', $value);
                sort($value);
                $filters[$key] = array_values($value);

                continue;
            }

            $filters[$key] = (string) $value;
        }

        ksort($filters);

        return (string) json_encode($filters);
    }

    private function partnerId(): int
    {
        $partner = app()->bound('current_partner') ? app('current_partner') : null;

        return (int) ($partner->id ?? 0);
    }
}
