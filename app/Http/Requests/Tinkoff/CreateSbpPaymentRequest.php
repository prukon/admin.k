<?php

namespace App\Http\Requests\Tinkoff;

use Illuminate\Foundation\Http\FormRequest;

class CreateSbpPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outSum' => ['required_without:formatedPaymentDate', 'nullable', 'string', 'max:32'],
            'formatedPaymentDate' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'outSum' => 'сумма',
            'formatedPaymentDate' => 'период оплаты',
        ];
    }

    public function messages(): array
    {
        return [
            'outSum.required_without' => 'Укажите сумму или период абонемента.',
        ];
    }
}

