<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserYearPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'team_id' => ['required', 'integer', 'min:1'],
            'year'    => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_id' => 'группа',
            'year'    => 'год',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Выберите группу для просмотра цен.',
            'user_id.required' => 'Не указан ученик.',
            'year.required'    => 'Не указан год.',
        ];
    }
}
