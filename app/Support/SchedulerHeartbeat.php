<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Обновляется из Laravel Scheduler (cron → schedule:run), чтобы в админке было видно,
 * что планировщик реально отрабатывает (отдельно от queue worker).
 */
final class SchedulerHeartbeat
{
    public const CACHE_KEY = 'scheduler:last_tick_at';

    public static function touch(): void
    {
        Cache::put(self::CACHE_KEY, now()->timestamp, now()->addHours(72));
    }
}
