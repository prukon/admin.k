<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\User;
use App\Models\UserTableSetting;

/**
 * «Показывать по» журнала /schedule: одна величина на пользователя и на все группы страницы.
 * Та же строка user_table_settings, что «Показать N» в отчётах (колонка page_length).
 */
final class ScheduleJournalPageLength
{
    public const TABLE_KEY = 'schedule_journal';

    /** @var list<int> */
    public const LENGTHS = [20, 50, 100];

    public const DEFAULT = 50;

    public static function forUser(?User $user): int
    {
        if ($user === null || (int) $user->id < 1) {
            return self::DEFAULT;
        }

        $raw = UserTableSetting::query()
            ->where('user_id', (int) $user->id)
            ->where('table_key', self::TABLE_KEY)
            ->value('page_length');

        return self::normalize($raw);
    }

    public static function normalize(mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || ! in_array($int, self::LENGTHS, true)) {
            return self::DEFAULT;
        }

        return $int;
    }

    public static function save(int $userId, int $length): void
    {
        UserTableSetting::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => self::TABLE_KEY,
            ],
            [
                'page_length' => $length,
            ]
        );
    }
}
