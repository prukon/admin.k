<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Привязка разового занятия (lesson_packages.schedule_type = no_schedule) к слоту календаря школы.
 */
final class AssignSchoolCalendarSingleLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('lessonPackages.view');
    }

    public function rules(): array
    {
        return [
            'user_lesson_package_id' => ['required', 'integer', 'min:1', 'exists:user_lesson_packages,id'],
            'team_schedule_slot_id' => ['required', 'integer', 'min:1', 'exists:team_schedule_slots,id'],
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_lesson_package_id.required' => 'Выберите назначение абонемента.',
            'user_lesson_package_id.exists' => 'Назначение не найдено.',
            'team_schedule_slot_id.required' => 'Не передан слот расписания.',
            'team_schedule_slot_id.exists' => 'Слот расписания не найден.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_lesson_package_id' => 'назначение абонемента',
            'team_schedule_slot_id' => 'слот расписания',
            'occurrence_date' => 'дата занятия',
        ];
    }
}
