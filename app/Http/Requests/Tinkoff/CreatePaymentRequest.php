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

            // периодный абонемент (user_period_prices)
            'payment_kind' => ['nullable', 'string', 'in:abonement'],
            'abonement_id' => ['required_if:payment_kind,abonement', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'outSum' => 'сумма',
            'formatedPaymentDate' => 'период оплаты',
            'method' => 'способ оплаты',
            'payment_kind' => 'тип оплаты',
            'abonement_id' => 'абонемент',
        ];
    }

    public function messages(): array
    {
        return [
            'outSum.required_without' => 'Укажите сумму или период абонемента.',
            'abonement_id.required_if' => 'Выберите абонемент для оплаты.',
        ];
    }
}

