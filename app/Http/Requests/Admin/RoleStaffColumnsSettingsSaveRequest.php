<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\UserTableSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleStaffColumnsSettingsSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isAdministrators = trim((string) $this->input('table_key', '')) === 'role_staff_admin';

        $rules = [
            'table_key' => ['required', 'regex:/^role_staff_[a-z0-9_]+$/'],
            'columns' => [
                $isAdministrators ? 'required_without:page_length' : 'required',
                'array',
            ],
        ];

        if ($isAdministrators) {
            $rules['page_length'] = [
                'sometimes',
                'integer',
                Rule::in(UserTableSetting::PAGE_LENGTHS),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'table_key' => 'Ключ таблицы',
            'columns' => 'Колонки',
            'page_length' => 'Показывать по',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'table_key.required' => 'Некорректный ключ таблицы.',
            'table_key.regex' => 'Некорректный ключ таблицы.',
            'columns.required' => 'Передайте настройки колонок.',
            'columns.required_without' => 'Передайте настройки колонок.',
            'columns.array' => 'Настройки колонок должны быть массивом.',
            'page_length.integer' => 'Количество строк должно быть целым числом.',
            'page_length.in' => 'Можно показать 10, 20, 50 или 100 записей.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('columns');
        if (! is_array($raw)) {
            return;
        }

        $normalized = [];
        foreach ($raw as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $normalized[$key] = $bool ?? false;
        }

        $this->merge(['columns' => $normalized]);
    }
}
