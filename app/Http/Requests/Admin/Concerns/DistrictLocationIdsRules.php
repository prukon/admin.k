<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Services\PartnerContext;
use Illuminate\Validation\Rule;

trait DistrictLocationIdsRules
{
    protected function prepareDistrictLocationIds(): void
    {
        if ($this->has('location_ids') && ! is_array($this->input('location_ids'))) {
            $this->merge(['location_ids' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function districtLocationIdsRules(): array
    {
        if (! $this->user()?->can('locations.view')) {
            return [];
        }

        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);

        return [
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => [
                'integer',
                'min:1',
                Rule::exists('locations', 'id')->where(function ($query) use ($partnerId) {
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
    protected function districtLocationIdsAttributes(): array
    {
        return [
            'location_ids' => 'объекты',
            'location_ids.*' => 'объект',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function districtLocationIdsMessages(): array
    {
        return [
            'location_ids.array' => 'Некорректный список объектов',
            'location_ids.*.exists' => 'Выберите объект из списка текущего партнёра',
        ];
    }
}
