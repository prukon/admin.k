<?php

namespace App\Services\Users\Import;

use App\Models\ParentProfile;

/**
 * Сопоставление строки Excel со справочником parents при импорте.
 *
 * Пустая ячейка не трогает справочник. Непустое значение из файла
 * дописывается только в пустое поле карточки. Конфликт — только когда
 * оба значения заполнены и различаются.
 */
final class UsersImportParentDirectory
{
    /**
     * @return array{lastname: ?string, firstname: ?string, middlename: ?string, phone: ?string}
     */
    public static function rowContactFields(UsersImportRow $row): array
    {
        return [
            'lastname' => self::blankToNull($row->parentLastname),
            'firstname' => self::blankToNull($row->parentFirstname),
            'middlename' => self::blankToNull($row->parentMiddlename),
            'phone' => self::blankToNull($row->parentPhone),
        ];
    }

    /**
     * @return array{lastname: ?string, firstname: ?string, middlename: ?string, phone: ?string}
     */
    public static function storedContactFields(ParentProfile $parent): array
    {
        return [
            'lastname' => self::blankToNull($parent->lastname),
            'firstname' => self::blankToNull($parent->firstname),
            'middlename' => self::blankToNull($parent->middlename),
            'phone' => self::blankToNull($parent->phone),
        ];
    }

    public static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array{lastname?: ?string, firstname?: ?string, middlename?: ?string, phone?: ?string} $left
     * @param array{lastname?: ?string, firstname?: ?string, middlename?: ?string, phone?: ?string} $right
     */
    public static function nonEmptyFieldsConflict(array $left, array $right): bool
    {
        foreach (['lastname', 'firstname', 'middlename', 'phone'] as $key) {
            $a = self::blankToNull($left[$key] ?? null);
            $b = self::blankToNull($right[$key] ?? null);
            if ($a !== null && $b !== null && $a !== $b) {
                return true;
            }
        }

        return false;
    }

    /**
     * Непустые поля $base сохраняются; пустые дописываются из $incoming.
     *
     * @param array{lastname?: ?string, firstname?: ?string, middlename?: ?string, phone?: ?string} $base
     * @param array{lastname?: ?string, firstname?: ?string, middlename?: ?string, phone?: ?string} $incoming
     * @return array{lastname: ?string, firstname: ?string, middlename: ?string, phone: ?string}
     */
    public static function mergeFillEmpty(array $base, array $incoming): array
    {
        $merged = [
            'lastname' => self::blankToNull($base['lastname'] ?? null),
            'firstname' => self::blankToNull($base['firstname'] ?? null),
            'middlename' => self::blankToNull($base['middlename'] ?? null),
            'phone' => self::blankToNull($base['phone'] ?? null),
        ];

        foreach (['lastname', 'firstname', 'middlename', 'phone'] as $key) {
            if ($merged[$key] !== null) {
                continue;
            }

            $merged[$key] = self::blankToNull($incoming[$key] ?? null);
        }

        return $merged;
    }

    public static function fileConflictsWithStored(ParentProfile $parent, UsersImportRow $row): bool
    {
        return self::nonEmptyFieldsConflict(
            self::storedContactFields($parent),
            self::rowContactFields($row),
        );
    }

    /**
     * Дописывает пустые ФИО/телефон из строки. Уже заполненные поля не затирает.
     */
    public static function fillEmptyFromRow(ParentProfile $parent, UsersImportRow $row): bool
    {
        $merged = self::mergeFillEmpty(
            self::storedContactFields($parent),
            self::rowContactFields($row),
        );

        $changed = false;
        foreach (['lastname', 'firstname', 'middlename', 'phone'] as $key) {
            $next = $merged[$key];
            $current = self::blankToNull(is_string($parent->{$key}) ? $parent->{$key} : null);
            if ($current === $next) {
                continue;
            }

            $parent->{$key} = $next;
            $changed = true;
        }

        if ($changed) {
            $parent->save();
        }

        return $changed;
    }
}
