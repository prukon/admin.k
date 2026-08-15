<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\Schedule\TrainerSalary\Schemes\Kansas\KansasTrainerSalaryScheme;
use App\Services\Schedule\TrainerSalary\TrainerSalarySchemeResolver;

/**
 * Типы тренера видны и живут только при схеме Канзас у партнёра.
 */
final class TrainerTypeAccess
{
    public static function partnerHasKansas(?int $partnerId = null): bool
    {
        $scheme = app(TrainerSalarySchemeResolver::class)->activeScheme($partnerId);

        return $scheme !== null && $scheme->code() === KansasTrainerSalaryScheme::CODE;
    }

    public static function canViewCatalog(?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null || ! self::partnerHasKansas()) {
            return false;
        }

        if (self::isSuperadmin($user)) {
            return true;
        }

        return $user->can('trainers.view') || TrainerSalaryAccess::canViewModule($user);
    }

    public static function canManageCatalog(?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null || ! self::partnerHasKansas()) {
            return false;
        }

        return TrainerSalaryAccess::canManageModule($user);
    }

    private static function isSuperadmin(User $user): bool
    {
        return ($user->role->name ?? null) === 'superadmin';
    }
}
