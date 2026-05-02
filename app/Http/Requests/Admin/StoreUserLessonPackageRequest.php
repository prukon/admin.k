<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserLessonPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'user_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('partner_id', $partnerId)),
            ],
            'lesson_package_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id'),
            ],

            'fee_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'lesson_package_id' => 'абонемент',
            'fee_amount' => 'стоимость для ученика',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.exists' => 'Ученик не найден или недоступен в контексте текущего партнёра.',

            'lesson_package_id.required' => 'Выберите абонемент.',
            'lesson_package_id.exists' => 'Абонемент не найден.',

            'fee_amount.required' => 'Укажите стоимость абонемента для этого ученика.',
            'fee_amount.numeric' => 'Стоимость должна быть числом.',
            'fee_amount.min' => 'Стоимость не может быть отрицательной.',
            'fee_amount.max' => 'Слишком большая сумма.',
        ];
    }
}
