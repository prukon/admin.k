<?php

namespace App\Http\Requests\Tinkoff;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // при отсутствии периода — обязательна сумма; при месячном периоде сумма из users_prices
            'outSum' => ['required_without:formatedPaymentDate', 'nullable', 'string', 'max:32'],

            // card/tpay (sbp отдельным методом)
            'method' => ['nullable', 'string', 'in:card,tpay'],

            // YYYY-MM-01 (месяц оплаты)
            'formatedPaymentDate' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],

            // дополнительный платеж (user_period_prices)
            'payment_kind' => ['nullable', 'string', 'in:custom_payment'],
            'custom_payment_id' => ['required_if:payment_kind,custom_payment', 'nullable', 'integer', 'min:1'],
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
        ];
    }

    public function messages(): array
    {
        return [
            'outSum.required_without' => 'Укажите сумму или дополнительный платеж.',
            'custom_payment_id.required_if' => 'Выберите дополнительный платеж для оплаты.',
        ];
    }
}

