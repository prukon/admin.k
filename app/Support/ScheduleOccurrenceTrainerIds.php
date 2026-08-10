<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TrainerProfile;
use Illuminate\Support\Collection;

/**
 * Нормализация и отображение тренеров на событии статуса занятия в журнале.
 */
final class ScheduleOccurrenceTrainerIds
{
    /**
     * @return list<int>
     */
    public static function normalize(mixed $ids, mixed $legacySingle = null): array
    {
        if ($ids === null && $legacySingle !== null) {
            $ids = [$legacySingle];
        }

        if ($ids === null || $ids === '' || $ids === []) {
            return [];
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '' || $id === 'none' || $id === '0' || $id === 0) {
                continue;
            }
            $int = (int) $id;
            if ($int > 0 && ! in_array($int, $normalized, true)) {
                $normalized[] = $int;
            }
        }

        return $normalized;
    }

    /**
     * Имена через запятую для тултипа / ответа API; null если список пуст.
     *
     * @param  Collection<int, TrainerProfile>|iterable<TrainerProfile>  $profiles
     */
    public static function formatNames(iterable $profiles): ?string
    {
        $names = [];
        foreach ($profiles as $profile) {
            $name = trim((string) ($profile->user?->full_name ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        if ($names === []) {
            return null;
        }

        return implode(', ', $names);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function existingForPartner(int $partnerId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = TrainerProfile::query()
            ->where('partner_id', $partnerId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $foundSet = array_flip($found);

        return array_values(array_filter(
            $ids,
            static fn (int $id): bool => isset($foundSet[$id])
        ));
    }
}
