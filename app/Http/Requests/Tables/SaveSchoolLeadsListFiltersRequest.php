<?php

declare(strict_types=1);

namespace App\Http\Requests\Tables;

use App\Services\Tables\PersistedListFilters;
use Illuminate\Validation\Rule;

final class SaveSchoolLeadsListFiltersRequest extends SavePersistedListFiltersRequest
{
    public function tableKey(): string
    {
        return PersistedListFilters::SCHOOL_LEADS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(): array
    {
        $service = app(PersistedListFilters::class);
        $rules = [
            'status_ids' => ['nullable', 'array', 'max:100'],
            'status_ids.*' => ['string', Rule::in($service->allowedLeadStatusIds())],
            'team_ids' => ['nullable', 'array', 'max:100'],
            'team_ids.*' => ['string', Rule::in($service->allowedTeamValues($this->user(), PersistedListFilters::SCHOOL_LEADS))],
            'has_special_conditions' => ['nullable', 'boolean'],
        ];

        if ($this->user()?->can('districts.view')) {
            $rules['district_id'] = ['nullable', 'string', Rule::in($service->allowedDistrictValues())];
        }

        if ($this->user()?->can('locations.view')) {
            $rules['location_ids'] = ['nullable', 'array', 'max:100'];
            $rules['location_ids.*'] = ['string', Rule::in($service->allowedLocationValues())];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function fieldAttributes(): array
    {
        return [
            'status_ids' => 'Статус',
            'status_ids.*' => 'Статус',
            'district_id' => 'Район',
            'location_ids' => 'Объект',
            'location_ids.*' => 'Объект',
            'team_ids' => 'Секция',
            'team_ids.*' => 'Секция',
            'has_special_conditions' => 'Особые условия',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMessages(): array
    {
        return [
            'status_ids.array' => 'Выберите статусы из списка.',
            'status_ids.max' => 'Слишком много статусов.',
            'status_ids.*.in' => 'Выберите статус из списка.',
            'district_id.in' => 'Выберите район из списка.',
            'location_ids.array' => 'Выберите объекты из списка.',
            'location_ids.max' => 'Слишком много объектов.',
            'location_ids.*.in' => 'Выберите объект из списка.',
            'team_ids.array' => 'Выберите секции из списка.',
            'team_ids.max' => 'Слишком много секций.',
            'team_ids.*.in' => 'Выберите секцию из списка.',
            'has_special_conditions.boolean' => 'Отметьте «Есть особые условия» или снимите галочку.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->boolean('reset')) {
            return;
        }

        $merge = [];
        foreach (['status_ids', 'location_ids', 'team_ids'] as $key) {
            if (! $this->exists($key)) {
                $merge[$key] = [];

                continue;
            }

            $value = $this->input($key);
            if (! is_array($value)) {
                $merge[$key] = ($value === null || $value === '') ? [] : [$value];
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
