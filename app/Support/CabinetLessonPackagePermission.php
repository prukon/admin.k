<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;

/**
 * Права консоли на отображение абонементов по schedule_type.
 * Не путать с setPrices.packageAssignments.view (вкладка назначений в админке).
 */
final class CabinetLessonPackagePermission
{
    public const FIXED = 'setPrices.cabinetPackages.fixed.view';

    public const FLEXIBLE = 'setPrices.cabinetPackages.flexible.view';

    public const SINGLE = 'setPrices.cabinetPackages.single.view';

    public const POSTPAY = 'setPrices.cabinetPackages.postpay.view';

    /** @var list<string> */
    public const ALL = [
        self::FIXED,
        self::FLEXIBLE,
        self::SINGLE,
        self::POSTPAY,
    ];

    /** Типы, которые рисуются карточками ULP на консоли. */
    /** @var list<string> */
    public const ASSIGNMENT = [
        self::FIXED,
        self::FLEXIBLE,
        self::SINGLE,
    ];

    public static function forScheduleType(string $scheduleType): ?string
    {
        return match ($scheduleType) {
            LessonPackage::SCHEDULE_TYPE_FIXED => self::FIXED,
            LessonPackage::SCHEDULE_TYPE_FLEXIBLE => self::FLEXIBLE,
            LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE => self::SINGLE,
            LessonPackage::SCHEDULE_TYPE_POSTPAY => self::POSTPAY,
            default => null,
        };
    }

    public static function userCanViewType(?User $user, string $scheduleType): bool
    {
        $permission = self::forScheduleType($scheduleType);

        return $permission !== null && $user !== null && $user->can($permission);
    }

    public static function userCanViewAnyAssignment(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (self::ASSIGNMENT as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function userCanViewSeasonsBlock(?User $user): bool
    {
        return $user !== null
            && (
                $user->can('setPrices.cabinetSeasons.view')
                || $user->can(self::POSTPAY)
            );
    }

    public static function userCanViewAssignment(?User $user, UserLessonPackage $ulp): bool
    {
        $scheduleType = (string) ($ulp->lessonPackage?->schedule_type ?? '');

        if (! in_array($scheduleType, LessonPackage::ASSIGNMENT_SCHEDULE_TYPES, true)) {
            return false;
        }

        return self::userCanViewType($user, $scheduleType);
    }
}
