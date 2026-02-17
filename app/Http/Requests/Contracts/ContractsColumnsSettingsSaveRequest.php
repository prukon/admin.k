<?php

namespace App\Http\Requests\Contracts;

class ContractsColumnsSettingsSaveRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'columns' => ['required', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'columns' => 'Колонки',
        ];
    }

    public function messages(): array
    {
        return [
            'columns.required' => 'Передайте настройки колонок.',
            'columns.array'    => 'Настройки колонок должны быть массивом.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('columns');
        if (!is_array($raw)) {
            return;
        }

        $normalized = [];
        foreach ($raw as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[$key] = $bool;
        }

        $this->merge(['columns' => $normalized]);
    }
}

