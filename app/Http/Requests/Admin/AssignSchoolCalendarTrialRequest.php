<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AssignSchoolCalendarTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('lessonPackages.view');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'team_schedule_slot_id' => ['required', 'integer', 'min:1', 'exists:team_schedule_slots,id'],
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.exists' => 'Ученик не найден.',
            'team_schedule_slot_id.required' => 'Не передан слот расписания.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_schedule_slot_id' => 'слот расписания',
            'occurrence_date' => 'дата занятия',
        ];
    }
}
