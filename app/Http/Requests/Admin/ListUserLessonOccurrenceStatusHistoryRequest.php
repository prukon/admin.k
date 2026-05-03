<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ListUserLessonOccurrenceStatusHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_schedule_slot_id' => ['required', 'integer', 'min:1'],
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'user_id' => ['required', 'integer', 'min:1'],
            'user_lesson_package_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'team_schedule_slot_id.required' => 'Укажите слот расписания.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Дата занятия должна быть в формате ГГГГ-ММ-ДД.',
            'user_id.required' => 'Укажите ученика.',
        ];
    }
}
