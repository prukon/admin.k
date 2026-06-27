<?php

namespace App\Services\PartnerLegalEntities;

use App\Models\PartnerLegalEntity;

final class LegalEntityResolution
{
    public function __construct(
        public readonly ?PartnerLegalEntity $entity,
        public readonly bool $usedDefaultFallback = false,
    ) {
    }

    public function hasEntity(): bool
    {
        return $this->entity !== null;
    }
}
