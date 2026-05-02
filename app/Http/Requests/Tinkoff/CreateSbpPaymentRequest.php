<?php

namespace App\Http\Requests\Tinkoff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSbpPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outSum' => [
                Rule::requiredIf(function () {
                    $kind = (string) $this->input('payment_kind', '');
                    if ($kind === 'custom_payment' || $kind === 'lesson_package') {
                        return false;
                    }

                    return ! $this->filled('formatedPaymentDate');
                }),
                'nullable',
                'string',
                'max:32',
            ],
            'formatedPaymentDate' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],

            'payment_kind' => ['nullable', 'string', 'in:custom_payment,lesson_package'],
            'custom_payment_id' => ['required_if:payment_kind,custom_payment', 'nullable', 'integer', 'min:1'],
            'user_lesson_package_id' => ['required_if:payment_kind,lesson_package', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'outSum' => 'сумма',
            'formatedPaymentDate' => 'период оплаты',
            'payment_kind' => 'тип оплаты',
            'custom_payment_id' => 'дополнительный платеж',
            'user_lesson_package_id' => 'назначение абонемента',
        ];
    }

    public function messages(): array
    {
        return [
            'outSum.required' => 'Укажите сумму оплаты.',
            'custom_payment_id.required_if' => 'Выберите дополнительный платеж для оплаты.',
            'user_lesson_package_id.required_if' => 'Выберите абонемент для оплаты.',
        ];
    }
}

