<?php

namespace App\Services\Payments;

use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Services\PartnerLegalEntities\LegalEntityResolver;

final class PaymentCheckoutLegalEntityPresenter
{
    public function __construct(
        private readonly LegalEntityResolver $legalEntityResolver,
    ) {
    }

    public function labelForTeamId(int $partnerId, ?int $teamId): ?string
    {
        $resolution = $this->legalEntityResolver->forTeamId($teamId, $partnerId);
        if ($resolution->entity === null) {
            return null;
        }

        return $this->formatLabel($resolution->entity);
    }

    public function labelForTeam(int $partnerId, Team $team): ?string
    {
        return $this->labelForTeamId($partnerId, (int) $team->id);
    }

    public function formatLabel(PartnerLegalEntity $entity): string
    {
        $parts = [];

        $name = trim($entity->displayTitle());
        if ($name !== '') {
            $parts[] = $name;
        }

        $taxId = trim((string) ($entity->tax_id ?? ''));
        if ($taxId !== '') {
            $parts[] = 'ИНН '.$taxId;
        }

        $city = trim((string) ($entity->city ?? ''));
        if ($city !== '') {
            $parts[] = $city;
        }

        return implode(', ', $parts);
    }
}
