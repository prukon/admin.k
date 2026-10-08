<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SettingPricesInvoiceEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('setPrices.invoiceEmail.send');
    }

    protected function prepareForValidation(): void
    {
        $rawMonth = trim((string) $this->input('new_month', ''));
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawMonth, $match) === 1) {
            $this->merge(['new_month' => $match[1]]);
        }

        if ($this->exists('user_id')) {
            $this->merge(['user_id' => $this->input('user_id')]);
        }
        if ($this->exists('team_id')) {
            $this->merge(['team_id' => $this->input('team_id')]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'team_id' => ['required', 'integer', 'min:1'],
            'new_month' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_id' => 'группа',
            'new_month' => 'месяц',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Укажите ученика.',
            'user_id.integer' => 'Ученик указан неверно.',
            'user_id.min' => 'Ученик указан неверно.',
            'team_id.required' => 'Укажите группу.',
            'team_id.integer' => 'Группа указана неверно.',
            'team_id.min' => 'Группа указана неверно.',
            'new_month.required' => 'Укажите месяц начисления.',
            'new_month.date_format' => 'Месяц начисления указан неверно.',
        ];
    }
};
