<?php

namespace App\Http\Requests\Contracts;

use App\Models\UserTableSetting;
use Illuminate\Validation\Rule;

class ContractsColumnsSettingsSaveRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'columns' => ['required_without:page_length', 'array'],
            'page_length' => ['sometimes', 'integer', Rule::in(UserTableSetting::PAGE_LENGTHS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'columns' => 'Колонки',
            'page_length' => 'Показывать по',
        ];
    }

    public function messages(): array
    {
        return [
            'columns.required_without' => 'Передайте настройки колонок.',
            'columns.array'    => 'Настройки колонок должны быть массивом.',
            'page_length.integer' => 'Количество строк должно быть целым числом.',
            'page_length.in' => 'Можно показать 10, 20, 50 или 100 записей.',
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

