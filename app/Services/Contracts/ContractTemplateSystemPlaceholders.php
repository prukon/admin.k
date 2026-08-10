<?php

namespace App\Services\Contracts;

use App\Models\Contract;

/**
 * Служебные плейсхолдеры (в т.ч. из подсказок к email), подставляемые при генерации PDF.
 */
class ContractTemplateSystemPlaceholders
{
    public function __construct(
        private readonly ContractLegalEntityPlaceholderService $legalEntityPlaceholders,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function forContract(Contract $contract): array
    {
        return array_merge(
            [
                'documents_url' => url('/account-settings/documents'),
                'contract_id'   => (string) $contract->id,
                'contract_date' => now()->format('d.m.Y'),
            ],
            $this->legalEntityPlaceholders->valuesForContract($contract),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function forContractStatic(Contract $contract): array
    {
        return app(self::class)->forContract($contract);
    }
}
