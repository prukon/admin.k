<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

final class StoreLessonPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $price = $this->input('price', null);
        if (is_string($price)) {
            $price = str_replace([' ', ','], ['', '.'], trim($price));
        }

        $freezeEnabled = $this->boolean('freeze_enabled');

        $this->merge([
            'price' => $price,
            'freeze_enabled' => $freezeEnabled,
        ]);
    }

    protected function passedValidation(): void
    {
        $price = (float) $this->validated('price');
        $priceCents = (int) round($price * 100);

        $freezeEnabled = (bool) $this->validated('freeze_enabled');
        $freezeDays = (int) ($this->validated('freeze_days') ?? 0);

        $this->merge([
            'price_cents' => $priceCents,
            'freeze_days' => $freezeEnabled ? $freezeDays : 0,
        ]);
    }

    public function rules(): array
    {
        $scheduleType = (string) $this->input('schedule_type', '');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'schedule_type' => [
                'required',
                'string',
                Rule::in(['fixed', 'flexible', 'no_schedule']),
            ],
            'duration_days' => [
                'required',
                'integer',
                'min:1',
                'max:3650',
            ],
            'lessons_count' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'freeze_enabled' => [
                'nullable',
                'boolean',
            ],
            'freeze_days' => [
                Rule::requiredIf(fn () => (bool) $this->boolean('freeze_enabled')),
                'nullable',
                'integer',
                'min:1',
                'max:3650',
            ],

            // Слоты нужны только для fixed, для остальных — не используем
            'time_slots' => [
                Rule::requiredIf(fn () => $scheduleType === 'fixed'),
                'nullable',
                'array',
                'min:1',
                'max:21',
            ],
            'time_slots.*.weekday' => [
                'exclude_unless:schedule_type,fixed',
                'required',
                'integer',
                'min:1',
                'max:7',
            ],
            'time_slots.*.time_start' => [
                'exclude_unless:schedule_type,fixed',
                'required',
                'date_format:H:i',
            ],
            'time_slots.*.time_end' => [
                'exclude_unless:schedule_type,fixed',
                'required',
                'date_format:H:i',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $scheduleType = (string) $this->input('schedule_type', '');
            if ($scheduleType !== 'fixed') {
                return;
            }

            $slots = $this->input('time_slots', []);
            if (!is_array($slots)) {
                return;
            }

            foreach ($slots as $i => $slot) {
                $start = (string) ($slot['time_start'] ?? '');
                $end = (string) ($slot['time_end'] ?? '');

                if ($start === '' || $end === '') {
                    continue;
                }

                // Сравниваем строками HH:MM (лексикографически совпадает с временем)
                if ($end <= $start) {
                    $v->errors()->add("time_slots.$i.time_end", 'Время окончания должно быть позже времени начала.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'название абонемента',
            'schedule_type' => 'тип расписания',
            'duration_days' => 'длительность (дни)',
            'lessons_count' => 'кол-во занятий',
            'price' => 'стоимость',
            'freeze_enabled' => 'заморозка',
            'freeze_days' => 'кол-во дней заморозки',
            'time_slots' => 'расписание',
            'time_slots.*.weekday' => 'день недели',
            'time_slots.*.time_start' => 'время начала',
            'time_slots.*.time_end' => 'время окончания',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название абонемента.',
            'name.max' => 'Название слишком длинное (максимум 255 символов).',

            'schedule_type.required' => 'Выберите тип абонемента.',
            'schedule_type.in' => 'Некорректный тип абонемента.',

            'duration_days.required' => 'Укажите длительность в днях.',
            'duration_days.integer' => 'Длительность должна быть целым числом.',
            'duration_days.min' => 'Длительность должна быть больше нуля.',
            'duration_days.max' => 'Длительность слишком большая.',

            'lessons_count.required' => 'Укажите количество занятий.',
            'lessons_count.integer' => 'Количество занятий должно быть целым числом.',
            'lessons_count.min' => 'Количество занятий должно быть больше нуля.',
            'lessons_count.max' => 'Количество занятий слишком большое.',

            'price.required' => 'Укажите стоимость.',
            'price.numeric' => 'Стоимость должна быть числом.',
            'price.min' => 'Стоимость не может быть отрицательной.',
            'price.max' => 'Стоимость слишком большая.',

            'freeze_days.required' => 'Укажите количество дней заморозки.',
            'freeze_days.integer' => 'Количество дней заморозки должно быть целым числом.',
            'freeze_days.min' => 'Количество дней заморозки должно быть больше нуля.',
            'freeze_days.max' => 'Количество дней заморозки слишком большое.',

            'time_slots.required' => 'Для фиксированного расписания добавьте хотя бы один слот (день и время).',
            'time_slots.array' => 'Некорректный формат расписания.',
            'time_slots.min' => 'Добавьте хотя бы один слот расписания.',
            'time_slots.max' => 'Слишком много слотов расписания.',

            'time_slots.*.weekday.required' => 'Укажите день недели для каждого слота.',
            'time_slots.*.weekday.integer' => 'День недели должен быть числом.',
            'time_slots.*.weekday.min' => 'Некорректный день недели.',
            'time_slots.*.weekday.max' => 'Некорректный день недели.',

            'time_slots.*.time_start.required' => 'Укажите время начала для каждого слота.',
            'time_slots.*.time_start.date_format' => 'Время начала должно быть в формате ЧЧ:ММ.',

            'time_slots.*.time_end.required' => 'Укажите время окончания для каждого слота.',
            'time_slots.*.time_end.date_format' => 'Время окончания должно быть в формате ЧЧ:ММ.',
        ];
    }
}

