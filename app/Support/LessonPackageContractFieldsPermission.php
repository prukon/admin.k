<?php

namespace App\Support;

use App\Models\User;
use App\Services\Contracts\ContractLessonPackageBinder;

/**
 * Поля шаблона «Для договора»: право contracts.lessonPackage.bind.
 */
final class LessonPackageContractFieldsPermission
{
    public const NAME = ContractLessonPackageBinder::PERMISSION;

    public static function userCanManage(?User $user): bool
    {
        return $user !== null && $user->can(self::NAME);
    }

    /**
     * Без права create → null, update → уже сохранённое значение. Крафтовый POST игнорируется.
     */
    public static function resolvedInt(?User $user, mixed $requested, mixed $existingValue = null): ?int
    {
        if (self::userCanManage($user)) {
            if ($requested === null || $requested === '') {
                return null;
            }

            return (int) $requested;
        }

        if ($existingValue === null || $existingValue === '') {
            return null;
        }

        return (int) $existingValue;
    }
}
