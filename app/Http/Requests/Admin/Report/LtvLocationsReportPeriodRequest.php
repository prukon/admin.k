<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use Illuminate\Foundation\Http\FormRequest;

class LtvLocationsReportPeriodRequest extends FormRequest
{
    public const PERIODS = ['current', 'previous', 'all'];

    public const MONTH_NAMES = [
        1  => 'Январь',
        2  => 'Февраль',
        3  => 'Март',
        4  => 'Апрель',
        5  => 'Май',
        6  => 'Июнь',
        7  => 'Июль',
        8  => 'Август',
        9  => 'Сентябрь',
        10 => 'Октябрь',
        11 => 'Ноябрь',
        12 => 'Декабрь',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $period = $this->input('period');
        if ($period === null || $period === '') {
            $this->merge(['period' => 'current']);
        }
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', 'in:'.implode(',', self::PERIODS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'period' => 'Период',
        ];
    }

    public function messages(): array
    {
        return [
            'period.string' => 'Поле «:attribute» должно быть строкой.',
            'period.in' => 'Поле «:attribute» содержит недопустимое значение.',
        ];
    }

    public function period(): string
    {
        $period = $this->validated()['period'] ?? null;
        if ($period === 'previous' || $period === 'all') {
            return $period;
        }

        return 'current';
    }

    /**
     * @return array{current: string, previous: string, all: string}
     */
    public function periodLabels(): array
    {
        $now = now();
        $previous = $now->copy()->startOfMonth()->subMonth();

        return [
            'current' => self::MONTH_NAMES[(int) $now->month] ?? $now->format('m'),
            'previous' => self::MONTH_NAMES[(int) $previous->month] ?? $previous->format('m'),
            'all' => 'Все время',
        ];
    }

    /**
     * Границы operation_date для таба. null — «Все время».
     *
     * @return array{from: string, to: string}|null
     */
    public function periodDateRange(): ?array
    {
        $period = $this->period();
        $now = now();

        if ($period === 'current') {
            return [
                'from' => $now->copy()->startOfMonth()->toDateString(),
                'to' => $now->toDateString(),
            ];
        }

        if ($period === 'previous') {
            $start = $now->copy()->startOfMonth()->subMonth();

            return [
                'from' => $start->toDateString(),
                'to' => $start->copy()->endOfMonth()->toDateString(),
            ];
        }

        return null;
    }
}
