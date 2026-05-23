<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FormTrainerSalarySnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedule.trainerSalary.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function attributes(): array
    {
        return [
            'year' => 'год',
            'month' => 'месяц',
        ];
    }
}
