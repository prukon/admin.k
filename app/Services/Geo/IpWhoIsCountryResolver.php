<?php

declare(strict_types=1);

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Страна по публичному IP. Ответ кэшируется, чтобы карточка ученика не ходила в сеть на каждый IP повторно.
 */
final class IpWhoIsCountryResolver implements IpCountryResolver
{
    public function resolve(?string $ip): ?IpCountry
    {
        $ip = trim((string) $ip);
        if (! $this->isPublicIp($ip)) {
            return null;
        }

        $cacheKey = 'ip-country:'.$ip;
        $cached = Cache::get($cacheKey);
        if ($cached instanceof IpCountry) {
            return $cached;
        }
        if ($cached === false) {
            return null;
        }

        $country = $this->lookup($ip);
        if ($country === null) {
            Cache::put($cacheKey, false, now()->addMinutes(10));

            return null;
        }

        Cache::put($cacheKey, $country, now()->addDays(30));

        return $country;
    }

    private function lookup(string $ip): ?IpCountry
    {
        try {
            $response = Http::timeout(2)
                ->acceptJson()
                ->get('https://ipwho.is/'.$ip);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            return null;
        }

        $code = strtoupper(trim((string) ($payload['country_code'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return null;
        }

        return new IpCountry($code, $this->countryName($code, (string) ($payload['country'] ?? '')));
    }

    private function countryName(string $code, string $fallback): string
    {
        if (extension_loaded('intl') && class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$code, 'ru');
            if (is_string($name) && $name !== '' && $name !== $code) {
                return $name;
            }
        }

        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : $code;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
