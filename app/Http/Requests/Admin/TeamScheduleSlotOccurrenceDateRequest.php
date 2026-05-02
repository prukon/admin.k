<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class TeamScheduleSlotOccurrenceDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
        ];
    }
}
