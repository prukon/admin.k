<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

final class StoreUserLessonPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'lesson_package_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id'),
            ],
            'starts_at' => [
                'required',
                'date_format:Y-m-d',
            ],

            // Для flexible допускаем назначение без слотов, поэтому валидируем slots как nullable.
            // Для fixed/no_schedule слоты назначения вообще не нужны.
            'time_slots' => [
                'nullable',
                'array',
                'max:21',
            ],
            'time_slots.*.weekday' => [
                'nullable',
                'integer',
                'min:1',
                'max:7',
            ],
            'time_slots.*.time_start' => [
                'nullable',
                'date_format:H:i',
            ],
            'time_slots.*.time_end' => [
                'nullable',
                'date_format:H:i',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $slots = $this->input('time_slots', null);
            if ($slots === null) {
                return;
            }
            if (!is_array($slots)) {
                return;
            }

            foreach ($slots as $i => $slot) {
                $weekday = (string) ($slot['weekday'] ?? '');
                $start = (string) ($slot['time_start'] ?? '');
                $end = (string) ($slot['time_end'] ?? '');

                // Разрешаем пустые строки как "нет слота" (например, если фронт прислал пустую строку)
                $anyFilled = trim($weekday) !== '' || trim($start) !== '' || trim($end) !== '';
                if (!$anyFilled) {
                    continue;
                }

                if (trim($weekday) === '') {
                    $v->errors()->add("time_slots.$i.weekday", 'Укажите день недели.');
                }
                if (trim($start) === '') {
                    $v->errors()->add("time_slots.$i.time_start", 'Укажите время начала.');
                }
                if (trim($end) === '') {
                    $v->errors()->add("time_slots.$i.time_end", 'Укажите время окончания.');
                }

                if (trim($start) !== '' && trim($end) !== '' && $end <= $start) {
                    $v->errors()->add("time_slots.$i.time_end", 'Время окончания должно быть позже времени начала.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'lesson_package_id' => 'абонемент',
            'starts_at' => 'дата начала',
            'time_slots' => 'расписание',
            'time_slots.*.weekday' => 'день недели',
            'time_slots.*.time_start' => 'время начала',
            'time_slots.*.time_end' => 'время окончания',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.exists' => 'Ученик не найден или недоступен в контексте текущего партнёра.',

            'lesson_package_id.required' => 'Выберите абонемент.',
            'lesson_package_id.exists' => 'Абонемент не найден.',

            'starts_at.required' => 'Укажите дату начала.',
            'starts_at.date_format' => 'Дата начала должна быть в формате ГГГГ-ММ-ДД.',

            'time_slots.array' => 'Некорректный формат расписания.',
            'time_slots.max' => 'Слишком много слотов расписания.',

            'time_slots.*.weekday.integer' => 'День недели должен быть числом.',
            'time_slots.*.time_start.date_format' => 'Время начала должно быть в формате ЧЧ:ММ.',
            'time_slots.*.time_end.date_format' => 'Время окончания должно быть в формате ЧЧ:ММ.',
        ];
    }
}

