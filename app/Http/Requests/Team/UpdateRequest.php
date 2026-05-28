<?php

namespace App\Http\Requests\Team;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('trainer_profile_id') && $this->input('trainer_profile_id') === '') {
            $this->merge(['trainer_profile_id' => null]);
        }

        if ($this->user()?->can('locations.view')) {
            if (! $this->has('location_ids')) {
                $this->merge(['location_ids' => []]);
            } elseif (! is_array($this->input('location_ids'))) {
                $this->merge(['location_ids' => []]);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string',
            'type' => 'required|string|in:group,individual',
            'default_duration_minutes' => 'nullable|integer|min:1|max:600',
            'weekdays' => 'nullable|array',
            'is_enabled' => 'boolean',
            'order_by' => 'nullable|integer',
        ];

        if ($this->user()?->can('trainers.view')) {
            $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
            $rules['trainer_profile_id'] = [
                'nullable',
                'integer',
                Rule::exists('trainer_profiles', 'id')->where(function ($query) use ($partnerId) {
                    if ($partnerId > 0) {
                        $query->where('partner_id', $partnerId);
                    }
                }),
            ];
        }

        if ($this->user()?->can('locations.view')) {
            $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
            $rules['location_ids'] = ['nullable', 'array'];
            $rules['location_ids.*'] = [
                'integer',
                'min:1',
                Rule::exists('locations', 'id')->where(function ($query) use ($partnerId) {
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
            'title' => 'название группы',
            'type' => 'тип',
            'trainer_profile_id' => 'тренер',
            'location_ids' => 'локации',
            'location_ids.*' => 'локация',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название',
            'title.string' => 'Введите название',
            'type.required' => 'Укажите тип',
            'type.in' => 'Некорректный тип',
            'default_duration_minutes.integer' => 'Длительность должна быть числом (в минутах)',
            'default_duration_minutes.min' => 'Длительность должна быть больше 0 минут',
            'default_duration_minutes.max' => 'Длительность слишком большая',
            'trainer_profile_id.exists' => 'Выберите тренера из списка',
            'location_ids.array' => 'Некорректный список локаций',
            'location_ids.*.exists' => 'Выберите локацию из списка текущего партнёра',
        ];
    }
}
