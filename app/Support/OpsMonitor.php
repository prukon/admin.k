<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\FiscalReceipt;
use App\Models\OutgoingEmailLog;
use App\Models\PaymentIntent;
use App\Models\SchoolLead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\ViewException;
use Throwable;

/**
 * Операционный снимок «Пульт»: счётчики за 24 часа и last-ok/last-fail шлюзов.
 * Исключения / шлюзы: email / phone / password / PAN в JSON не кладём.
 * Строка «Вход»: за 72 часа в JSON есть введённые email/пароль/код 2FA и IP (только cache, не my_logs).
 */
final class OpsMonitor
{
    public const WINDOW_HOURS = 24;

    public const AUTH_WINDOW_HOURS = 72;

    public const MESSAGE_LIMIT = 80;

    public const RECENT_LIMIT = 20;

    public const AUTH_RECENT_LIMIT = 40;

    public const AUTH_SECRET_LIMIT = 80;

    public const GATEWAY_TINKOFF = 'tinkoff';

    public const GATEWAY_SMSRU = 'smsru';

    public const GATEWAY_CLOUDKASSIR = 'cloudkassir';

    private const CACHE_TTL_HOURS = 26;

    private const AUTH_CACHE_TTL_HOURS = 74;

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
                'window_hours' => self::AUTH_WINDOW_HOURS,
                'failed_logins' => self::sumHourlyInt('auth:login', self::AUTH_WINDOW_HOURS),
                'failed_2fa' => self::sumHourlyInt('auth:2fa', self::AUTH_WINDOW_HOURS),
                'recent_logins' => self::authRecentSnapshot('login'),
                'recent_2fa' => self::authRecentSnapshot('2fa'),
            ],
            'welcome' => self::welcomeSnapshot($since),
        ];
    }

    public static function recordException(Throwable $e): void
    {
        try {
            $payload = self::resolveExceptionPayload($e);
            $short = $payload['class'];
            $message = self::sanitizeMessage($payload['message']);
            self::bumpHourly('errors', $short);
            Cache::put('ops:errors:last', [
                'class' => $short,
                'message' => $message,
                'at' => now()->timestamp,
            ], now()->addHours(self::CACHE_TTL_HOURS));
            self::pushRecent($short, $message);
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

    /**
     * @param array{email?: string|null, password?: string|null, ip?: string|null, user_found?: bool}|null $attempt
     */
    public static function recordFailedLogin(?array $attempt = null): void
    {
        self::incrementHourlyInt('auth:login', self::AUTH_CACHE_TTL_HOURS);
        if ($attempt === null) {
            return;
        }

        self::pushAuthRecent('login', [
            'email' => self::clipPlain((string) ($attempt['email'] ?? ''), 191),
            'password' => self::clipPlain((string) ($attempt['password'] ?? ''), self::AUTH_SECRET_LIMIT),
            'ip' => self::clipPlain((string) ($attempt['ip'] ?? ''), 45),
            'user_found' => (bool) ($attempt['user_found'] ?? false),
            'at' => now()->timestamp,
        ]);
    }

    /**
     * @param array{email?: string|null, code?: string|null, ip?: string|null}|null $attempt
     */
    public static function recordFailedTwoFactor(?array $attempt = null): void
    {
        self::incrementHourlyInt('auth:2fa', self::AUTH_CACHE_TTL_HOURS);
        if ($attempt === null) {
            return;
        }

        self::pushAuthRecent('2fa', [
            'email' => self::clipPlain((string) ($attempt['email'] ?? ''), 191),
            'code' => self::clipPlain((string) ($attempt['code'] ?? ''), 16),
            'ip' => self::clipPlain((string) ($attempt['ip'] ?? ''), 45),
            'at' => now()->timestamp,
        ]);
    }

    /**
     * @return array{count: int, last_class: string|null, last_message: string|null, top_class: string|null, recent: list<array{class: string|null, message: string|null, route: string|null, path: string|null, at: int}>}
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
            'recent' => self::recentSnapshot(),
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
     * Лид → клиент за 24 ч без успешного welcome.
     * Успех: STATUS_SENT и (mailable_class = ClientWelcomeCredentialsMail
     * или пустой класс + тема «Доступ в личный кабинет…» — старые логи без типа).
     *
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
            ->where('status', OutgoingEmailLog::STATUS_SENT)
            ->where(function ($query): void {
                $query->where('mailable_class', ClientWelcomeCredentialsMail::class)
                    ->orWhere(function ($legacy): void {
                        $legacy->whereNull('mailable_class')
                            ->where('subject', 'like', ClientWelcomeCredentialsMail::SUBJECT_PREFIX.'%');
                    });
            })
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

    /**
     * ViewException / Ignition-обёртка → класс причины. Сообщение оставляем
     * с путём blade, абсолютный base_path режем.
     *
     * @return array{class: string, message: string}
     */
    private static function resolveExceptionPayload(Throwable $e): array
    {
        $message = $e->getMessage();
        $target = $e;
        while (self::isViewExceptionWrapper($target) && $target->getPrevious() instanceof Throwable) {
            $target = $target->getPrevious();
        }

        if (self::isViewExceptionWrapper($e) && str_contains($e->getMessage(), '(View:')) {
            $message = $e->getMessage();
        }

        return [
            'class' => class_basename($target::class),
            'message' => self::relativizeViewPaths($message),
        ];
    }

    private static function isViewExceptionWrapper(Throwable $e): bool
    {
        if ($e instanceof ViewException) {
            return true;
        }

        $short = class_basename($e::class);

        return $short === 'ViewException' || $short === 'ViewExceptionWithSolution';
    }

    private static function relativizeViewPaths(string $message): string
    {
        $bases = [];
        foreach ([base_path(), realpath(base_path())] as $base) {
            if (! is_string($base) || $base === '' || $base === '/') {
                continue;
            }
            $bases[] = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $bases[] = rtrim(str_replace('\\', '/', $base), '/').'/';
        }

        return str_replace(array_values(array_unique($bases)), '', $message);
    }

    /**
     * @return list<array{class: string|null, message: string|null, route: string|null, path: string|null, at: int}>
     */
    private static function recentSnapshot(): array
    {
        $raw = Cache::get('ops:errors:recent');
        if (! is_array($raw)) {
            return [];
        }

        $since = now()->subHours(self::WINDOW_HOURS)->timestamp;
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $at = (int) ($row['at'] ?? 0);
            if ($at < $since) {
                continue;
            }
            $out[] = [
                'class' => self::nullableString($row['class'] ?? null),
                'message' => self::nullableString($row['message'] ?? null),
                'route' => self::nullableString($row['route'] ?? null),
                'path' => self::nullableString($row['path'] ?? null),
                'at' => $at,
            ];
            if (count($out) >= self::RECENT_LIMIT) {
                break;
            }
        }

        return $out;
    }

    private static function pushRecent(string $class, string $message): void
    {
        $ctx = self::currentHttpContext();
        $item = [
            'class' => $class !== '' ? $class : null,
            'message' => $message !== '' ? $message : null,
            'route' => $ctx['route'],
            'path' => $ctx['path'],
            'at' => now()->timestamp,
        ];
        $list = Cache::get('ops:errors:recent', []);
        if (! is_array($list)) {
            $list = [];
        }
        array_unshift($list, $item);
        $list = array_slice(array_values($list), 0, self::RECENT_LIMIT);
        Cache::put('ops:errors:recent', $list, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * @return array{route: string|null, path: string|null}
     */
    private static function currentHttpContext(): array
    {
        try {
            $request = request();
            if (! $request instanceof Request) {
                return ['route' => null, 'path' => null];
            }

            $route = self::nullableString($request->route()?->getName());
            if ($route !== null) {
                $route = self::sanitizeMessage($route);
            }

            $path = $request->getPathInfo();
            if (! is_string($path) || trim($path) === '') {
                return ['route' => $route, 'path' => null];
            }

            $cleanPath = self::sanitizeMessage($path);

            return [
                'route' => $route,
                'path' => $cleanPath !== '' ? $cleanPath : null,
            ];
        } catch (Throwable) {
            return ['route' => null, 'path' => null];
        }
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

    /**
     * @param array<string, mixed> $item
     */
    private static function pushAuthRecent(string $kind, array $item): void
    {
        try {
            $key = 'ops:auth:'.$kind.':recent';
            $list = Cache::get($key, []);
            if (! is_array($list)) {
                $list = [];
            }
            array_unshift($list, $item);
            $list = array_slice(array_values($list), 0, self::AUTH_RECENT_LIMIT);
            Cache::put($key, $list, now()->addHours(self::AUTH_CACHE_TTL_HOURS));
        } catch (Throwable) {
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function authRecentSnapshot(string $kind): array
    {
        $raw = Cache::get('ops:auth:'.$kind.':recent');
        if (! is_array($raw)) {
            return [];
        }

        $since = now()->subHours(self::AUTH_WINDOW_HOURS)->timestamp;
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $at = (int) ($row['at'] ?? 0);
            if ($at < $since) {
                continue;
            }
            if ($kind === 'login') {
                $password = $row['password'] ?? '';
                $out[] = [
                    'email' => self::nullableString($row['email'] ?? null),
                    'password' => is_string($password) ? $password : '',
                    'ip' => self::nullableString($row['ip'] ?? null),
                    'user_found' => (bool) ($row['user_found'] ?? false),
                    'at' => $at,
                ];
            } else {
                $code = $row['code'] ?? '';
                $out[] = [
                    'email' => self::nullableString($row['email'] ?? null),
                    'code' => is_string($code) ? $code : '',
                    'ip' => self::nullableString($row['ip'] ?? null),
                    'at' => $at,
                ];
            }
            if (count($out) >= self::AUTH_RECENT_LIMIT) {
                break;
            }
        }

        return $out;
    }

    private static function clipPlain(string $value, int $limit): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        $clean = str_replace(["\r", "\n", "\t"], ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
        if ($limit > 0 && mb_strlen($clean) > $limit) {
            $clean = mb_substr($clean, 0, $limit);
        }

        return $clean;
    }

    private static function incrementHourlyInt(string $bucket, int $ttlHours = self::CACHE_TTL_HOURS): void
    {
        try {
            $key = 'ops:'.$bucket.':hour:'.now()->format('YmdH');
            Cache::add($key, 0, now()->addHours($ttlHours));
            Cache::increment($key);
        } catch (Throwable) {
        }
    }

    private static function sumHourlyInt(string $bucket, int $hours = self::WINDOW_HOURS): int
    {
        $total = 0;
        $now = now();
        for ($i = 0; $i < $hours; $i++) {
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
