<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Скрытые права lessonPackages.type.*: выбор schedule_type шаблона и назначение пакета.
 */
final class LessonPackageTypePermission
{
    public const PERMISSION_FIXED = 'lessonPackages.type.fixed';

    public const PERMISSION_FLEXIBLE = 'lessonPackages.type.flexible';

    public const PERMISSION_NO_SCHEDULE = 'lessonPackages.type.no_schedule';

    public const PERMISSION_POSTPAY = 'lessonPackages.type.postpay';

    /** @var array<string, string> */
    public const PERMISSIONS = [
        LessonPackage::SCHEDULE_TYPE_FIXED => self::PERMISSION_FIXED,
        LessonPackage::SCHEDULE_TYPE_FLEXIBLE => self::PERMISSION_FLEXIBLE,
        LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE => self::PERMISSION_NO_SCHEDULE,
        LessonPackage::SCHEDULE_TYPE_POSTPAY => self::PERMISSION_POSTPAY,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        LessonPackage::SCHEDULE_TYPE_FIXED => 'Фиксированный',
        LessonPackage::SCHEDULE_TYPE_FLEXIBLE => 'Предоплата',
        LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE => 'Разовое занятие',
        LessonPackage::SCHEDULE_TYPE_POSTPAY => 'Постоплата',
    ];

    public static function permissionName(string $scheduleType): ?string
    {
        return self::PERMISSIONS[$scheduleType] ?? null;
    }

    public static function label(string $scheduleType): string
    {
        return self::LABELS[$scheduleType] ?? $scheduleType;
    }

    public static function denyScheduleTypeMessage(string $scheduleType): string
    {
        return 'Недостаточно прав для выбора типа «'.self::label($scheduleType).'».';
    }

    public static function denyPackageMessage(string $scheduleType): string
    {
        return 'Недостаточно прав для выбора абонемента типа «'.self::label($scheduleType).'».';
    }

    public static function userCanSelectType(?User $user, string $scheduleType): bool
    {
        $permission = self::permissionName($scheduleType);
        if ($permission === null) {
            return false;
        }

        return $user !== null && $user->can($permission);
    }

    public static function userCanSelectAny(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (self::PERMISSIONS as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function allowedTypes(?User $user): array
    {
        $out = [];
        foreach (array_keys(self::PERMISSIONS) as $type) {
            if (self::userCanSelectType($user, $type)) {
                $out[] = $type;
            }
        }

        return $out;
    }

    /**
     * @return list<array{value: string, label: string, permission: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::PERMISSIONS as $type => $permission) {
            $out[] = [
                'value' => $type,
                'label' => self::label($type),
                'permission' => $permission,
            ];
        }

        return $out;
    }

    /**
     * Запрет выбора типа при создании / смене типа.
     * Редактирование уже существующего шаблона (тип не меняется) — разрешено без права.
     */
    public static function rejectUnauthorizedScheduleType(
        Validator $validator,
        ?User $user,
        string $scheduleType,
        ?LessonPackage $existingPackage = null,
    ): void {
        if ($scheduleType === '' || ! isset(self::PERMISSIONS[$scheduleType])) {
            return;
        }

        if ($existingPackage !== null && (string) $existingPackage->schedule_type === $scheduleType) {
            return;
        }

        if (! self::userCanSelectType($user, $scheduleType)) {
            $validator->errors()->add('schedule_type', self::denyScheduleTypeMessage($scheduleType));
        }
    }

    /**
     * Запрет назначения пакета, если нет права на его тип.
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

        $package = LessonPackage::query()->find($packageId);
        if ($package === null) {
            return;
        }

        $scheduleType = (string) $package->schedule_type;
        if (! isset(self::PERMISSIONS[$scheduleType])) {
            return;
        }

        if (! self::userCanSelectType($user, $scheduleType)) {
            $validator->errors()->add($errorKey, self::denyPackageMessage($scheduleType));
        }
    }

    /**
     * @param  list<string>  $scheduleTypes
     * @param  list<string>  $previouslySelectedTypes
     */
    public static function rejectUnauthorizedScheduleTypes(
        Validator $validator,
        ?User $user,
        array $scheduleTypes,
        array $previouslySelectedTypes = [],
        string $errorKey = 'schedule_types',
    ): void {
        $keep = array_flip($previouslySelectedTypes);

        foreach ($scheduleTypes as $index => $type) {
            if (! is_string($type) || $type === '' || ! isset(self::PERMISSIONS[$type])) {
                continue;
            }

            if (isset($keep[$type])) {
                continue;
            }

            if (! self::userCanSelectType($user, $type)) {
                $message = self::denyScheduleTypeMessage($type);
                $validator->errors()->add($errorKey, $message);
                $validator->errors()->add($errorKey.'.'.$index, $message);
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\LessonPackage>|\Illuminate\Database\Query\Builder  $query
     * @param  list<int>  $keepIds
     */
    public static function restrictQueryToAllowedTypes($query, ?User $user, array $keepIds = []): void
    {
        $allowed = self::allowedTypes($user);
        if (count($allowed) === count(self::PERMISSIONS)) {
            return;
        }

        $keepIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $keepIds),
            static fn (int $id) => $id > 0
        )));

        $query->where(function ($q) use ($allowed, $keepIds): void {
            if ($allowed !== []) {
                $q->whereIn('schedule_type', $allowed);
            } else {
                $q->whereRaw('1 = 0');
            }

            if ($keepIds !== []) {
                $q->orWhereIn('id', $keepIds);
            }
        });
    }
}
