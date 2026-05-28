<?php

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->can('locations.view')) {
            if (! $this->has('team_ids')) {
                $this->merge(['team_ids' => []]);
            } elseif (! is_array($this->input('team_ids'))) {
                $this->merge(['team_ids' => []]);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
        ];

        if ($this->user()?->can('locations.view')) {
            $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
            $rules['team_ids'] = ['nullable', 'array'];
            $rules['team_ids.*'] = [
                'integer',
                'min:1',
                Rule::exists('teams', 'id')->where(function ($query) use ($partnerId) {
                    if ($partnerId > 0) {
                        $query->where('partner_id', $partnerId);
                    }
                }),
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'team_ids' => 'группы',
            'team_ids.*' => 'группа',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Введите название',
            'team_ids.array' => 'Некорректный список групп',
            'team_ids.*.exists' => 'Выберите группу из списка текущего партнёра',
        ];
    }
}

