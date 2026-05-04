<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'patterns' => ['required', 'array', 'min:1', 'max:21'],
            'patterns.*.weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'patterns.*.time_start' => ['required', 'date_format:H:i'],
            'patterns.*.time_end' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $patterns = $this->input('patterns');
            if (! is_array($patterns)) {
                return;
            }
            foreach ($patterns as $idx => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $start = (string) ($row['time_start'] ?? '');
                $end = (string) ($row['time_end'] ?? '');
                if ($start !== '' && $end !== '' && $end <= $start) {
                    $v->errors()->add(
                        'patterns.'.$idx.'.time_end',
                        'Время окончания должно быть позже времени начала.'
                    );
                }
            }
        });
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
            'patterns.required' => 'Укажите хотя бы один слот шаблона привязки.',
            'patterns.min' => 'Укажите хотя бы один слот шаблона привязки.',
            'patterns.*.weekday.required' => 'Укажите день недели.',
            'patterns.*.time_start.required' => 'Укажите время начала слота.',
            'patterns.*.time_end.required' => 'Укажите время окончания слота.',
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
            'patterns' => 'шаблон привязки',
            'patterns.*.weekday' => 'день недели',
            'patterns.*.time_start' => 'время начала',
            'patterns.*.time_end' => 'время окончания',
        ];
    }
}
