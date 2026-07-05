<?php

namespace App\Services\Users\Import;

final class UsersImportColumns
{
    public const STUDENT_LASTNAME = 'student_lastname';
    public const STUDENT_NAME = 'student_name';
    public const TEAM = 'team';
    public const LEGAL_ENTITY = 'legal_entity';
    public const STUDENT_EMAIL = 'student_email';
    public const STUDENT_PHONE = 'student_phone';
    public const BIRTHDAY = 'birthday';
    public const IS_ENABLED = 'is_enabled';
    public const PARENT_EMAIL = 'parent_email';
    public const PARENT_LASTNAME = 'parent_lastname';
    public const PARENT_FIRSTNAME = 'parent_firstname';
    public const PARENT_MIDDLENAME = 'parent_middlename';
    public const PARENT_PHONE = 'parent_phone';

    /**
     * @return array<string, string> column key => header label in template
     */
    public static function headerLabels(): array
    {
        return [
            self::STUDENT_LASTNAME => 'Фамилия ученика',
            self::STUDENT_NAME => 'Имя ученика',
            self::TEAM => 'Группа',
            self::LEGAL_ENTITY => 'Юр. лицо',
            self::STUDENT_EMAIL => 'Email ученика',
            self::STUDENT_PHONE => 'Телефон ученика',
            self::BIRTHDAY => 'Дата рождения',
            self::IS_ENABLED => 'Активен',
            self::PARENT_EMAIL => 'Email родителя',
            self::PARENT_LASTNAME => 'Фамилия родителя',
            self::PARENT_FIRSTNAME => 'Имя родителя',
            self::PARENT_MIDDLENAME => 'Отчество родителя',
            self::PARENT_PHONE => 'Телефон родителя',
        ];
    }

    /**
     * @return list<string>
     */
    public static function requiredColumnKeys(): array
    {
        return [
            self::STUDENT_LASTNAME,
            self::STUDENT_NAME,
        ];
    }

    /**
     * @return list<string>
     */
    public static function orderedColumnKeys(): array
    {
        return array_keys(self::headerLabels());
    }
}
