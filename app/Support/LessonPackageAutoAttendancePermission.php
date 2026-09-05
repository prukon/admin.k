<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Чекбокс автосписания шаблона абонемента: право scheduleSlots.view.
 */
final class LessonPackageAutoAttendancePermission
{
    public const NAME = 'scheduleSlots.view';

    public const DENY_ENABLE = 'Недостаточно прав для включения автосписания.';

    public static function userCanManage(?User $user): bool
    {
        return $user !== null && $user->can(self::NAME);
    }

    public static function rejectUnauthorizedEnable(
        Validator $validator,
        ?User $user,
        bool $autoAttendanceEnabled,
    ): void {
        if ($autoAttendanceEnabled && ! self::userCanManage($user)) {
            $validator->errors()->add('auto_attendance_enabled', self::DENY_ENABLE);
        }
    }

    /**
     * Без права нельзя включить флаг; на update текущее значение шаблона сохраняется.
     */
    public static function resolvedValue(
        ?User $user,
        bool $requested,
        ?LessonPackage $existing = null,
    ): bool {
        if (self::userCanManage($user)) {
            return $requested;
        }

        if ($existing !== null) {
            return (bool) $existing->auto_attendance_enabled;
        }

        return false;
    }
}
