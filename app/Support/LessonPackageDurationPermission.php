<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LessonPackage;
use App\Models\User;

/**
 * Поле «Срок действия (дни)» шаблона абонемента: право scheduleSlots.view.
 */
final class LessonPackageDurationPermission
{
    public const NAME = 'scheduleSlots.view';

    public const DEFAULT_CREATE_DAYS = 30;

    public const POSTPAY_DAYS = 31;

    public const NO_SCHEDULE_DAYS = 1;

    public static function userCanManage(?User $user): bool
    {
        return $user !== null && $user->can(self::NAME);
    }

    /**
     * Без права поле в модалке скрыто: create → 30, update → текущее значение шаблона.
     * Разовое и постоплата — служебные константы независимо от права.
     */
    public static function resolvedDays(
        ?User $user,
        string $scheduleType,
        mixed $requested,
        ?LessonPackage $existing = null,
    ): int {
        if ($scheduleType === LessonPackage::SCHEDULE_TYPE_POSTPAY) {
            return self::POSTPAY_DAYS;
        }

        if ($scheduleType === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE) {
            return self::NO_SCHEDULE_DAYS;
        }

        if (self::userCanManage($user)) {
            return (int) $requested;
        }

        if ($existing !== null) {
            return (int) $existing->duration_days;
        }

        return self::DEFAULT_CREATE_DAYS;
    }
}
