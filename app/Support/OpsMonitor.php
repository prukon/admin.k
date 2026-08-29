<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\FiscalReceipt;
use App\Models\OutgoingEmailLog;
use App\Models\PaymentIntent;
use App\Models\SchoolLead;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Операционный снимок «Пульт»: счётчики за 24 часа и last-ok/last-fail шлюзов.
 * Персональные данные в JSON не кладём (email / phone / password / PAN).
 */
final class OpsMonitor
{
    public const WINDOW_HOURS = 24;

    public const MESSAGE_LIMIT = 80;

    public const GATEWAY_TINKOFF = 'tinkoff';

    public const GATEWAY_SMSRU = 'smsru';

    public const GATEWAY_CLOUDKASSIR = 'cloudkassir';

    private const CACHE_TTL_HOURS = 26;

    private const GATEWAY_TTL_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $queue = QueueStatusSnapshot::compact();
        $since = now()->subHours(self::WINDOW_HOURS);
        $errors = self::errorSnapshot();

        return [
            'ok' => true,
            'window_hours' => self::WINDOW_HOURS,
            'queue' => [
                'worker' => $queue['worker_status'],
                'scheduler' => $queue['scheduler_status'],
                'jobs' => $queue['jobs_count'],
                'failed_jobs' => $queue['failed_jobs_count'],
                'overdue_payouts' => $queue['overdue_scheduled_payouts_count'],
            ],
            'till' => [
                'overdue_payouts' => $queue['overdue_scheduled_payouts_count'],
                'failed_intents' => (int) PaymentIntent::query()
                    ->where('status', 'failed')
                    ->where('updated_at', '>=', $since)
                    ->count(),
                'fiscal_errors' => (int) FiscalReceipt::query()
                    ->where('status', FiscalReceipt::STATUS_ERROR)
                    ->where(function ($query) use ($since): void {
                        $query->where('failed_at', '>=', $since)
                            ->orWhere(function ($inner) use ($since): void {
                                $inner->whereNull('failed_at')->where('updated_at', '>=', $since);
                            });
                    })
                    ->count(),
            ],
            'errors' => $errors,
            'gateways' => [
                self::GATEWAY_TINKOFF => self::gatewaySnapshot(self::GATEWAY_TINKOFF),
                self::GATEWAY_SMSRU => self::gatewaySnapshot(self::GATEWAY_SMSRU),
                self::GATEWAY_CLOUDKASSIR => self::gatewaySnapshot(self::GATEWAY_CLOUDKASSIR),
            ],
            'auth' => [
                'failed_logins' => self::sumHourlyInt('auth:login'),
                'failed_2fa' => self::sumHourlyInt('auth:2fa'),
            ],
            'welcome' => self::welcomeSnapshot($since),
        ];
    }

    public static function recordException(Throwable $e): void
    {
        try {
            $class = $e::class;
            $short = class_basename($class);
            self::bumpHourly('errors', $short);
            Cache::put('ops:errors:last', [
                'class' => $short,
                'message' => self::sanitizeMessage($e->getMessage()),
                'at' => now()->timestamp,
            ], now()->addHours(self::CACHE_TTL_HOURS));
        } catch (Throwable) {
            // пульт не должен ломать report()
        }
    }

    public static function recordGatewayOk(string $gateway): void
    {
        try {
            Cache::put(
                'ops:gateway:'.$gateway.':last_ok',
                now()->timestamp,
                now()->addDays(self::GATEWAY_TTL_DAYS)
            );
        } catch (Throwable) {
        }
    }

    public static function recordGatewayFail(string $gateway, string $message): void
    {
        try {
            Cache::put(
                'ops:gateway:'.$gateway.':last_fail',
                [
                    'at' => now()->timestamp,
                    'message' => self::sanitizeMessage($message),
                ],
                now()->addDays(self::GATEWAY_TTL_DAYS)
            );
        } catch (Throwable) {
        }
    }

    public static function recordFailedLogin(): void
    {
        self::incrementHourlyInt('auth:login');
    }

    public static function recordFailedTwoFactor(): void
    {
        self::incrementHourlyInt('auth:2fa');
    }

    /**
     * @return array{count: int, last_class: string|null, last_message: string|null, top_class: string|null}
     */
    private static function errorSnapshot(): array
    {
        $total = 0;
        $byClass = [];
        $now = now();
        for ($i = 0; $i < self::WINDOW_HOURS; $i++) {
            $data = Cache::get('ops:errors:hour:'.$now->copy()->subHours($i)->format('YmdH'));
            if (! is_array($data)) {
                continue;
            }
            $total += (int) ($data['total'] ?? 0);
            $classes = is_array($data['by_class'] ?? null) ? $data['by_class'] : [];
            foreach ($classes as $class => $count) {
                $name = (string) $class;
                if ($name === '') {
                    continue;
                }
                $byClass[$name] = ($byClass[$name] ?? 0) + (int) $count;
            }
        }

        $topClass = null;
        if ($byClass !== []) {
            arsort($byClass);
            $topClass = (string) array_key_first($byClass);
        }

        $last = Cache::get('ops:errors:last');
        $lastClass = is_array($last) ? self::nullableString($last['class'] ?? null) : null;
        $lastMessage = is_array($last) ? self::nullableString($last['message'] ?? null) : null;

        return [
            'count' => $total,
            'last_class' => $lastClass,
            'last_message' => $lastMessage,
            'top_class' => $topClass,
        ];
    }

    /**
     * @return array{last_ok_at: string|null, last_fail_at: string|null, last_fail_message: string|null, last_ok_age_seconds: int|null, last_fail_age_seconds: int|null}
     */
    private static function gatewaySnapshot(string $gateway): array
    {
        $okTs = (int) (Cache::get('ops:gateway:'.$gateway.':last_ok', 0) ?: 0);
        $fail = Cache::get('ops:gateway:'.$gateway.':last_fail');
        $failTs = is_array($fail) ? (int) ($fail['at'] ?? 0) : 0;
        $failMessage = is_array($fail) ? self::nullableString($fail['message'] ?? null) : null;
        $nowTs = now()->timestamp;

        return [
            'last_ok_at' => $okTs > 0 ? now()->setTimestamp($okTs)->toDateTimeString() : null,
            'last_fail_at' => $failTs > 0 ? now()->setTimestamp($failTs)->toDateTimeString() : null,
            'last_fail_message' => $failMessage,
            'last_ok_age_seconds' => $okTs > 0 ? max(0, $nowTs - $okTs) : null,
            'last_fail_age_seconds' => $failTs > 0 ? max(0, $nowTs - $failTs) : null,
        ];
    }

    /**
     * @return array{missing_count: int, last_user_id: int|null}
     */
    private static function welcomeSnapshot(\DateTimeInterface $since): array
    {
        $leads = SchoolLead::query()
            ->whereNotNull('user_id')
            ->whereHas('user', static function ($query) use ($since): void {
                $query->where('created_at', '>=', $since);
            })
            ->with(['user:id,email,created_at'])
            ->orderByDesc('id')
            ->get(['id', 'user_id']);

        if ($leads->isEmpty()) {
            return [
                'missing_count' => 0,
                'last_user_id' => null,
            ];
        }

        $userIds = $leads->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
        $emails = $leads->map(fn (SchoolLead $lead) => trim((string) ($lead->user?->email ?? '')))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values();

        $sentLogs = OutgoingEmailLog::query()
            ->where('mailable_class', ClientWelcomeCredentialsMail::class)
            ->where('status', OutgoingEmailLog::STATUS_SENT)
            ->where(function ($query) use ($userIds, $emails): void {
                $query->where(function ($inner) use ($userIds): void {
                    $inner->where('notifiable_type', User::class)
                        ->whereIn('notifiable_id', $userIds->all());
                });
                foreach ($emails as $email) {
                    $query->orWhere('to_summary', 'like', '%'.addcslashes($email, '%_\\').'%');
                }
            })
            ->get(['notifiable_id', 'to_summary']);

        $sentUserIds = [];
        foreach ($sentLogs as $log) {
            if ($log->notifiable_id) {
                $sentUserIds[(int) $log->notifiable_id] = true;
            }
        }

        $missing = 0;
        $lastUserId = null;
        foreach ($leads as $lead) {
            $userId = (int) $lead->user_id;
            $email = trim((string) ($lead->user?->email ?? ''));
            $sent = isset($sentUserIds[$userId]);
            if (! $sent && $email !== '') {
                foreach ($sentLogs as $log) {
                    $summary = (string) ($log->to_summary ?? '');
                    if ($summary !== '' && str_contains($summary, $email)) {
                        $sent = true;
                        break;
                    }
                }
            }
            if ($sent) {
                continue;
            }
            $missing++;
            if ($lastUserId === null) {
                $lastUserId = $userId;
            }
        }

        return [
            'missing_count' => $missing,
            'last_user_id' => $lastUserId,
        ];
    }

    private static function bumpHourly(string $bucket, string $class): void
    {
        $key = 'ops:'.$bucket.':hour:'.now()->format('YmdH');
        $data = Cache::get($key, ['total' => 0, 'by_class' => []]);
        if (! is_array($data)) {
            $data = ['total' => 0, 'by_class' => []];
        }
        $data['total'] = (int) ($data['total'] ?? 0) + 1;
        if ($class !== '') {
            $byClass = is_array($data['by_class'] ?? null) ? $data['by_class'] : [];
            $byClass[$class] = (int) ($byClass[$class] ?? 0) + 1;
            $data['by_class'] = $byClass;
        }
        Cache::put($key, $data, now()->addHours(self::CACHE_TTL_HOURS));
    }

    private static function incrementHourlyInt(string $bucket): void
    {
        try {
            $key = 'ops:'.$bucket.':hour:'.now()->format('YmdH');
            Cache::add($key, 0, now()->addHours(self::CACHE_TTL_HOURS));
            Cache::increment($key);
        } catch (Throwable) {
        }
    }

    private static function sumHourlyInt(string $bucket): int
    {
        $total = 0;
        $now = now();
        for ($i = 0; $i < self::WINDOW_HOURS; $i++) {
            $total += (int) (Cache::get('ops:'.$bucket.':hour:'.$now->copy()->subHours($i)->format('YmdH'), 0) ?: 0);
        }

        return $total;
    }

    private static function sanitizeMessage(string $message): string
    {
        $clean = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $message) ?? $message;
        $clean = preg_replace('/\b\d{13,19}\b/', '[card]', $clean) ?? $clean;
        $clean = preg_replace('/\+?\d[\d\s\-()]{9,}\d/', '[phone]', $clean) ?? $clean;
        $clean = preg_replace('/(?i)\b(password|passwd|secret)\s*[=:]\s*\S+/', '[secret]', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
        if (mb_strlen($clean) > self::MESSAGE_LIMIT) {
            $clean = mb_substr($clean, 0, self::MESSAGE_LIMIT - 1).'…';
        }

        return $clean;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
