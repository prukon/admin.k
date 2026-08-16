<?php

declare(strict_types=1);

namespace App\Services\InAppNotifications;

final class InAppNotificationActionUrl
{
    public static function sanitize(?string $raw): ?string
    {
        $url = trim((string) $raw);
        if ($url === '') {
            return null;
        }

        if (mb_strlen($url) > 2048) {
            return null;
        }

        if (! self::isSafe($url)) {
            return null;
        }

        return $url;
    }

    public static function isSafe(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $lower = mb_strtolower($url);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return ! str_contains($url, "\n") && ! str_contains($url, "\r");
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);

        return in_array($scheme, ['http', 'https'], true);
    }
}
