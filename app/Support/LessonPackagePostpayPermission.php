<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Право lessonPackages.type.postpay: тонкая обёртка над LessonPackageTypePermission.
 */
final class LessonPackagePostpayPermission
{
    public const NAME = LessonPackageTypePermission::PERMISSION_POSTPAY;

    public const DENY_SCHEDULE_TYPE = 'Недостаточно прав для выбора типа «Постоплата».';

    public const DENY_PACKAGE = 'Недостаточно прав для выбора абонемента типа «Постоплата».';

    public static function userCanSelect(?User $user): bool
    {
        return LessonPackageTypePermission::userCanSelectType($user, LessonPackage::SCHEDULE_TYPE_POSTPAY);
    }

    public static function rejectUnauthorizedScheduleType(
        Validator $validator,
        ?User $user,
        string $scheduleType,
        ?LessonPackage $existingPackage = null,
    ): void {
        LessonPackageTypePermission::rejectUnauthorizedScheduleType(
            $validator,
            $user,
            $scheduleType,
            $existingPackage,
        );
    }

    public static function rejectUnauthorizedPackageId(
        Validator $validator,
        ?User $user,
        ?int $packageId,
        string $errorKey,
        ?int $previouslyAssignedPackageId = null,
    ): void {
        LessonPackageTypePermission::rejectUnauthorizedPackageId(
            $validator,
            $user,
            $packageId,
            $errorKey,
            $previouslyAssignedPackageId,
        );
    }

    /**
     * «Август 2026» → Y-m-01 (как SettingPricesController::formatedDate).
     */
    public static function monthStringToDate(string $monthString): string
    {
        $parts = explode(' ', trim($monthString));
        $ruMonths = [
            'январь' => 1,
            'февраль' => 2,
            'март' => 3,
            'апрель' => 4,
            'май' => 5,
            'июнь' => 6,
            'июль' => 7,
            'август' => 8,
            'сентябрь' => 9,
            'октябрь' => 10,
            'ноябрь' => 11,
            'декабрь' => 12,
        ];
        $month = mb_strtolower($parts[0] ?? '', 'UTF-8');
        $year = $parts[1] ?? date('Y');
        $mNum = $ruMonths[$month] ?? (int) date('n');

        return sprintf('%04d-%02d-01', (int) $year, $mNum);
    }
}
