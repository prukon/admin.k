<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignSchoolCalendarFixedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('lessonPackages.view');
    }

    protected function prepareForValidation(): void
    {
        $loc = $this->input('location_id');
        if ($loc === '' || $loc === null) {
            $this->merge(['location_id' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'user_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('partner_id', $partnerId)),
            ],
            'user_lesson_package_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('user_lesson_packages', 'id'),
            ],
            'team_schedule_slot_id' => ['required', 'integer', 'min:1', 'exists:team_schedule_slots,id'],
            'anchor_date' => ['required', 'date_format:Y-m-d'],
            'location_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.exists' => 'Ученик не найден в контексте текущего партнёра.',
            'user_lesson_package_id.required' => 'Выберите назначение абонемента.',
            'user_lesson_package_id.exists' => 'Назначение не найдено.',
            'team_schedule_slot_id.required' => 'Не передан слот расписания.',
            'anchor_date.required' => 'Укажите дату первого занятия.',
            'anchor_date.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'user_lesson_package_id' => 'назначение абонемента',
            'team_schedule_slot_id' => 'слот расписания',
            'anchor_date' => 'дата начала',
            'location_id' => 'локация',
        ];
    }
}
