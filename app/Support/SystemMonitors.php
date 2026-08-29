<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Системные мониторы (оверлеи): скрытое право settings.systemMonitors.view,
 * персональный флаг users.system_monitors. Superadmin проходит Gate::before.
 */
final class SystemMonitors
{
    public const PERMISSION = 'settings.systemMonitors.view';

    public const COLUMN = 'system_monitors';

    public static function canView(?User $user): bool
    {
        return $user !== null && $user->can(self::PERMISSION);
    }

    public static function isEnabled(?User $user): bool
    {
        return $user !== null && (bool) $user->system_monitors;
    }

    public static function shouldShow(?User $user): bool
    {
        return self::canView($user) && self::isEnabled($user);
    }
}
