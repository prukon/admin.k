<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\Schedule\TrainerSalary\TrainerSalarySchemeResolver;

/**
 * Доступ к модулю ЗП: пользовательские view/manage + включённая схема партнёра.
 */
final class TrainerSalaryAccess
{
    public static function canViewModule(?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null) {
            return false;
        }

        if (self::isSuperadmin($user)) {
            return true;
        }

        if (! self::resolver()->hasActiveScheme()) {
            return false;
        }

        return $user->hasPermission('schedule.trainerSalary.view');
    }

    public static function canManageModule(?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null) {
            return false;
        }

        if (self::isSuperadmin($user)) {
            return true;
        }

        if (! self::resolver()->hasActiveScheme()) {
            return false;
        }

        return $user->hasPermission('schedule.trainerSalary.manage');
    }

    private static function isSuperadmin(User $user): bool
    {
        return ($user->role->name ?? null) === 'superadmin';
    }

    private static function resolver(): TrainerSalarySchemeResolver
    {
        return app(TrainerSalarySchemeResolver::class);
    }
}
