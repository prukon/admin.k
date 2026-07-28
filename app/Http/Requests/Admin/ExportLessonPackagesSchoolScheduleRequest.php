<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ExportLessonPackagesSchoolScheduleRequest extends FormRequest
{
    /** Максимальная длина периода выгрузки (включительно), дни. */
    public const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('lessonPackages.export');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required' => 'Укажите дату начала периода.',
            'date_from.date_format' => 'Дата начала должна быть в формате ГГГГ-ММ-ДД.',
            'date_to.required' => 'Укажите дату окончания периода.',
            'date_to.date_format' => 'Дата окончания должна быть в формате ГГГГ-ММ-ДД.',
            'date_to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
        ];
    }

    public function attributes(): array
    {
        return [
            'date_from' => 'дата начала',
            'date_to' => 'дата окончания',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('date_from'))->startOfDay();
            $to = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('date_to'))->startOfDay();
            $days = $from->diffInDays($to) + 1;

            if ($days > self::MAX_RANGE_DAYS) {
                $v->errors()->add(
                    'date_to',
                    'Период выгрузки не более '.self::MAX_RANGE_DAYS.' дней. Уменьшите диапазон дат.'
                );
            }
        });
    }

    public function dateFrom(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('date_from'))->startOfDay();
    }

    public function dateTo(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('date_to'))->startOfDay();
    }
}
