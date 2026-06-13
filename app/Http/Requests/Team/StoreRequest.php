<?php

namespace App\Http\Requests\Team;

use App\Rules\PartnerSportTypeId;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
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

        if ($this->has('location_ids') && ! is_array($this->input('location_ids'))) {
            $this->merge(['location_ids' => []]);
        }

        if ($this->has('sport_type_id') && $this->input('sport_type_id') === '') {
            $this->merge(['sport_type_id' => null]);
        }

        if ($this->has('month_price') && $this->input('month_price') === '') {
            $this->merge(['month_price' => null]);
        }

        if ($this->has('training_base') && $this->input('training_base') === '') {
            $this->merge(['training_base' => null]);
        }

        if ($this->has('address') && $this->input('address') === '') {
            $this->merge(['address' => null]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string',
            'default_duration_minutes' => 'nullable|integer|min:1|max:600',
            'month_price' => 'nullable|integer|min:0',
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

        if ($this->user()?->can('sport_types.view')) {
            $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
            $rules['sport_type_id'] = [
                'nullable',
                'integer',
                new PartnerSportTypeId($partnerId),
            ];
        }

        if ($this->user()?->can('groups.training_base.view')) {
            $rules['training_base'] = 'nullable|string|max:255';
        }

        if ($this->user()?->can('groups.address.view')) {
            $rules['address'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'title' => 'название группы',
            'trainer_profile_id' => 'тренер',
            'location_ids' => 'объекты',
            'location_ids.*' => 'объект',
            'sport_type_id' => 'вид спорта',
            'month_price' => 'стоимость в месяц',
            'training_base' => 'тренировочная база',
            'address' => 'адрес',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Введите название',
            'title.string' => 'Введите название',
            'default_duration_minutes.integer' => 'Длительность должна быть числом (в минутах)',
            'default_duration_minutes.min' => 'Длительность должна быть больше 0 минут',
            'default_duration_minutes.max' => 'Длительность слишком большая',
            'month_price.integer' => 'Стоимость в месяц должна быть целым числом рублей',
            'month_price.min' => 'Стоимость в месяц не может быть отрицательной',
            'trainer_profile_id.exists' => 'Выберите тренера из списка',
            'location_ids.array' => 'Некорректный список объектов',
            'location_ids.*.exists' => 'Выберите объект из списка текущего партнёра',
            'sport_type_id.exists' => 'Выберите активный вид спорта из списка текущего партнёра',
            'training_base.string' => 'Тренировочная база должна быть текстом',
            'training_base.max' => 'Тренировочная база не длиннее 255 символов',
            'address.string' => 'Адрес должен быть текстом',
            'address.max' => 'Адрес не длиннее 255 символов',
        ];
    }
}
