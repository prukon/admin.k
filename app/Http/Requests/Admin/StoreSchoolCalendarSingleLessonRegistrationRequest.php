<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Запись разового занятия из модалки слота: привязка существующего назначения или создание нового.
 */
final class StoreSchoolCalendarSingleLessonRegistrationRequest extends FormRequest
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
            'user_lesson_package_id' => ['nullable', 'integer', 'min:1', 'exists:user_lesson_packages,id'],
            'lesson_package_id' => ['nullable', 'integer', 'min:1', 'exists:lesson_packages,id'],
            'fee_amount' => ['required_with:lesson_package_id', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $ulpId = (int) $this->input('user_lesson_package_id', 0);
            $templateId = (int) $this->input('lesson_package_id', 0);

            if ($ulpId > 0 && $templateId > 0) {
                $v->errors()->add('user_lesson_package_id', 'Укажите либо существующее назначение, либо шаблон для нового разового занятия.');

                return;
            }

            if ($ulpId < 1 && $templateId < 1) {
                $v->errors()->add('lesson_package_id', 'Выберите назначение или шаблон разового занятия.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.exists' => 'Ученик не найден.',
            'team_schedule_slot_id.required' => 'Не передан слот расписания.',
            'team_schedule_slot_id.exists' => 'Слот расписания не найден.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
            'user_lesson_package_id.exists' => 'Назначение не найдено.',
            'lesson_package_id.exists' => 'Шаблон абонемента не найден.',
            'fee_amount.required_with' => 'Укажите стоимость разового занятия.',
            'fee_amount.min' => 'Стоимость не может быть отрицательной.',
            'fee_amount.max' => 'Слишком большая сумма.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_schedule_slot_id' => 'слот расписания',
            'occurrence_date' => 'дата занятия',
            'user_lesson_package_id' => 'назначение',
            'lesson_package_id' => 'шаблон абонемента',
            'fee_amount' => 'стоимость',
        ];
    }
}
