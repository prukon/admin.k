<?php

namespace App\Services\Contracts;

/**
 * Привязки полей шаблона к данным CRM (ключ = переменная DOCX).
 */
class ContractTemplatePrefillSources
{
    public const CHILD_FULL_NAME    = 'child_full_name';
    public const CHILD_LASTNAME     = 'child_lastname';
    public const CHILD_FIRSTNAME    = 'child_firstname';
    public const CHILD_BIRTHDAY     = 'child_birthday';
    public const STUDENT_PHONE      = 'student_phone';
    public const STUDENT_EMAIL      = 'student_email';
    public const PARENT_FULL_NAME   = 'parent_full_name';
    public const PARENT_LASTNAME    = 'parent_lastname';
    public const PARENT_FIRSTNAME   = 'parent_firstname';
    public const PARENT_MIDDLENAME  = 'parent_middlename';
    public const PARENT_PASSPORT    = 'parent_passport';
    public const PARENT_PASSPORT_ISSUED = 'parent_passport_issued';
    public const PARENT_ADDRESS     = 'parent_address';
    public const PARENT_PHONE       = 'parent_phone';
    public const PARENT_EMAIL       = 'parent_email';
    public const TEAM_TITLE         = 'team_title';

    /**
     * @return array<string, string> key => label for admin UI
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (ContractTemplateVariablePresets::recommended() as $preset) {
            if ($preset['prefill_source'] !== null) {
                $labels[$preset['prefill_source']] = $preset['label'];
            }
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }
}
