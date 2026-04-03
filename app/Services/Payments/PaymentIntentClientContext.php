<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

/**
 * Снимок клиентского контекста при создании PaymentIntent (UA, тип устройства, IP, referrer).
 */
final class PaymentIntentClientContext
{
    private const UA_MAX_LEN = 65535;

    private const REFERRER_MAX_LEN = 2048;

    /**
     * @return array<string, string|null>
     */
    public static function fromRequest(?Request $request): array
    {
        if ($request === null) {
            return self::emptyPayload();
        }

        $uaRaw = $request->userAgent();
        $ua = is_string($uaRaw) ? trim($uaRaw) : '';
        if (mb_strlen($ua) > self::UA_MAX_LEN) {
            $ua = mb_substr($ua, 0, self::UA_MAX_LEN);
        }

        $referrerRaw = $request->headers->get('Referer');
        $referrer = is_string($referrerRaw) ? trim($referrerRaw) : '';
        if ($referrer === '') {
            $referrer = null;
        } elseif (mb_strlen($referrer) > self::REFERRER_MAX_LEN) {
            $referrer = mb_substr($referrer, 0, self::REFERRER_MAX_LEN);
        }

        $ipRaw = $request->ip();
        $ip = is_string($ipRaw) ? trim($ipRaw) : '';
        if ($ip === '') {
            $ip = null;
        } else {
            $ip = self::truncate($ip, 45);
        }

        if ($ua === '') {
            return [
                'client_user_agent' => null,
                'client_device_type' => null,
                'client_os_family' => null,
                'client_os_version' => null,
                'client_browser_family' => null,
                'client_browser_version' => null,
                'client_ip' => $ip,
                'client_referrer' => $referrer,
            ];
        }

        $agent = new Agent;
        $agent->setUserAgent($ua);

        $platformRaw = $agent->platform();
        $platform = is_string($platformRaw) && $platformRaw !== '' ? $platformRaw : null;

        $browserRaw = $agent->browser();
        $browser = is_string($browserRaw) && $browserRaw !== '' ? $browserRaw : null;

        $osVersion = null;
        if ($platform !== null) {
            $v = $agent->version($platform);
            $osVersion = is_string($v) && $v !== '' ? $v : null;
        }

        $browserVersion = null;
        if ($browser !== null) {
            $v = $agent->version($browser);
            $browserVersion = is_string($v) && $v !== '' ? $v : null;
        }

        return [
            'client_user_agent' => $ua,
            'client_device_type' => self::resolveDeviceType($agent),
            'client_os_family' => self::truncate($platform, 64),
            'client_os_version' => self::truncate($osVersion, 32),
            'client_browser_family' => self::truncate($browser, 64),
            'client_browser_version' => self::truncate($browserVersion, 32),
            'client_ip' => $ip,
            'client_referrer' => $referrer,
        ];
    }

    /**
     * @return array<string, null>
     */
    private static function emptyPayload(): array
    {
        return [
            'client_user_agent' => null,
            'client_device_type' => null,
            'client_os_family' => null,
            'client_os_version' => null,
            'client_browser_family' => null,
            'client_browser_version' => null,
            'client_ip' => null,
            'client_referrer' => null,
        ];
    }

    private static function resolveDeviceType(Agent $agent): string
    {
        if ($agent->isRobot()) {
            return 'bot';
        }
        if ($agent->isTablet()) {
            return 'tablet';
        }
        if ($agent->isMobile()) {
            return 'mobile';
        }
        if ($agent->isDesktop()) {
            return 'desktop';
        }

        return 'unknown';
    }

    private static function truncate(?string $value, int $maxBytes): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) <= $maxBytes) {
            return $value;
        }

        return mb_substr($value, 0, $maxBytes);
    }
}
