<?php

declare(strict_types=1);

namespace App\Support;

final class PartnerLandingSlug
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 40;

    /** Маршрут Laravel: сегмент 3–40 символов, латиница, цифры, дефис. */
    public const ROUTE_PATTERN = '[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])?';

    /** @var list<string> */
    public const RESERVED = [
        'admin',
        'api',
        'blog',
        'contact',
        'doc',
        'docs',
        'lead',
        'login',
        'logout',
        'partner',
        'policy',
        'public-offerta',
        'register',
        'sitemap',
        'two-factor',
        'webhooks',
        'widget',
        '_debug',
    ];

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return $value === '' ? null : $value;
    }

    public static function validationRegex(): string
    {
        return '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])?$/';
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }
}
