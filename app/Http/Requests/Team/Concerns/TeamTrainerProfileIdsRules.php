<?php

namespace App\Http\Requests\Team\Concerns;

use App\Services\PartnerContext;
use Illuminate\Validation\Rule;

trait TeamTrainerProfileIdsRules
{
    protected function prepareTeamTrainerProfileIds(): void
    {
        if ($this->has('trainer_profile_ids') && ! is_array($this->input('trainer_profile_ids'))) {
            // Пустая строка из form serialize при cleared multiselect → [].
            $raw = $this->input('trainer_profile_ids');
            $this->merge([
                'trainer_profile_ids' => ($raw === null || $raw === '') ? [] : [$raw],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamTrainerProfileIdsRules(): array
    {
        if (! $this->user()?->can('trainers.view')) {
            return [];
        }

        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);

        return [
            'trainer_profile_ids' => ['nullable', 'array'],
            'trainer_profile_ids.*' => [
                'integer',
                'min:1',
                Rule::exists('trainer_profiles', 'id')->where(function ($query) use ($partnerId) {
                    if ($partnerId > 0) {
                        $query->where('partner_id', $partnerId);
                    }
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function teamTrainerProfileIdsAttributes(): array
    {
        return [
            'trainer_profile_ids' => 'тренеры',
            'trainer_profile_ids.*' => 'тренер',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function teamTrainerProfileIdsMessages(): array
    {
        return [
            'trainer_profile_ids.array' => 'Некорректный список тренеров',
            'trainer_profile_ids.*.exists' => 'Выберите тренера из списка',
        ];
    }
}
