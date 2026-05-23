<?php

namespace App\Services\Contracts;

/**
 * Источники предзаполнения полей формы договора (итерация 2 — кабинет клиента).
 */
class ContractTemplatePrefillSources
{
    public const STUDENT_FULL_NAME  = 'student.full_name';
    public const STUDENT_PHONE      = 'student.phone';
    public const STUDENT_EMAIL      = 'student.email';
    public const PARENT_FULL_NAME   = 'parent.full_name';
    public const PARENT_LASTNAME    = 'parent.lastname';
    public const PARENT_FIRSTNAME   = 'parent.firstname';
    public const PARENT_MIDDLENAME  = 'parent.middlename';
    public const TEAM_TITLE         = 'team.title';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::STUDENT_FULL_NAME  => 'Ученик: ФИО',
            self::STUDENT_PHONE      => 'Ученик: телефон',
            self::STUDENT_EMAIL      => 'Ученик: email',
            self::PARENT_FULL_NAME   => 'Родитель: ФИО',
            self::PARENT_LASTNAME    => 'Родитель: фамилия',
            self::PARENT_FIRSTNAME   => 'Родитель: имя',
            self::PARENT_MIDDLENAME  => 'Родитель: отчество',
            self::TEAM_TITLE         => 'Группа: название',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }
}
