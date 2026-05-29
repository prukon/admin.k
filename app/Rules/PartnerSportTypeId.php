<?php

namespace App\Rules;

use App\Models\SportType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PartnerSportTypeId implements ValidationRule
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
            $fail('Выберите активный вид спорта из списка текущего партнёра');

            return;
        }

        $query = SportType::query()
            ->whereKey((int) $value)
            ->where('partner_id', $this->partnerId);

        if ($this->requireEnabled) {
            $query->where('is_enabled', true);
        }

        if (! $query->exists()) {
            $fail('Выберите активный вид спорта из списка текущего партнёра');
        }
    }
}
