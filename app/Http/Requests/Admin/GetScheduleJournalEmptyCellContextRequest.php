<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GetScheduleJournalEmptyCellContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'context_team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'occurrence_date' => 'дата занятия',
            'context_team_id' => 'группа',
        ];
    }

    public function messages(): array
    {
        return [
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Некорректный формат даты занятия.',
        ];
    }
}
