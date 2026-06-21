<?php

namespace App\Http\Requests\Tinkoff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Сумма Init всегда из БД для monthly (users_prices), custom_payment и lesson_package;
            // поле outSum в форме не является источником истины и для этих видов не обязательно.
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

            // card/tpay (sbp отдельным методом)
            'method' => ['nullable', 'string', 'in:card,tpay'],

            // YYYY-MM-01 (месяц оплаты)
            'formatedPaymentDate' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'team_id' => ['nullable', 'integer', 'min:1'],

            // дополнительный платеж (user_period_prices) / назначенный абонемент (user_lesson_packages)
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
            'method' => 'способ оплаты',
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

