<?php

namespace App\Http\Requests\Admin;

use App\Models\LessonPackage;
use App\Support\LessonPackagePostpayPermission;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $autoAttendanceEnabled = $this->boolean('auto_attendance_enabled');
        $scheduleType = (string) $this->input('schedule_type', '');

        $merge = [
            'price' => $price,
            'freeze_enabled' => $freezeEnabled,
            'auto_attendance_enabled' => $autoAttendanceEnabled,
        ];

        // Постоплата биллится календарным месяцем через users_prices — длительность/кол-во в шаблоне служебные.
        if ($scheduleType === LessonPackage::SCHEDULE_TYPE_POSTPAY) {
            $merge['duration_days'] = 31;
            $merge['lessons_count'] = 1;
            $merge['freeze_enabled'] = false;
            $merge['auto_attendance_enabled'] = false;
        }

        $this->merge($merge);
    }

    protected function passedValidation(): void
    {
        $priceCents = Money::toCentsOrFail($this->validated('price'));

        $freezeEnabled = (bool) $this->validated('freeze_enabled');
        $freezeDays = (int) ($this->validated('freeze_days') ?? 0);
        $autoAttendanceEnabled = (bool) $this->validated('auto_attendance_enabled');

        $this->merge([
            'price_cents' => $priceCents,
            'freeze_days' => $freezeEnabled ? $freezeDays : 0,
            'auto_attendance_enabled' => $autoAttendanceEnabled,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'schedule_type' => [
                'required',
                'string',
                Rule::in(LessonPackage::SCHEDULE_TYPES),
            ],
            'duration_days' => [
                Rule::requiredIf(fn () => (string) $this->input('schedule_type') !== LessonPackage::SCHEDULE_TYPE_POSTPAY),
                'nullable',
                'integer',
                'min:1',
                'max:3650',
            ],
            'lessons_count' => [
                Rule::requiredIf(fn () => (string) $this->input('schedule_type') !== LessonPackage::SCHEDULE_TYPE_POSTPAY),
                'nullable',
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
            'auto_attendance_enabled' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $scheduleType = (string) $this->input('schedule_type', '');

            $existing = $this->route('lessonPackage');
            LessonPackagePostpayPermission::rejectUnauthorizedScheduleType(
                $v,
                $this->user(),
                $scheduleType,
                $existing instanceof LessonPackage ? $existing : null,
            );

            if ($scheduleType === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE) {
                if ((int) $this->input('duration_days') !== 1) {
                    $v->errors()->add('duration_days', 'Для разового занятия длительность должна быть 1 день.');
                }
                if ((int) $this->input('lessons_count') !== 1) {
                    $v->errors()->add('lessons_count', 'Для разового занятия количество занятий должно быть 1.');
                }
                if ($this->boolean('freeze_enabled')) {
                    $v->errors()->add('freeze_enabled', 'Для разового занятия заморозка недоступна.');
                }
                if ($this->boolean('auto_attendance_enabled')) {
                    $v->errors()->add('auto_attendance_enabled', 'Для разового занятия автосписание недоступно.');
                }
            }

            if ($scheduleType === LessonPackage::SCHEDULE_TYPE_POSTPAY) {
                if ($this->boolean('freeze_enabled')) {
                    $v->errors()->add('freeze_enabled', 'Для постоплаты заморозка недоступна.');
                }
                if ($this->boolean('auto_attendance_enabled')) {
                    $v->errors()->add('auto_attendance_enabled', 'Для постоплаты автосписание недоступно.');
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
            'auto_attendance_enabled' => 'автосписание',
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
        ];
    }
}
