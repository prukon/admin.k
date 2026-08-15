<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\TrainerType;
use App\Support\TrainerTypeAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainerTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TrainerTypeAccess::canManageCatalog($this->user());
    }

    protected function prepareForValidation(): void
    {
        foreach (['rate_per_training', 'base_premium'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->has('is_enabled')) {
            $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) (app(\App\Services\PartnerContext::class)->partnerId() ?? 0);
        $type = $this->route('trainerType');
        $typeId = $type instanceof TrainerType ? (int) $type->id : (int) $type;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('trainer_types', 'name')
                    ->where(function ($query) use ($partnerId) {
                        if ($partnerId > 0) {
                            $query->where('partner_id', $partnerId);
                        }
                    })
                    ->ignore($typeId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_enabled' => ['nullable', 'boolean'],
            'rate_per_training' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'base_premium' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'sort_order' => 'порядок сортировки',
            'is_enabled' => 'активность',
            'rate_per_training' => 'оклад за тренировку',
            'base_premium' => 'базовая премия за тренировку',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Введите название типа.',
            'name.unique' => 'Тип тренера с таким названием уже существует.',
            'rate_per_training.required' => 'Укажите оклад за тренировку.',
            'rate_per_training.numeric' => 'Оклад за тренировку должен быть числом (рубли, можно с копейками).',
            'rate_per_training.min' => 'Оклад за тренировку не может быть отрицательным.',
            'base_premium.required' => 'Укажите базовую премию за тренировку.',
            'base_premium.numeric' => 'Базовая премия должна быть числом (рубли, можно с копейками).',
            'base_premium.min' => 'Базовая премия не может быть отрицательной.',
        ];
    }
}
