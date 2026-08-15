<?php

namespace App\Http\Requests\Concerns;

use App\Services\PartnerContext;
use App\Support\TrainerTypeAccess;
use Illuminate\Validation\Rule;

trait ValidatesTrainerTypeId
{
    /**
     * @return list<mixed>
     */
    protected function trainerTypeIdRules(?int $currentTypeId = null): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
        $required = TrainerTypeAccess::partnerHasKansas($partnerId > 0 ? $partnerId : null);

        $exists = Rule::exists('trainer_types', 'id')->where(function ($query) use ($partnerId, $currentTypeId) {
            if ($partnerId <= 0) {
                return;
            }

            $query->where('partner_id', $partnerId)
                ->where(function ($inner) use ($currentTypeId) {
                    $inner->where('is_enabled', 1);
                    if ($currentTypeId !== null && $currentTypeId > 0) {
                        $inner->orWhere('id', $currentTypeId);
                    }
                });
        });

        return $required
            ? ['required', 'integer', $exists]
            : ['nullable', 'integer', $exists];
    }

    /**
     * @return array<string, string>
     */
    protected function trainerTypeIdAttributes(): array
    {
        return [
            'trainer_type_id' => 'тип тренера',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function trainerTypeIdMessages(): array
    {
        return [
            'trainer_type_id.required' => 'Выберите тип тренера.',
            'trainer_type_id.exists' => 'Выберите тип тренера из списка.',
        ];
    }

    protected function prepareTrainerTypeIdForValidation(): void
    {
        if ($this->has('trainer_type_id') && $this->input('trainer_type_id') === '') {
            $this->merge(['trainer_type_id' => null]);
        }
    }
}
