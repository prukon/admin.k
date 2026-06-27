<?php

namespace App\Rules;

use App\Models\PartnerLegalEntity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PartnerLegalEntityId implements ValidationRule
{
    public function __construct(
        private readonly int $partnerId,
        private readonly bool $requireEnabled = true,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->partnerId <= 0) {
            $fail('Выберите юр. лицо из списка текущего партнёра');

            return;
        }

        $query = PartnerLegalEntity::query()
            ->whereKey((int) $value)
            ->where('partner_id', $this->partnerId);

        if ($this->requireEnabled) {
            $query->where('is_enabled', true);
        }

        if (! $query->exists()) {
            $fail('Выберите активное юр. лицо из списка текущего партнёра');
        }
    }
}
