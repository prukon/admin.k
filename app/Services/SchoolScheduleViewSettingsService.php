<?php

namespace App\Services;

use App\Models\UserTableSetting;

/**
 * Визуальный диапазон времени календаря «Расписание школы» (per user, БД: user_table_settings).
 */
final class SchoolScheduleViewSettingsService
{
    public const TABLE_KEY = 'school_schedule_view';

    private const DEFAULT_START_MIN = 540; // 09:00

    private const DEFAULT_END_MIN = 1260; // 21:00

    /**
     * @return array{view_start_min: int, view_end_min: int}
     */
    public function defaults(): array
    {
        return [
            'view_start_min' => self::DEFAULT_START_MIN,
            'view_end_min' => self::DEFAULT_END_MIN,
        ];
    }

    /**
     * @return array{view_start_min: int, view_end_min: int}
     */
    public function getForUserId(int $userId): array
    {
        $row = UserTableSetting::query()
            ->where('user_id', $userId)
            ->where('table_key', self::TABLE_KEY)
            ->first();

        return $this->normalizeFromColumns($row?->columns);
    }

    /**
     * @param  array<string, mixed>|null  $columns
     * @return array{view_start_min: int, view_end_min: int}
     */
    public function normalizeFromColumns(?array $columns): array
    {
        $defaults = $this->defaults();
        if (! is_array($columns)) {
            return $defaults;
        }
        $start = isset($columns['view_start_min']) ? (int) $columns['view_start_min'] : $defaults['view_start_min'];
        $end = isset($columns['view_end_min']) ? (int) $columns['view_end_min'] : $defaults['view_end_min'];

        return $this->normalizePair($start, $end);
    }

    /**
     * @return array{view_start_min: int, view_end_min: int}
     */
    public function normalizePair(int $startMin, int $endMin): array
    {
        $defaults = $this->defaults();
        if ($startMin % 30 !== 0 || $startMin < 0 || $startMin > 1380) {
            return $defaults;
        }
        if ($endMin % 30 !== 0 || $endMin < 60 || $endMin > 1440) {
            return $defaults;
        }
        if ($endMin < $startMin + 60) {
            return $defaults;
        }

        return [
            'view_start_min' => $startMin,
            'view_end_min' => $endMin,
        ];
    }

    public function saveForUserId(int $userId, int $startMin, int $endMin): void
    {
        $pair = $this->normalizePair($startMin, $endMin);

        UserTableSetting::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => self::TABLE_KEY,
            ],
            [
                'columns' => [
                    'view_start_min' => $pair['view_start_min'],
                    'view_end_min' => $pair['view_end_min'],
                ],
            ]
        );
    }
}
