<?php

namespace App\Http\Requests\Team;

use App\Rules\PartnerLegalEntityId;
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

        if ($this->has('location_id') && $this->input('location_id') === '') {
            $this->merge(['location_id' => null]);
        }

        if ($this->has('sport_type_id') && $this->input('sport_type_id') === '') {
            $this->merge(['sport_type_id' => null]);
        }

        if ($this->has('legal_entity_id') && $this->input('legal_entity_id') === '') {
            $this->merge(['legal_entity_id' => null]);
        }

        if ($this->has('month_price') && $this->input('month_price') === '') {
            $this->merge(['month_price' => null]);
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
            $rules['location_id'] = [
                'nullable',
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

        if ($this->user()?->can('legal_entities.view')) {
            $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
            $rules['legal_entity_id'] = [
                'nullable',
                'integer',
                new PartnerLegalEntityId($partnerId),
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'title' => 'название группы',
            'trainer_profile_id' => 'тренер',
            'location_id' => 'объект',
            'sport_type_id' => 'вид спорта',
            'legal_entity_id' => 'юр. лицо',
            'month_price' => 'стоимость по умолчанию',
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
            'month_price.integer' => 'Стоимость по умолчанию должна быть целым числом рублей',
            'month_price.min' => 'Стоимость по умолчанию не может быть отрицательной',
            'trainer_profile_id.exists' => 'Выберите тренера из списка',
            'location_id.exists' => 'Выберите объект из списка текущего партнёра',
            'sport_type_id.exists' => 'Выберите активный вид спорта из списка текущего партнёра',
            'legal_entity_id.integer' => 'Выберите юр. лицо из списка',
        ];
    }
}
