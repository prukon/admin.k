<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class TrainerSalaryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedule.trainerSalary.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function attributes(): array
    {
        return [
            'year' => 'год',
            'month' => 'месяц',
        ];
    }

    public function messages(): array
    {
        return [
            'year.integer' => 'Некорректный год.',
            'year.min' => 'Год не может быть раньше :min.',
            'year.max' => 'Год не может быть позже :max.',
            'month.integer' => 'Некорректный месяц.',
            'month.min' => 'Месяц должен быть от 1 до 12.',
            'month.max' => 'Месяц должен быть от 1 до 12.',
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function resolvedYearMonth(): array
    {
        $today = Carbon::today();

        $year = $this->input('year');
        $month = $this->input('month');

        if ($year === null && $month === null) {
            return [(int) $today->year, (int) $today->month];
        }

        return [
            (int) ($year ?? $today->year),
            (int) ($month ?? $today->month),
        ];
    }
}
