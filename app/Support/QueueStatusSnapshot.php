<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\Models\TinkoffPayout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Снимок очередей для Настройки → Очереди и компактной строки пульта.
 * Вкладка очередей для не-superadmin режет просроченные выплаты текущим партнёром;
 * пульт всегда считает все школы.
 */
final class QueueStatusSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function full(?int $partnerId): array
    {
        $jobsCount = (int) DB::table('jobs')->count();
        $failedJobsCount = (int) DB::table('failed_jobs')->count();

        $oldestJobCreatedAtRaw = DB::table('jobs')->min('created_at');
        $oldestJobCreatedAt = $oldestJobCreatedAtRaw ? Carbon::parse($oldestJobCreatedAtRaw) : null;
        $oldestJobAgeSeconds = $oldestJobCreatedAt ? now()->diffInSeconds($oldestJobCreatedAt) : null;

        $lastSuccessTs = Setting::getInt('queue_monitor_last_success_at', 0, null);
        $lastFailedTs = Setting::getInt('queue_monitor_last_failed_at', 0, null);
        $lastHeartbeatTs = (int) (Cache::get('queue:monitor:last_heartbeat_at', 0) ?: 0);

        $lastSuccessAt = $lastSuccessTs > 0 ? now()->setTimestamp($lastSuccessTs)->toDateTimeString() : null;
        $lastFailedAt = $lastFailedTs > 0 ? now()->setTimestamp($lastFailedTs)->toDateTimeString() : null;
        $lastHeartbeatAt = $lastHeartbeatTs > 0 ? now()->setTimestamp($lastHeartbeatTs)->toDateTimeString() : null;

        $queuesPending = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['queue' => (string) $row->queue, 'pending' => (int) $row->total])
            ->values()
            ->all();

        $queuesFailed = DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['queue' => (string) ($row->queue ?: 'default'), 'failed' => (int) $row->total])
            ->values()
            ->all();

        $jobGroups = [
            'refunds' => [
                'label' => 'Возвраты',
                'patterns' => ['TinkoffProcessRefundJob', 'RobokassaProcessRefundJob'],
            ],
            'receipts' => [
                'label' => 'Онлайн-чеки CloudKassir',
                'patterns' => ['SendCloudKassirReceiptJob'],
            ],
            'blog_ai' => [
                'label' => 'AI-статьи блога',
                'patterns' => ['RunBlogAiGenerationJob', 'RunBlogAiGeneratedImageJob', 'RunBlogAiImageRegenerationJob'],
            ],
            'blog_vk' => [
                'label' => 'Публикация блога в VK',
                'patterns' => ['PublishBlogPostToVkJob'],
            ],
            'payouts' => [
                'label' => 'Выплаты T-Bank',
                'patterns' => ['TinkoffRunScheduledPayoutsJob', 'TinkoffPollPayoutStatesJob'],
            ],
            'in_app_notifications' => [
                'label' => 'Уведомления CRM',
                'patterns' => ['FanOutInAppNotificationJob'],
            ],
            'smoke' => [
                'label' => 'Тех. проверка очереди',
                'patterns' => ['QueueSmokeTestJob'],
            ],
        ];

        $groupStats = [];
        foreach ($jobGroups as $key => $group) {
            $groupStats[] = [
                'key' => $key,
                'label' => $group['label'],
                'pending' => self::countByPayloadPatterns('jobs', $group['patterns']),
                'failed' => self::countByPayloadPatterns('failed_jobs', $group['patterns']),
            ];
        }

        $schedulerTickTs = (int) (Cache::get(SchedulerHeartbeat::CACHE_KEY, 0) ?: 0);
        $schedulerLastTickAt = $schedulerTickTs > 0
            ? now()->setTimestamp($schedulerTickTs)->toDateTimeString()
            : null;

        $overdueBase = TinkoffPayout::query()->overdueScheduled();
        if ($partnerId !== null) {
            $overdueBase->where('partner_id', $partnerId);
        }
        $overdueScheduledPayoutsCount = (int) (clone $overdueBase)->count();
        $overdueScheduledPayoutsSample = (clone $overdueBase)
            ->with(['partner:id,title'])
            ->orderBy('when_to_run')
            ->limit(15)
            ->get()
            ->map(fn (TinkoffPayout $payout) => [
                'id' => $payout->id,
                'partner_id' => $payout->partner_id,
                'partner_title' => $payout->partner?->title,
                'when_to_run' => $payout->when_to_run?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return [
            'jobs_count' => $jobsCount,
            'failed_jobs_count' => $failedJobsCount,
            'oldest_job_age_seconds' => $oldestJobAgeSeconds,
            'oldest_job_created_at' => $oldestJobCreatedAt?->toDateTimeString(),
            'last_success_at' => $lastSuccessAt,
            'last_failed_at' => $lastFailedAt,
            'last_heartbeat_at' => $lastHeartbeatAt,
            'worker_status' => self::resolveWorkerStatus($lastHeartbeatTs),
            'scheduler_last_tick_at' => $schedulerLastTickAt,
            'scheduler_status' => self::resolveSchedulerStatus($schedulerTickTs),
            'overdue_scheduled_payouts_count' => $overdueScheduledPayoutsCount,
            'overdue_scheduled_payouts_sample' => $overdueScheduledPayoutsSample,
            'queues_pending' => $queuesPending,
            'queues_failed' => $queuesFailed,
            'job_groups' => $groupStats,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Компактный снимок для пульта: без LIKE по payload и без выборки выплат.
     *
     * @return array{
     *     jobs_count: int,
     *     failed_jobs_count: int,
     *     overdue_scheduled_payouts_count: int,
     *     worker_status: array{code: string, title: string, seconds_since_heartbeat: int|null},
     *     scheduler_status: array{code: string, title: string, seconds_since_tick: int|null}
     * }
     */
    public static function compact(): array
    {
        $lastHeartbeatTs = (int) (Cache::get('queue:monitor:last_heartbeat_at', 0) ?: 0);
        $schedulerTickTs = (int) (Cache::get(SchedulerHeartbeat::CACHE_KEY, 0) ?: 0);

        return [
            'jobs_count' => (int) DB::table('jobs')->count(),
            'failed_jobs_count' => (int) DB::table('failed_jobs')->count(),
            'overdue_scheduled_payouts_count' => (int) TinkoffPayout::query()->overdueScheduled()->count(),
            'worker_status' => self::resolveWorkerStatus($lastHeartbeatTs),
            'scheduler_status' => self::resolveSchedulerStatus($schedulerTickTs),
        ];
    }

    /**
     * @return array{code: string, title: string, seconds_since_tick: int|null}
     */
    public static function resolveSchedulerStatus(int $lastTickTs): array
    {
        if ($lastTickTs <= 0) {
            return [
                'code' => 'no_data',
                'title' => 'Планировщик: нет данных (проверьте cron: * * * * * php artisan schedule:run)',
                'seconds_since_tick' => null,
            ];
        }

        $seconds = now()->timestamp - $lastTickTs;

        if ($seconds <= 120) {
            return [
                'code' => 'alive',
                'title' => 'Планировщик: работает',
                'seconds_since_tick' => $seconds,
            ];
        }

        if ($seconds <= 600) {
            return [
                'code' => 'stale',
                'title' => 'Планировщик: давно не было тика',
                'seconds_since_tick' => $seconds,
            ];
        }

        return [
            'code' => 'dead',
            'title' => 'Планировщик: вероятно не запускается',
            'seconds_since_tick' => $seconds,
        ];
    }

    /**
     * @return array{code: string, title: string, seconds_since_heartbeat: int|null}
     */
    public static function resolveWorkerStatus(int $lastHeartbeatTs): array
    {
        if ($lastHeartbeatTs <= 0) {
            return [
                'code' => 'no_data',
                'title' => 'Нет данных',
                'seconds_since_heartbeat' => null,
            ];
        }

        $seconds = now()->timestamp - $lastHeartbeatTs;

        if ($seconds <= 60) {
            return [
                'code' => 'alive',
                'title' => 'Жив',
                'seconds_since_heartbeat' => $seconds,
            ];
        }

        if ($seconds <= 300) {
            return [
                'code' => 'stale',
                'title' => 'Нет данных давно',
                'seconds_since_heartbeat' => $seconds,
            ];
        }

        return [
            'code' => 'dead',
            'title' => 'Вероятно умер',
            'seconds_since_heartbeat' => $seconds,
        ];
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function countByPayloadPatterns(string $table, array $patterns): int
    {
        return (int) DB::table($table)
            ->where(function ($query) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $query->orWhere('payload', 'like', '%'.$pattern.'%');
                }
            })
            ->count();
    }
}
