<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Право lessonPackages.type.postpay: выбор schedule_type=postpay и назначение postpay-шаблона.
 */
final class LessonPackagePostpayPermission
{
    public const NAME = 'lessonPackages.type.postpay';

    public const DENY_SCHEDULE_TYPE = 'Недостаточно прав для выбора типа «Постоплата».';

    public const DENY_PACKAGE = 'Недостаточно прав для выбора абонемента типа «Постоплата».';

    public static function userCanSelect(?User $user): bool
    {
        return $user !== null && $user->can(self::NAME);
    }

    /**
     * Запрет выбора типа postpay при создании / смене типа.
     * Редактирование уже существующего postpay-шаблона (тип не меняется) — разрешено без права.
     */
    public static function rejectUnauthorizedScheduleType(
        Validator $validator,
        ?User $user,
        string $scheduleType,
        ?LessonPackage $existingPackage = null,
    ): void {
        if ($scheduleType !== LessonPackage::SCHEDULE_TYPE_POSTPAY) {
            return;
        }

        if ($existingPackage !== null && $existingPackage->isPostpay()) {
            return;
        }

        if (! self::userCanSelect($user)) {
            $validator->errors()->add('schedule_type', self::DENY_SCHEDULE_TYPE);
        }
    }

    /**
     * Запрет назначения postpay-пакета, если право нет.
     * Повторная отправка того же уже назначенного package_id — разрешена без права.
     */
    public static function rejectUnauthorizedPackageId(
        Validator $validator,
        ?User $user,
        ?int $packageId,
        string $errorKey,
        ?int $previouslyAssignedPackageId = null,
    ): void {
        if ($packageId === null || $packageId <= 0) {
            return;
        }

        if ($previouslyAssignedPackageId !== null
            && $previouslyAssignedPackageId > 0
            && $previouslyAssignedPackageId === $packageId) {
            return;
        }

        if (self::userCanSelect($user)) {
            return;
        }

        $package = LessonPackage::query()->find($packageId);
        if ($package !== null && $package->isPostpay()) {
            $validator->errors()->add($errorKey, self::DENY_PACKAGE);
        }
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
