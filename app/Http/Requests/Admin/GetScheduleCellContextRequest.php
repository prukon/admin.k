<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GetScheduleCellContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'date' => 'дата',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Не указан ученик.',
            'date.required' => 'Не указана дата.',
            'date.date_format' => 'Некорректный формат даты.',
        ];
    }
}
