<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetManualUserPeriodPricePaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'mode' => [
                'required',
                'string',
                Rule::in(['paid', 'unpaid']),
            ],
            'comment' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'mode' => 'режим',
            'comment' => 'комментарий',
        ];
    }

    public function messages(): array
    {
        return [
            'mode.required' => 'Не выбран режим изменения статуса оплаты.',
            'mode.in' => 'Некорректный режим изменения статуса оплаты.',

            'comment.required' => 'Укажите комментарий.',
            'comment.string' => 'Комментарий должен быть строкой.',
            'comment.min' => 'Комментарий слишком короткий (минимум 3 символа).',
            'comment.max' => 'Комментарий слишком длинный (максимум 5000 символов).',
        ];
    }
}

