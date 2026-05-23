<?php

namespace App\Services\Contracts;

use App\Models\Contract;

/**
 * Служебные плейсхолдеры (в т.ч. из подсказок к email), подставляемые при генерации PDF.
 */
class ContractTemplateSystemPlaceholders
{
    /**
     * @return array<string, string>
     */
    public static function forContract(Contract $contract): array
    {
        $contract->loadMissing('user');
        $student = $contract->user;

        $studentName = trim(($student->lastname ?? '') . ' ' . ($student->name ?? ''));

        return [
            'documents_url' => url('/account-settings/documents'),
            'student_name'  => $studentName,
            'contract_id'   => (string) $contract->id,
        ];
    }
}
