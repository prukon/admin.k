<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserTableSetting;
use Illuminate\Support\Collection;

/**
 * Месяц и фильтры вкладки «По месяцам» — отдельно на каждого пользователя.
 * Хранится в user_table_settings.columns (table_key = setting_prices_monthly).
 */
final class SettingPricesMonthlyViewStateService
{
    public const TABLE_KEY = 'setting_prices_monthly';

    /**
     * @return array{
     *     month: ?string,
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }
     */
    public function defaults(): array
    {
        return [
            'month' => null,
            'team_title' => '',
            'team_package' => '',
            'team_price' => '',
            'user_name' => '',
            'user_paid' => '',
            'user_membership' => '',
            'user_package' => '',
            'location_id' => '',
            'admin_user_id' => '',
        ];
    }

    /**
     * @param  list<int>  $packageIds
     * @return array{
     *     month: ?string,
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }
     */
    public function resolvedForUser(int $userId, array $packageIds): array
    {
        $state = $this->forUserId($userId);
        $cleaned = $this->dropUnknownPackages($state, $packageIds);
        if ($cleaned !== $state) {
            $this->persist($userId, $cleaned);
        }

        return $cleaned;
    }

    /**
     * @return array{
     *     month: ?string,
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }
     */
    public function forUserId(int $userId): array
    {
        $row = UserTableSetting::query()
            ->where('user_id', $userId)
            ->where('table_key', self::TABLE_KEY)
            ->first();

        $columns = $row?->columns;

        return $this->normalize(is_array($columns) ? $columns : null);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function saveFilters(int $userId, array $filters): void
    {
        $current = $this->forUserId($userId);
        $next = $this->normalize(array_merge($current, $filters));
        $next['month'] = $current['month'];
        $this->persist($userId, $next);
    }

    public function clearFilters(int $userId): void
    {
        $current = $this->forUserId($userId);
        $next = $this->defaults();
        $next['month'] = $current['month'];
        $this->persist($userId, $next);
    }

    public function saveMonth(int $userId, string $month): void
    {
        $current = $this->forUserId($userId);
        $current['month'] = trim($month) !== '' ? trim($month) : null;
        $this->persist($userId, $this->normalize($current));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function hasActiveFilters(array $state): bool
    {
        foreach ([
            'team_title',
            'team_package',
            'team_price',
            'user_name',
            'user_paid',
            'user_membership',
            'user_package',
            'location_id',
            'admin_user_id',
        ] as $key) {
            if (trim((string) ($state[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, mixed>  $teams
     * @param  Collection<int|string, mixed>  $teamPrices
     * @param  array<string, mixed>  $state
     * @return Collection<int, mixed>
     */
    public function filterTeams(Collection $teams, Collection $teamPrices, array $state): Collection
    {
        $title = mb_strtolower(trim((string) ($state['team_title'] ?? '')));
        $package = (string) ($state['team_package'] ?? '');
        $price = (string) ($state['team_price'] ?? '');
        $locationFilter = (string) ($state['location_id'] ?? '');
        $adminFilter = (string) ($state['admin_user_id'] ?? '');

        return $teams
            ->filter(function ($team) use ($teamPrices, $title, $package, $price, $locationFilter, $adminFilter) {
                $teamTitle = mb_strtolower(trim((string) ($team->title ?? '')));
                if ($title !== '' && ! str_contains($teamTitle, $title)) {
                    return false;
                }

                if (! $this->teamMatchesLocation($team, $locationFilter, $adminFilter)) {
                    return false;
                }

                $row = $teamPrices->get($team->id);
                if ($row === null) {
                    $row = $teamPrices->get((string) $team->id);
                }
                $packageId = $row?->lesson_package_id;
                if ($package === 'none' && $packageId !== null) {
                    return false;
                }
                if ($package !== '' && $package !== 'none' && (int) $packageId !== (int) $package) {
                    return false;
                }

                $cents = (int) ($row->price_cents ?? 0);
                if ($price === 'set' && $cents <= 0) {
                    return false;
                }
                if ($price === 'unset' && $cents > 0) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Убирает id объекта или админа, которых больше нет в списках партнёра.
     *
     * @param  array<string, mixed>  $state
     * @param  list<int>  $locationIds
     * @param  list<int>  $adminIds
     * @return array<string, mixed>
     */
    public function alignDirectoryFilters(int $userId, array $state, array $locationIds, array $adminIds): array
    {
        $cleaned = $state;
        $cleaned['location_id'] = $this->keepKnownChoice((string) ($state['location_id'] ?? ''), $locationIds);
        $cleaned['admin_user_id'] = $this->keepKnownChoice((string) ($state['admin_user_id'] ?? ''), $adminIds);
        if ($cleaned !== $state) {
            $this->saveFilters($userId, $cleaned);
            $cleaned['month'] = $state['month'] ?? null;
        }

        return $cleaned;
    }

    private function teamMatchesLocation(object $team, string $locationFilter, string $adminFilter): bool
    {
        $locationId = $team->location_id ?? null;

        if ($locationFilter === 'none' && $locationId !== null) {
            return false;
        }
        if ($locationFilter !== '' && $locationFilter !== 'none' && (int) $locationId !== (int) $locationFilter) {
            return false;
        }

        if ($adminFilter === '') {
            return true;
        }

        $adminIds = $this->locationAdminIds($team);
        if ($adminFilter === 'none') {
            return $locationId === null || $adminIds === [];
        }

        return in_array((int) $adminFilter, $adminIds, true);
    }

    /**
     * @return list<int>
     */
    private function locationAdminIds(object $team): array
    {
        $location = $team->location ?? null;
        if ($location === null) {
            return [];
        }

        if ($location->relationLoaded('adminUsers')) {
            return $location->adminUsers
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        return $location->adminUsers()
            ->pluck('users.id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $knownIds
     */
    private function keepKnownChoice(string $value, array $knownIds): string
    {
        if ($value === '' || $value === 'none') {
            return $value;
        }
        if (! ctype_digit($value)) {
            return '';
        }

        return in_array((int) $value, $knownIds, true) ? $value : '';
    }

    /**
     * @param  list<User>  $usersTeam
     * @param  list<UserPrice>  $usersPrice
     * @param  array<string, mixed>  $state
     * @return array{0: list<User>, 1: list<UserPrice>}
     */
    public function filterMonthlyUsers(array $usersTeam, array $usersPrice, array $state): array
    {
        $name = mb_strtolower(trim((string) ($state['user_name'] ?? '')));
        $paid = (string) ($state['user_paid'] ?? '');
        $membership = (string) ($state['user_membership'] ?? '');
        $package = (string) ($state['user_package'] ?? '');

        if ($name === '' && $paid === '' && $membership === '' && $package === '') {
            return [$usersTeam, $usersPrice];
        }

        $usersById = [];
        foreach ($usersTeam as $user) {
            if ($user instanceof User) {
                $usersById[(int) $user->id] = $user;
            }
        }

        $allowed = [];
        $filteredPrices = [];
        foreach ($usersPrice as $row) {
            if (! $row instanceof UserPrice) {
                continue;
            }
            $userId = (int) $row->user_id;
            $user = $usersById[$userId] ?? ($row->relationLoaded('user') ? $row->user : null);
            if ($name !== '') {
                $full = $user instanceof User
                    ? mb_strtolower(trim((string) $user->lastname.' '.(string) $user->name))
                    : '';
                if ($full === '' || ! str_contains($full, $name)) {
                    continue;
                }
            }
            if ($paid === 'paid' && ! $row->effective_is_paid) {
                continue;
            }
            if ($paid === 'unpaid' && $row->effective_is_paid) {
                continue;
            }
            $former = (bool) $row->getAttribute('is_former_member');
            if ($membership === 'current' && $former) {
                continue;
            }
            if ($membership === 'former' && ! $former) {
                continue;
            }
            $packageId = $row->lesson_package_id;
            if ($package === 'none' && $packageId !== null) {
                continue;
            }
            if ($package !== '' && $package !== 'none' && (int) $packageId !== (int) $package) {
                continue;
            }

            $allowed[$userId] = true;
            $filteredPrices[] = $row;
        }

        $filteredUsers = [];
        foreach ($usersTeam as $user) {
            if ($user instanceof User && isset($allowed[(int) $user->id])) {
                $filteredUsers[] = $user;
            }
        }

        return [$filteredUsers, $filteredPrices];
    }

    /**
     * @param  array<string, mixed>|null  $columns
     * @return array{
     *     month: ?string,
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }
     */
    public function normalize(?array $columns): array
    {
        $defaults = $this->defaults();
        if (! is_array($columns)) {
            return $defaults;
        }

        $month = trim((string) ($columns['month'] ?? ''));

        return [
            'month' => $month !== '' ? $month : null,
            'team_title' => $this->text($columns['team_title'] ?? ''),
            'team_package' => $this->packageValue($columns['team_package'] ?? ''),
            'team_price' => $this->enumValue($columns['team_price'] ?? '', ['set', 'unset']),
            'user_name' => $this->text($columns['user_name'] ?? ''),
            'user_paid' => $this->enumValue($columns['user_paid'] ?? '', ['paid', 'unpaid']),
            'user_membership' => $this->enumValue($columns['user_membership'] ?? '', ['current', 'former']),
            'user_package' => $this->packageValue($columns['user_package'] ?? ''),
            'location_id' => $this->packageValue($columns['location_id'] ?? ''),
            'admin_user_id' => $this->packageValue($columns['admin_user_id'] ?? ''),
        ];
    }

    /**
     * @param  array{
     *     month: ?string,
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }  $state
     * @param  list<int>  $packageIds
     * @return array{
     *     month: ?string,
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }
     */
    public function dropUnknownPackages(array $state, array $packageIds): array
    {
        $known = [];
        foreach ($packageIds as $id) {
            $known[(int) $id] = true;
        }

        foreach (['team_package', 'user_package'] as $key) {
            $value = (string) ($state[$key] ?? '');
            if ($value === '' || $value === 'none') {
                continue;
            }
            if (! isset($known[(int) $value])) {
                $state[$key] = '';
            }
        }

        return $state;
    }

    /**
     * @param  array{
     *     month: ?string,
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }  $state
     */
    private function persist(int $userId, array $state): void
    {
        UserTableSetting::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => self::TABLE_KEY,
            ],
            [
                'columns' => $state,
            ]
        );
    }

    private function text(mixed $value): string
    {
        $text = trim((string) $value);

        return mb_substr($text, 0, 255);
    }

    private function packageValue(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '' || $text === 'none') {
            return $text === 'none' ? 'none' : '';
        }
        if (! ctype_digit($text)) {
            return '';
        }

        return $text;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function enumValue(mixed $value, array $allowed): string
    {
        $text = trim((string) $value);

        return in_array($text, $allowed, true) ? $text : '';
    }
}
