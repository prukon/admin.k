<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\Chat\UserPresence;

/**
 * Снимок пользователей онлайн для системного монитора:
 * last_seen_at в окне UserPresence::ONLINE_WITHIN_SECONDS, все роли и партнёры.
 * Зрителя (excludeUserId) в списке нет.
 */
final class OnlineUsersMonitor
{
    public const MISSING_PARTNER_TITLE = 'Без партнёра';

    /**
     * @param  int|null  $excludeUserId  Id зрителя: в списке и в total его нет.
     * @return array{
     *     ok: bool,
     *     online_within_seconds: int,
     *     total: int,
     *     partners: list<array{id: int|null, title: string, count: int, users: list<array{id: int, name: string}>}>
     * }
     */
    public static function snapshot(?int $excludeUserId = null): array
    {
        $seconds = UserPresence::ONLINE_WITHIN_SECONDS;
        $since = now()->subSeconds($seconds);

        $query = User::query()
            ->with(['partner' => static function ($query): void {
                $query->withTrashed();
            }])
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $since);

        if ($excludeUserId !== null && $excludeUserId > 0) {
            $query->where('id', '!=', $excludeUserId);
        }

        $users = $query
            ->orderBy('lastname')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'lastname', 'partner_id', 'last_seen_at', 'is_enabled']);

        /** @var array<string, array{id: int|null, title: string, users: list<array{id: int, name: string}>}> $groups */
        $groups = [];

        foreach ($users as $user) {
            $partner = $user->partner;
            if ($partner === null) {
                $key = 'none';
                $partnerId = null;
                $title = self::MISSING_PARTNER_TITLE;
            } else {
                $key = 'p'.(int) $partner->id;
                $partnerId = (int) $partner->id;
                $title = trim((string) $partner->title);
                if ($title === '') {
                    $title = 'Без названия';
                }
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $partnerId,
                    'title' => $title,
                    'users' => [],
                ];
            }

            $name = trim((string) $user->full_name);
            $groups[$key]['users'][] = [
                'id' => (int) $user->id,
                'name' => $name !== '' ? $name : ('#'.(int) $user->id),
            ];
        }

        uasort($groups, static function (array $left, array $right): int {
            if ($left['id'] === null && $right['id'] !== null) {
                return 1;
            }
            if ($left['id'] !== null && $right['id'] === null) {
                return -1;
            }

            return strnatcasecmp($left['title'], $right['title']);
        });

        $partners = [];
        foreach ($groups as $group) {
            $partners[] = [
                'id' => $group['id'],
                'title' => $group['title'],
                'count' => count($group['users']),
                'users' => $group['users'],
            ];
        }

        return [
            'ok' => true,
            'online_within_seconds' => $seconds,
            'total' => $users->count(),
            'partners' => $partners,
        ];
    }
}
