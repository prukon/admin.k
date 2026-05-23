<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TrainerWorkloadReportRequest extends FormRequest
{
    private const MAX_PERIOD_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'show_groups' => ['nullable', 'boolean'],
        ];
    }

    public function showGroups(): bool
    {
        return $this->boolean('show_groups', false);
    }

    public function attributes(): array
    {
        return [
            'date_from' => 'дата начала',
            'date_to' => 'дата окончания',
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date_format' => 'Некорректный формат даты начала.',
            'date_to.date_format' => 'Некорректный формат даты окончания.',
            'date_to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$from, $to] = $this->resolvedPeriodStrings();

            $fromCarbon = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
            $toCarbon = Carbon::createFromFormat('Y-m-d', $to)->startOfDay();

            if ($fromCarbon->diffInDays($toCarbon) > self::MAX_PERIOD_DAYS) {
                $validator->errors()->add(
                    'date_to',
                    'Период не может быть длиннее ' . self::MAX_PERIOD_DAYS . ' дней.',
                );
            }
        });
    }

    /**
     * @return array{0: string, 1: string} [date_from, date_to] Y-m-d
     */
    public function resolvedPeriodStrings(): array
    {
        $to = $this->input('date_to');
        $from = $this->input('date_from');

        if ($to === null && $from === null) {
            $end = Carbon::today();

            return [
                $end->copy()->subDays(29)->toDateString(),
                $end->toDateString(),
            ];
        }

        if ($to !== null && $from !== null) {
            return [(string) $from, (string) $to];
        }

        if ($to !== null) {
            $end = Carbon::createFromFormat('Y-m-d', (string) $to)->startOfDay();

            return [
                $end->copy()->subDays(29)->toDateString(),
                $end->toDateString(),
            ];
        }

        $start = Carbon::createFromFormat('Y-m-d', (string) $from)->startOfDay();

        return [
            $start->toDateString(),
            $start->copy()->addDays(29)->toDateString(),
        ];
    }
}
