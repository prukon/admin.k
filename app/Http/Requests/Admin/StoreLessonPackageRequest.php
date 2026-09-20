<?php

namespace App\Http\Requests\Admin;

use App\Models\LessonPackage;
use App\Support\LessonPackageAutoAttendancePermission;
use App\Support\LessonPackageContractFieldsPermission;
use App\Support\LessonPackageDurationPermission;
use App\Support\LessonPackageFreezePermission;
use App\Support\LessonPackageTypePermission;
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
        $freezeDays = $this->input('freeze_days');
        $existing = $this->route('lessonPackage');
        $existingPackage = $existing instanceof LessonPackage ? $existing : null;

        $lessonPrice = $this->input('lesson_price', null);
        if (is_string($lessonPrice)) {
            $lessonPrice = str_replace([' ', ','], ['', '.'], trim($lessonPrice));
        }

        $merge = [
            'price' => $price,
            'freeze_enabled' => $freezeEnabled,
            'auto_attendance_enabled' => $autoAttendanceEnabled,
            'lessons_per_week' => $this->blankToNull($this->input('lessons_per_week')),
            'lesson_duration_minutes' => $this->blankToNull($this->input('lesson_duration_minutes')),
            'lesson_price' => ($lessonPrice === '' || $lessonPrice === null) ? null : $lessonPrice,
        ];

        if ($freezeDays === '' || $freezeDays === null) {
            $merge['freeze_days'] = null;
        }

        // Без bind поле скрыто: не валидируем крафт и не даём записать значения с клиента.
        if (! LessonPackageContractFieldsPermission::userCanManage($this->user())) {
            $merge['lessons_per_week'] = null;
            $merge['lesson_duration_minutes'] = null;
            $merge['lesson_price'] = null;
        }

        // Постоплата биллится календарным месяцем через users_prices — длительность/кол-во в шаблоне служебные.
        // auto_attendance_enabled здесь не обнуляем: withValidator вернёт 422 при попытке включить.
        if ($scheduleType === LessonPackage::SCHEDULE_TYPE_POSTPAY) {
            $merge['duration_days'] = LessonPackageDurationPermission::POSTPAY_DAYS;
            $merge['lessons_count'] = 1;
            $merge['freeze_enabled'] = false;
        } elseif ($scheduleType === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE) {
            $merge['duration_days'] = LessonPackageDurationPermission::NO_SCHEDULE_DAYS;
        } elseif (! LessonPackageDurationPermission::userCanManage($this->user())) {
            // Поле скрыто без scheduleSlots.view: create → 30, update → уже сохранённый срок.
            $merge['duration_days'] = LessonPackageDurationPermission::resolvedDays(
                $this->user(),
                $scheduleType,
                $this->input('duration_days'),
                $existingPackage,
            );
        }

        $this->merge($merge);
    }

    protected function passedValidation(): void
    {
        $priceCents = Money::toCentsOrFail($this->validated('price'));

        $freezeEnabled = (bool) $this->validated('freeze_enabled');
        $freezeDays = (int) ($this->validated('freeze_days') ?? 0);
        $autoAttendanceEnabled = (bool) $this->validated('auto_attendance_enabled');

        $lessonPrice = $this->validated('lesson_price');
        $lessonPriceCents = ($lessonPrice === null || $lessonPrice === '')
            ? null
            : Money::toCentsOrFail($lessonPrice);

        $this->merge([
            'price_cents' => $priceCents,
            'lesson_price_cents' => $lessonPriceCents,
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
                Rule::requiredIf(fn () => (string) $this->input('schedule_type') !== LessonPackage::SCHEDULE_TYPE_POSTPAY
                    && LessonPackageDurationPermission::userCanManage($this->user())),
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
            'lessons_per_week' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'lesson_duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'max:1440',
            ],
            'lesson_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'freeze_enabled' => [
                'nullable',
                'boolean',
            ],
            'freeze_days' => [
                Rule::requiredIf(fn () => $this->boolean('freeze_enabled')
                    && LessonPackageFreezePermission::userCanManage($this->user())),
                'nullable',
                'integer',
                'max:3650',
                Rule::when(
                    fn () => $this->boolean('freeze_enabled'),
                    ['min:1']
                ),
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
            LessonPackageTypePermission::rejectUnauthorizedScheduleType(
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

            LessonPackageFreezePermission::rejectUnauthorizedEnable(
                $v,
                $this->user(),
                $this->boolean('freeze_enabled'),
            );

            LessonPackageAutoAttendancePermission::rejectUnauthorizedEnable(
                $v,
                $this->user(),
                $this->boolean('auto_attendance_enabled'),
            );
        });
    }

    public function resolvedFreezeEnabled(?LessonPackage $existing = null): bool
    {
        return LessonPackageFreezePermission::resolvedEnabled(
            $this->user(),
            $this->boolean('freeze_enabled'),
            $existing,
        );
    }

    public function resolvedFreezeDays(?LessonPackage $existing = null): int
    {
        return LessonPackageFreezePermission::resolvedDays(
            $this->user(),
            $this->boolean('freeze_enabled'),
            (int) $this->input('freeze_days', 0),
            $existing,
        );
    }

    public function resolvedAutoAttendanceEnabled(?LessonPackage $existing = null): bool
    {
        return LessonPackageAutoAttendancePermission::resolvedValue(
            $this->user(),
            $this->boolean('auto_attendance_enabled'),
            $existing,
        );
    }

    public function resolvedDurationDays(?LessonPackage $existing = null): int
    {
        return LessonPackageDurationPermission::resolvedDays(
            $this->user(),
            (string) $this->input('schedule_type', ''),
            $this->input('duration_days'),
            $existing,
        );
    }

    public function attributes(): array
    {
        return [
            'name' => 'название абонемента',
            'schedule_type' => 'тип расписания',
            'duration_days' => 'длительность (дни)',
            'lessons_count' => 'кол-во занятий',
            'price' => 'стоимость',
            'lessons_per_week' => 'кол-во занятий в неделю',
            'lesson_duration_minutes' => 'длительность занятий (мин)',
            'lesson_price' => 'стоимость одного занятия',
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

            'lessons_per_week.integer' => 'Количество занятий в неделю должно быть целым числом.',
            'lessons_per_week.min' => 'Количество занятий в неделю должно быть больше нуля.',
            'lessons_per_week.max' => 'Количество занятий в неделю слишком большое.',

            'lesson_duration_minutes.integer' => 'Длительность занятий должна быть целым числом.',
            'lesson_duration_minutes.min' => 'Длительность занятий должна быть больше нуля.',
            'lesson_duration_minutes.max' => 'Длительность занятий слишком большая.',

            'lesson_price.numeric' => 'Стоимость одного занятия должна быть числом.',
            'lesson_price.min' => 'Стоимость одного занятия не может быть отрицательной.',
            'lesson_price.max' => 'Стоимость одного занятия слишком большая.',

            'freeze_enabled.boolean' => 'Некорректное значение заморозки.',
            'freeze_days.required' => 'Укажите количество дней заморозки.',
            'freeze_days.integer' => 'Количество дней заморозки должно быть целым числом.',
            'freeze_days.min' => 'Количество дней заморозки должно быть больше нуля.',
            'freeze_days.max' => 'Количество дней заморозки слишком большое.',
        ];
    }

    public function resolvedLessonPriceCents(?LessonPackage $existing = null): ?int
    {
        return LessonPackageContractFieldsPermission::resolvedInt(
            $this->user(),
            $this->input('lesson_price_cents'),
            $existing?->lesson_price_cents,
        );
    }

    public function resolvedLessonsPerWeek(?LessonPackage $existing = null): ?int
    {
        return LessonPackageContractFieldsPermission::resolvedInt(
            $this->user(),
            $this->input('lessons_per_week'),
            $existing?->lessons_per_week,
        );
    }

    public function resolvedLessonDurationMinutes(?LessonPackage $existing = null): ?int
    {
        return LessonPackageContractFieldsPermission::resolvedInt(
            $this->user(),
            $this->input('lesson_duration_minutes'),
            $existing?->lesson_duration_minutes,
        );
    }

    private function blankToNull(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
