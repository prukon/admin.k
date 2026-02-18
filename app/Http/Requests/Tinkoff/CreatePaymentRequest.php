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
            // приходит как строка в рублях (может быть с запятой)
            'outSum' => ['required', 'string', 'max:32'],

            // card/tpay (sbp отдельным методом)
            'method' => ['nullable', 'string', 'in:card,tpay'],

            // YYYY-MM-01 (месяц оплаты)
            'formatedPaymentDate' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }
}

