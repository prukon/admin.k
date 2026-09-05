<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Чекбокс заморозки шаблона абонемента: право scheduleSlots.view.
 */
final class LessonPackageFreezePermission
{
    public const NAME = 'scheduleSlots.view';

    public const DENY_ENABLE = 'Недостаточно прав для включения заморозки.';

    public static function userCanManage(?User $user): bool
    {
        return $user !== null && $user->can(self::NAME);
    }

    public static function rejectUnauthorizedEnable(
        Validator $validator,
        ?User $user,
        bool $freezeEnabled,
    ): void {
        if ($freezeEnabled && ! self::userCanManage($user)) {
            $validator->errors()->add('freeze_enabled', self::DENY_ENABLE);
        }
    }

    /**
     * Без права нельзя включить флаг; на update текущее значение шаблона сохраняется.
     */
    public static function resolvedEnabled(
        ?User $user,
        bool $requested,
        ?LessonPackage $existing = null,
    ): bool {
        if (self::userCanManage($user)) {
            return $requested;
        }

        if ($existing !== null) {
            return (bool) $existing->freeze_enabled;
        }

        return false;
    }

    /**
     * Без права дни заморозки на update не трогаем (модалка без поля шлёт пустое / 0).
     */
    public static function resolvedDays(
        ?User $user,
        bool $requestedEnabled,
        int $requestedDays,
        ?LessonPackage $existing = null,
    ): int {
        if (self::userCanManage($user)) {
            return $requestedEnabled ? $requestedDays : 0;
        }

        if ($existing !== null) {
            return (int) $existing->freeze_days;
        }

        return 0;
    }
}
