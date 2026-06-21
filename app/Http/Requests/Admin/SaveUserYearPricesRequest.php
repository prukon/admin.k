<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveUserYearPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'              => ['required', 'integer', 'min:1'],
            'team_id'              => ['required', 'integer', 'min:1'],
            'year'                 => ['required', 'integer', 'min:2000', 'max:2100'],
            'prices'               => ['required', 'array'],
            'prices.*.new_month'   => ['required', 'date_format:Y-m-d'],
            'prices.*.price'       => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'            => 'ученик',
            'team_id'            => 'группа',
            'year'               => 'год',
            'prices'             => 'цены',
            'prices.*.new_month' => 'месяц',
            'prices.*.price'     => 'цена',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Выберите группу для сохранения цен.',
        ];
    }
}
