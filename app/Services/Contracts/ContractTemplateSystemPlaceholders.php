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
        return [
            'documents_url' => url('/account-settings/documents'),
            'contract_id'   => (string) $contract->id,
            'contract_date' => now()->format('d.m.Y'),
        ];
    }
}
