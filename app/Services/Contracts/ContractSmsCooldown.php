<?php

namespace App\Services\Contracts;

use Illuminate\Support\Facades\Cache;

class ContractSmsCooldown
{
    public const SECONDS = 30;

    public static function cacheKey(int $contractId): string
    {
        return 'contract_sms_cooldown:' . $contractId;
    }

    /**
     * @return array{allowed: bool, remaining?: int}
     */
    public static function tryAcquire(int $contractId): array
    {
        $key = self::cacheKey($contractId);
        $expiresAt = time() + self::SECONDS;

        if (Cache::add($key, $expiresAt, self::SECONDS)) {
            return ['allowed' => true];
        }

        $existing = Cache::get($key);
        if ($existing === null) {
            if (Cache::add($key, $expiresAt, self::SECONDS)) {
                return ['allowed' => true];
            }

            $existing = Cache::get($key);
        }

        return [
            'allowed'   => false,
            'remaining' => max(1, (int) $existing - time()),
        ];
    }

    public static function release(int $contractId): void
    {
        Cache::forget(self::cacheKey($contractId));
    }

    /**
     * @return array{success: false, message: string, code: string, cooldown_sec: int}
     */
    public static function blockedResponse(int $remaining): array
    {
        return [
            'success'      => false,
            'message'      => 'Повторная отправка SMS возможна через ' . $remaining . ' сек.',
            'code'         => 'sms_cooldown',
            'cooldown_sec' => $remaining,
        ];
    }
}
